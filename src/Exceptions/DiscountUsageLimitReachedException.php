<?php

namespace Binafy\LaravelDiscount\Exceptions;

class DiscountUsageLimitReachedException extends DiscountException
{
    protected $message = 'The discount usage limit has been reached.';
}
