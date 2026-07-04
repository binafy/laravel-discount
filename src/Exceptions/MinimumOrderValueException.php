<?php

namespace Binafy\LaravelDiscount\Exceptions;

class MinimumOrderValueException extends DiscountException
{
    protected $message = 'The order total does not reach the minimum required for this discount.';
}
