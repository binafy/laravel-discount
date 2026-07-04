<?php

namespace Binafy\LaravelDiscount\Traits;

use Binafy\LaravelDiscount\Models\Discount;
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
}
