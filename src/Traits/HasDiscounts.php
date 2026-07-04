<?php

namespace Binafy\LaravelDiscount\Traits;

use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Models\Discount;
use Binafy\LaravelDiscount\Support\DiscountResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasDiscounts
{
    /**
     * The discounts attached to this model.
     */
    public function discounts(): MorphToMany
    {
        return $this->morphToMany(
            Discount::class,
            'discountable',
            config('laravel-discount.discountables.table', 'discountables')
        )->withTimestamps();
    }

    /**
     * The attached discounts that are currently applicable.
     */
    public function validDiscounts(): Collection
    {
        return $this->discounts()->valid()->get();
    }

    /**
     * Determine if the given discount (model or code) is attached to this model.
     */
    public function hasDiscount(Discount|string $discount): bool
    {
        return $this->discounts()
            ->when(
                is_string($discount),
                fn ($query) => $query->where('code', $discount),
                fn ($query) => $query->whereKey($discount->getKey()),
            )
            ->exists();
    }

    /**
     * Apply the attached valid discounts to the given amount, resolving stacking rules.
     */
    public function applyDiscounts(float $amount, Model|int|null $user = null): DiscountResult
    {
        return app(DiscountManager::class)->applyMany($this->validDiscounts(), $amount, $user);
    }
}
