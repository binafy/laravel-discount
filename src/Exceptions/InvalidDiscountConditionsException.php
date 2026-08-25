<?php

namespace Binafy\LaravelDiscount\Exceptions;

class InvalidDiscountConditionsException extends DiscountException
{
    protected $message = 'The discount conditions are missing or invalid for this discount type.';
}
