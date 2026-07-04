<?php

namespace Binafy\LaravelDiscount\Events;

use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscountExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(public Discount $discount)
    {
    }
}
