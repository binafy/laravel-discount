<?php

namespace Binafy\LaravelDiscount\Support;

use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Support\Collection;

class DiscountResult
{
    public function __construct(
        public Collection $discounts,
        public float $originalAmount,
        public float $discountAmount,
    ) {}

    /**
     * The amount left to pay after the discount, never below zero.
     */
    public function payableAmount(): float
    {
        return round(max($this->originalAmount - $this->discountAmount, 0), 2);
    }
}
