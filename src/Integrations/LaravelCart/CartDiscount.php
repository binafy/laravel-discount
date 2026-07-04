<?php

namespace Binafy\LaravelDiscount\Integrations\LaravelCart;

use Binafy\LaravelCart\Models\Cart;
use Binafy\LaravelCart\Models\CartItem;
use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Exceptions\DiscountNotFoundException;
use Binafy\LaravelDiscount\Models\Discount;
use Binafy\LaravelDiscount\Support\DiscountResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CartDiscount
{
    public function __construct(protected DiscountManager $manager)
    {
    }

    /**
     * Apply the given discounts (models, a code string, or a list) to the
     * whole cart total, resolving stacking rules.
     *
     * @throws DiscountNotFoundException When a code string does not exist.
     */
    public function applyToCart(Cart $cart, Discount|iterable|string $discounts, Model|int|null $user = null): DiscountResult
    {
        return $this->manager->applyMany(
            $this->resolveDiscounts($discounts),
            $cart->calculatedPriceByQuantity(),
            $user ?? $cart->user_id
        );
    }

    /**
     * Apply the given discounts to a specific cart item's subtotal
     * (item price multiplied by quantity).
     *
     * @throws DiscountNotFoundException When a code string does not exist.
     */
    public function applyToItem(CartItem $item, Discount|iterable|string $discounts, Model|int|null $user = null): DiscountResult
    {
        $subtotal = (int) $item->quantity * (float) $item->itemable->getPrice();

        return $this->manager->applyMany(
            $this->resolveDiscounts($discounts),
            $subtotal,
            $user ?? $item->cart?->user_id
        );
    }

    /**
     * Walk the cart and apply, per item, the valid discounts attached to
     * its model through the HasDiscounts trait. Items whose model has no
     * discounts are counted at full price.
     */
    public function applyItemDiscounts(Cart $cart, Model|int|null $user = null): DiscountResult
    {
        $user ??= $cart->user_id;
        $originalTotal = 0.0;
        $discountTotal = 0.0;
        $applied = collect();

        foreach ($cart->items()->with('itemable')->get() as $item) {
            $subtotal = (int) $item->quantity * (float) $item->itemable->getPrice();
            $originalTotal += $subtotal;

            if (! method_exists($item->itemable, 'discounts')) {
                continue;
            }

            $result = $this->manager->applyMany(
                $item->itemable->discounts()->valid()->get(),
                $subtotal,
                $user
            );

            $discountTotal += $result->discountAmount;
            $applied = $applied->merge($result->discounts);
        }

        return new DiscountResult(
            $applied->unique('id')->values(),
            round($originalTotal, 2),
            round($discountTotal, 2)
        );
    }

    /**
     * Normalize the given discounts into a collection: a single model,
     * a code string (looked up in the database), or any iterable.
     *
     * @return Collection<int, Discount>
     *
     * @throws DiscountNotFoundException
     */
    protected function resolveDiscounts(Discount|iterable|string $discounts): Collection
    {
        if ($discounts instanceof Discount) {
            return collect([$discounts]);
        }

        if (is_string($discounts)) {
            $discount = Discount::query()->where('code', $discounts)->first();

            if (is_null($discount)) {
                throw DiscountNotFoundException::forCode($discounts);
            }

            return collect([$discount]);
        }

        return collect($discounts);
    }
}
