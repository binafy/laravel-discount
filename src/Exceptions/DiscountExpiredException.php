<?php

namespace Binafy\LaravelDiscount\Exceptions;

class DiscountExpiredException extends DiscountException
{
    protected $message = 'The discount has expired.';
}
