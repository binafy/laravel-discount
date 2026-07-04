<?php

namespace Binafy\LaravelDiscount;

use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Exceptions\DiscountException;
use Binafy\LaravelDiscount\Exceptions\DiscountExpiredException;
use Binafy\LaravelDiscount\Exceptions\DiscountNotActiveException;
use Binafy\LaravelDiscount\Exceptions\DiscountNotStartedException;
use Binafy\LaravelDiscount\Exceptions\DiscountUsageLimitReachedException;
use Binafy\LaravelDiscount\Exceptions\MinimumOrderValueException;
use Binafy\LaravelDiscount\Models\Discount;
use Binafy\LaravelDiscount\Models\DiscountUsage;
use Binafy\LaravelDiscount\Support\DiscountResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DiscountManager
{
    /**
     * Calculate the amount this discount deducts from the given amount.
     * The result never exceeds the amount itself.
     */
    public function calculate(Discount $discount, float $amount): float
    {
        $value = (float) $discount->value;

        $discountAmount = $discount->type === DiscountType::Percentage
            ? $amount * $value / 100
            : $value;

        return round(min($discountAmount, $amount), 2);
    }

    /**
     * Ensure the discount is applicable, or throw a specific exception.
     *
     * @throws DiscountException
     */
    public function validate(Discount $discount, float $orderAmount = 0, Model|int|null $user = null): void
    {
        if (! $discount->is_active) {
            throw DiscountNotActiveException::for($discount);
        }

        if (! $discount->hasStarted()) {
            throw DiscountNotStartedException::for($discount);
        }

        if ($discount->isExpired()) {
            throw DiscountExpiredException::for($discount);
        }

        if ($discount->usageLimitReached()) {
            throw DiscountUsageLimitReachedException::for($discount);
        }

        if (! is_null($user) && ! is_null($discount->usage_limit_per_user)) {
            $userId = $user instanceof Model ? $user->getKey() : $user;

            $used = $discount->usages()->where('user_id', $userId)->count();

            if ($used >= $discount->usage_limit_per_user) {
                throw DiscountUsageLimitReachedException::for(
                    $discount,
                    'The discount usage limit for this user has been reached.'
                );
            }
        }

        if (! is_null($discount->min_order_value) && $orderAmount < (float) $discount->min_order_value) {
            throw MinimumOrderValueException::for($discount);
        }
    }

    /**
     * Determine if the discount is applicable, without throwing.
     */
    public function isValid(Discount $discount, float $orderAmount = 0, Model|int|null $user = null): bool
    {
        try {
            $this->validate($discount, $orderAmount, $user);

            return true;
        } catch (DiscountException) {
            return false;
        }
    }

    /**
     * Validate and apply a single discount to the given amount.
     *
     * @throws DiscountException
     */
    public function apply(Discount $discount, float $amount, Model|int|null $user = null): DiscountResult
    {
        $this->validate($discount, $amount, $user);

        return new DiscountResult(
            collect([$discount]),
            $amount,
            $this->calculate($discount, $amount)
        );
    }

    /**
     * Apply multiple discounts to the given amount, resolving stacking:
     * stackable discounts combine, non-stackable discounts compete alone,
     * and whichever combination saves the most wins. Invalid discounts
     * are silently skipped.
     */
    public function applyMany(iterable $discounts, float $amount, Model|int|null $user = null): DiscountResult
    {
        $valid = collect($discounts)->filter(
            fn (Discount $discount) => $this->isValid($discount, $amount, $user)
        );

        [$stackable, $solo] = $valid->partition(fn (Discount $discount) => $discount->is_stackable);

        $stackTotal = round(min(
            $stackable->sum(fn (Discount $discount) => $this->calculate($discount, $amount)),
            $amount
        ), 2);

        $bestSolo = $solo->sortByDesc(
            fn (Discount $discount) => $this->calculate($discount, $amount)
        )->first();
        $bestSoloAmount = $bestSolo ? $this->calculate($bestSolo, $amount) : 0.0;

        if ($stackable->isNotEmpty() && $stackTotal >= $bestSoloAmount) {
            return new DiscountResult($stackable->values(), $amount, $stackTotal);
        }

        return new DiscountResult(
            collect($bestSolo ? [$bestSolo] : []),
            $amount,
            $bestSoloAmount
        );
    }

    /**
     * Record a redemption: create a usage row and increment `used_count`.
     * The increment is guarded by the usage limit at the query level, so
     * concurrent redemptions cannot exceed the limit (no race condition).
     *
     * @throws DiscountUsageLimitReachedException
     */
    public function redeem(Discount $discount, Model|int $user, ?float $amount = null): DiscountUsage
    {
        $userId = $user instanceof Model ? $user->getKey() : $user;

        return DB::transaction(function () use ($discount, $userId, $amount) {
            if (! is_null($discount->usage_limit_per_user)) {
                $used = DiscountUsage::query()
                    ->where('discount_id', $discount->getKey())
                    ->where('user_id', $userId)
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
                'amount' => $amount,
                'used_at' => now(),
            ]);
        });
    }
}
