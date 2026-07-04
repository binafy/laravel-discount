<?php

namespace Binafy\LaravelDiscount\Exceptions;

class DiscountNotStartedException extends DiscountException
{
    protected $message = 'The discount has not started yet.';
}
