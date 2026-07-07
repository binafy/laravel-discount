<?php

namespace Binafy\LaravelDiscount\Events;

use Binafy\LaravelDiscount\Models\Discount;
use Binafy\LaravelDiscount\Models\DiscountUsage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscountRedeemed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Discount $discount,
        public DiscountUsage $usage,
    ) {}
}
