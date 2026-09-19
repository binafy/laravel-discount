<?php

namespace Binafy\LaravelDiscount\Exceptions;

use Binafy\LaravelDiscount\Models\Discount;

class DiscountCurrencyMismatchException extends DiscountException
{
    protected $message = 'This discount is not available in the order\'s currency.';

    /**
     * Create the exception for an order in another currency than the
     * discount's, or one whose currency was not given at all.
     */
    public static function forCurrency(Discount $discount, ?string $orderCurrency): static
    {
        return static::for($discount, is_null($orderCurrency)
            ? "This discount is priced in {$discount->currency}, but the order's currency was not given."
            : "This discount is only available for orders in {$discount->currency}.");
    }
}
