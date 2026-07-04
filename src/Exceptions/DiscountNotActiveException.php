<?php

namespace Binafy\LaravelDiscount\Exceptions;

class DiscountNotActiveException extends DiscountException
{
    protected $message = 'The discount is not active.';
}
