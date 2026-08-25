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
        public bool $freeShipping = false,
    ) {}

    /**
     * The amount left to pay after the discount, never below zero.
     */
    public function payableAmount(): float
    {
        return round(max($this->originalAmount - $this->discountAmount, 0), 2);
    }

    /**
     * Whether one of the applied discounts grants free shipping. Free
     * shipping deducts nothing from the amount, so the shipping cost is
     * yours to waive when this is true.
     */
    public function hasFreeShipping(): bool
    {
        return $this->freeShipping;
    }

    /**
     * The shipping cost left to pay, i.e. zero when shipping is free.
     */
    public function payableShipping(float $shippingCost): float
    {
        return $this->freeShipping ? 0.0 : round($shippingCost, 2);
    }

    /**
     * The grand total: the payable amount plus the shipping cost that
     * is still due.
     */
    public function payableTotal(float $shippingCost = 0): float
    {
        return round($this->payableAmount() + $this->payableShipping($shippingCost), 2);
    }
}
