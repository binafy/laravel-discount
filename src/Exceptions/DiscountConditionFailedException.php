<?php

namespace Binafy\LaravelDiscount\Exceptions;

use Binafy\LaravelDiscount\Contracts\DiscountCondition;
use Binafy\LaravelDiscount\Models\Discount;

class DiscountConditionFailedException extends DiscountException
{
    protected $message = 'The order does not meet the conditions of this discount.';

    protected ?DiscountCondition $condition = null;

    /**
     * Create the exception for the condition that was not met, so the
     * application can show its reason to the customer.
     */
    public static function forCondition(Discount $discount, DiscountCondition $condition): static
    {
        $exception = static::for($discount, $condition->message());
        $exception->condition = $condition;

        return $exception;
    }

    /**
     * The condition that was not met, when a single one can be named.
     */
    public function getCondition(): ?DiscountCondition
    {
        return $this->condition;
    }
}
