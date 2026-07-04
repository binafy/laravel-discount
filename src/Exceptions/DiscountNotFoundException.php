<?php

namespace Binafy\LaravelDiscount\Exceptions;

class DiscountNotFoundException extends DiscountException
{
    protected $message = 'The discount code was not found.';

    public static function forCode(string $code): static
    {
        return new static("The discount code [{$code}] was not found.");
    }
}
