<?php

namespace Binafy\LaravelDiscount;

use Binafy\LaravelDiscount\Contracts\DiscountCondition;
use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Events\DiscountApplied;
use Binafy\LaravelDiscount\Events\DiscountExpired;
use Binafy\LaravelDiscount\Events\DiscountRedeemed;
use Binafy\LaravelDiscount\Exceptions\DiscountConditionFailedException;
use Binafy\LaravelDiscount\Exceptions\DiscountCurrencyMismatchException;
use Binafy\LaravelDiscount\Exceptions\DiscountException;
use Binafy\LaravelDiscount\Exceptions\DiscountExpiredException;
use Binafy\LaravelDiscount\Exceptions\DiscountNotActiveException;
use Binafy\LaravelDiscount\Exceptions\DiscountNotFoundException;
use Binafy\LaravelDiscount\Exceptions\DiscountNotStartedException;
use Binafy\LaravelDiscount\Exceptions\DiscountUsageLimitReachedException;
use Binafy\LaravelDiscount\Exceptions\InvalidDiscountConditionsException;
use Binafy\LaravelDiscount\Exceptions\MinimumOrderValueException;
use Binafy\LaravelDiscount\Models\Discount;
use Binafy\LaravelDiscount\Models\DiscountUsage;
use Binafy\LaravelDiscount\Support\ConditionFactory;
use Binafy\LaravelDiscount\Support\DiscountCodeGenerator;
use Binafy\LaravelDiscount\Support\DiscountContext;
use Binafy\LaravelDiscount\Support\DiscountResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DiscountManager
{
    /**
     * Calculate the amount this discount deducts from the given amount.
     * The result never exceeds the amount itself, nor the discount's
     * `max_discount_amount` cap when one is set.
     *
     * `$quantity` is the number of items the amount covers. It only
     * matters for "buy X get Y" discounts, which need to know how many
     * items are in the basket to work out how many come free.
     */
    public function calculate(Discount $discount, float $amount, int $quantity = 1): float
    {
        $discountAmount = match ($discount->type) {
            DiscountType::Percentage => $amount * (float) $discount->value / 100,
            DiscountType::Fixed => (float) $discount->value,
            DiscountType::BuyXGetY => $this->calculateBuyXGetY($discount, $amount, $quantity),
            DiscountType::Tiered => $this->calculateTiered($discount, $amount),
            DiscountType::FreeShipping => 0.0,
        };

        if (! is_null($discount->max_discount_amount)) {
            $discountAmount = min($discountAmount, (float) $discount->max_discount_amount);
        }

        return round(min($discountAmount, $amount), 2);
    }

    /**
     * Work out the value of the free items in a "buy X get Y" deal, e.g.
     * "buy 2, get 1 free". Every full set of X + Y items in the basket
     * earns Y free items, priced at the basket's average unit price.
     *
     * The `conditions` column holds the deal:
     *
     *     ['buy' => 2, 'get' => 1, 'get_discount_percentage' => 100, 'max_free_items' => 3]
     *
     * `get_discount_percentage` makes the free items merely cheaper
     * ("buy 2, get the third at 50% off") and defaults to a full 100%.
     * `max_free_items` caps how many items a single order can get.
     */
    protected function calculateBuyXGetY(Discount $discount, float $amount, int $quantity): float
    {
        $conditions = $discount->conditions ?? [];
        $buy = (int) ($conditions['buy'] ?? 0);
        $get = (int) ($conditions['get'] ?? 0);

        if ($buy < 1 || $get < 1 || $quantity < $buy + $get || $amount <= 0) {
            return 0.0;
        }

        $freeItems = intdiv($quantity, $buy + $get) * $get;

        if (! is_null($max = $conditions['max_free_items'] ?? null)) {
            $freeItems = min($freeItems, max((int) $max, 0));
        }

        $percentage = (float) ($conditions['get_discount_percentage'] ?? 100);
        $unitPrice = $amount / $quantity;

        return $freeItems * $unitPrice * $percentage / 100;
    }

    /**
     * Work out a tiered discount, where the discount grows with the
     * order total. The `conditions` column holds the ladder:
     *
     *     ['tiers' => [
     *         ['min' => 1_000_000, 'value' => 5],
     *         ['min' => 5_000_000, 'value' => 10],
     *     ]]
     *
     * The highest tier the amount reaches wins; an amount below every
     * tier gets nothing. A tier discounts by percentage unless it sets
     * `'type' => 'fixed'`.
     */
    protected function calculateTiered(Discount $discount, float $amount): float
    {
        $tier = $this->matchingTier($discount, $amount);

        if (is_null($tier)) {
            return 0.0;
        }

        $value = (float) ($tier['value'] ?? 0);

        return ($tier['type'] ?? DiscountType::Percentage->value) === DiscountType::Fixed->value
            ? $value
            : $amount * $value / 100;
    }

    /**
     * The highest tier the given amount reaches, or null when the amount
     * is below every tier.
     */
    public function matchingTier(Discount $discount, float $amount): ?array
    {
        return collect(($discount->conditions ?? [])['tiers'] ?? [])
            ->filter(fn ($tier) => is_array($tier) && $amount >= (float) ($tier['min'] ?? 0))
            ->sortByDesc(fn ($tier) => (float) ($tier['min'] ?? 0))
            ->first();
    }

    /**
     * Ensure the discount is applicable, or throw a specific exception.
     *
     * `$quantity` and `$payload` describe the order being discounted and are
     * handed to the discount's conditions; see `conditions.rules`. The
     * payload's `currency` is checked against the discount's currency.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws DiscountException
     */
    public function validate(Discount $discount, float $orderAmount = 0, Model|int|null $user = null, ?string $sessionId = null, int $quantity = 1, array $payload = []): void
    {
        if (! $discount->is_active) {
            throw DiscountNotActiveException::for($discount);
        }

        if (! $discount->hasStarted()) {
            throw DiscountNotStartedException::for($discount);
        }

        if ($discount->isExpired()) {
            DiscountExpired::dispatch($discount);

            throw DiscountExpiredException::for($discount);
        }

        if ($discount->usageLimitReached()) {
            throw DiscountUsageLimitReachedException::for($discount);
        }

        if (! is_null($discount->usage_limit_per_user) && (! is_null($user) || ! is_null($sessionId))) {
            $used = $this->perUserUsagesQuery($discount, $user, $sessionId)->count();

            if ($used >= $discount->usage_limit_per_user) {
                throw DiscountUsageLimitReachedException::for(
                    $discount,
                    'The discount usage limit for this user has been reached.'
                );
            }
        }

        $context = new DiscountContext($discount, $orderAmount, $user, $sessionId, $quantity, $payload);

        // Checked before the minimum order value, which means nothing when
        // the order total is counted in another currency.
        if (! $discount->appliesToCurrency($context->currency())) {
            throw DiscountCurrencyMismatchException::forCurrency($discount, $context->currency());
        }

        if (! is_null($discount->min_order_value) && $orderAmount < (float) $discount->min_order_value) {
            throw MinimumOrderValueException::for($discount);
        }

        $this->validateConditions($discount);

        $this->evaluateConditions($discount, $context);
    }

    /**
     * Run the conditions stored in the discount's `conditions.rules` array
     * against the order. By default every rule must pass; setting
     * `conditions.rules_match` to "any" is enough for one of them to.
     *
     * @throws DiscountException
     */
    protected function evaluateConditions(Discount $discount, DiscountContext $context): void
    {
        $conditions = $this->conditions($discount);

        if ($conditions->isEmpty()) {
            return;
        }

        $failed = $conditions->reject(fn (DiscountCondition $condition) => $condition->passes($context));

        $matchAny = ($discount->conditions['rules_match'] ?? 'all') === 'any';

        if ($matchAny ? $failed->count() === $conditions->count() : $failed->isNotEmpty()) {
            throw DiscountConditionFailedException::forCondition($discount, $failed->first());
        }
    }

    /**
     * The conditions stored on the discount, rebuilt as objects.
     *
     * @return Collection<int, DiscountCondition>
     *
     * @throws DiscountException
     */
    public function conditions(Discount $discount): Collection
    {
        return app(ConditionFactory::class)->make($discount);
    }

    /**
     * Ensure the types configured through the `conditions` column are
     * configured correctly, so a malformed discount fails loudly instead
     * of quietly deducting nothing.
     *
     * @throws InvalidDiscountConditionsException
     */
    protected function validateConditions(Discount $discount): void
    {
        if (! $discount->type->usesConditions()) {
            return;
        }

        $conditions = $discount->conditions ?? [];

        $valid = match ($discount->type) {
            DiscountType::BuyXGetY => (int) ($conditions['buy'] ?? 0) >= 1
                && (int) ($conditions['get'] ?? 0) >= 1,
            DiscountType::Tiered => is_array($conditions['tiers'] ?? null)
                && $conditions['tiers'] !== []
                && collect($conditions['tiers'])->every(
                    fn ($tier) => is_array($tier) && isset($tier['min'], $tier['value'])
                        && is_numeric($tier['min']) && is_numeric($tier['value'])
                ),
            default => true,
        };

        if (! $valid) {
            throw InvalidDiscountConditionsException::for($discount);
        }
    }

    /**
     * Determine if the discount is applicable, without throwing.
     */
    public function isValid(Discount $discount, float $orderAmount = 0, Model|int|null $user = null, ?string $sessionId = null, int $quantity = 1, array $payload = []): bool
    {
        try {
            $this->validate($discount, $orderAmount, $user, $sessionId, $quantity, $payload);

            return true;
        } catch (DiscountException) {
            return false;
        }
    }

    /**
     * Find a discount by its code.
     *
     * @throws DiscountNotFoundException
     */
    public function findByCode(string $code): Discount
    {
        $discount = Discount::query()->where('code', $code)->first();

        if (is_null($discount)) {
            throw DiscountNotFoundException::forCode($code);
        }

        return $discount;
    }

    /**
     * Validate and apply a discount, found by its code, to the given amount.
     *
     * @throws DiscountException
     */
    public function applyCode(string $code, float $amount, Model|int|null $user = null, ?string $sessionId = null, int $quantity = 1, array $payload = []): DiscountResult
    {
        return $this->apply($this->findByCode($code), $amount, $user, $sessionId, $quantity, $payload);
    }

    /**
     * Generate a single unique discount code.
     */
    public function generateCode(?string $prefix = null): string
    {
        return app(DiscountCodeGenerator::class)->generate($prefix);
    }

    /**
     * Generate a batch of unique discount codes.
     *
     * @return Collection<int, string>
     */
    public function generateCodes(int $count, ?string $prefix = null): Collection
    {
        return app(DiscountCodeGenerator::class)->generateMany($count, $prefix);
    }

    /**
     * Validate and apply a single discount to the given amount.
     *
     * `$quantity` is the number of items the amount covers, which "buy X
     * get Y" discounts need; every other type ignores it. `$payload` carries
     * anything the discount's conditions need, such as the order's items.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws DiscountException
     */
    public function apply(Discount $discount, float $amount, Model|int|null $user = null, ?string $sessionId = null, int $quantity = 1, array $payload = []): DiscountResult
    {
        $this->validate($discount, $amount, $user, $sessionId, $quantity, $payload);

        $result = new DiscountResult(
            collect([$discount]),
            $amount,
            $this->calculate($discount, $amount, $quantity),
            $discount->type === DiscountType::FreeShipping
        );

        DiscountApplied::dispatch($result, $user);

        return $result;
    }

    /**
     * Apply multiple discounts to the given amount, resolving stacking:
     * stackable discounts combine, non-stackable discounts compete alone,
     * and whichever combination saves the most wins. Invalid discounts
     * are silently skipped.
     *
     * Free shipping discounts sit outside that competition: they deduct
     * nothing from the amount, so they would always lose it. Every valid
     * one is applied and raises the result's free shipping flag.
     *
     * @param  array<string, mixed>  $payload
     */
    public function applyMany(iterable $discounts, float $amount, Model|int|null $user = null, ?string $sessionId = null, int $quantity = 1, array $payload = []): DiscountResult
    {
        $valid = collect($discounts)->filter(
            fn (Discount $discount) => $this->isValid($discount, $amount, $user, $sessionId, $quantity, $payload)
        );

        [$shipping, $monetary] = $valid->partition(
            fn (Discount $discount) => $discount->type === DiscountType::FreeShipping
        );

        [$stackable, $solo] = $monetary->partition(fn (Discount $discount) => $discount->is_stackable);

        $stackTotal = round(min(
            $stackable->sum(fn (Discount $discount) => $this->calculate($discount, $amount, $quantity)),
            $amount
        ), 2);

        $bestSolo = $solo->sortByDesc(
            fn (Discount $discount) => $this->calculate($discount, $amount, $quantity)
        )->first();
        $bestSoloAmount = $bestSolo ? $this->calculate($bestSolo, $amount, $quantity) : 0.0;

        [$winners, $discountAmount] = $stackable->isNotEmpty() && $stackTotal >= $bestSoloAmount
            ? [$stackable, $stackTotal]
            : [collect($bestSolo ? [$bestSolo] : []), $bestSoloAmount];

        $result = new DiscountResult(
            $winners->concat($shipping)->values(),
            $amount,
            $discountAmount,
            $shipping->isNotEmpty()
        );

        if ($result->discounts->isNotEmpty()) {
            DiscountApplied::dispatch($result, $user);
        }

        return $result;
    }

    /**
     * Record a redemption: create a usage row and increment `used_count`.
     * The increment is guarded by the usage limit at the query level, so
     * concurrent redemptions cannot exceed the limit (no race condition).
     */
    public function redeem(Discount $discount, Model|int|null $user = null, ?float $amount = null, ?string $sessionId = null): DiscountUsage
    {
        $userId = $user instanceof Model ? $user->getKey() : $user;

        $usage = DB::transaction(function () use ($discount, $userId, $amount, $sessionId) {
            if (! is_null($discount->usage_limit_per_user) && (! is_null($userId) || ! is_null($sessionId))) {
                $used = $this->perUserUsagesQuery($discount, $userId, $sessionId)
                    ->lockForUpdate()
                    ->count();

                if ($used >= $discount->usage_limit_per_user) {
                    throw DiscountUsageLimitReachedException::for(
                        $discount,
                        'The discount usage limit for this user has been reached.'
                    );
                }
            }

            $incremented = Discount::query()
                ->whereKey($discount->getKey())
                ->where(function ($query) {
                    $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
                })
                ->increment('used_count');

            if ($incremented === 0) {
                throw DiscountUsageLimitReachedException::for($discount);
            }

            $discount->refresh();

            return DiscountUsage::query()->create([
                'discount_id' => $discount->getKey(),
                'user_id' => $userId,
                'session_id' => $sessionId,
                'amount' => $amount,
                'used_at' => now(),
            ]);
        });

        DiscountRedeemed::dispatch($discount, $usage);

        return $usage;
    }

    /**
     * Query the usages that count toward the per-user limit: by user id
     * for authenticated users, or by session id for guests.
     */
    protected function perUserUsagesQuery(Discount $discount, Model|int|null $user, ?string $sessionId)
    {
        $query = DiscountUsage::query()->where('discount_id', $discount->getKey());

        if (! is_null($user)) {
            return $query->where('user_id', $user instanceof Model ? $user->getKey() : $user);
        }

        return $query->where('session_id', $sessionId);
    }
}
