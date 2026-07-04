<?php

namespace Binafy\LaravelDiscount\Exceptions;

use Exception;

/**
 * Base exception for every discount validation failure, so applications
 * can catch all discount errors with a single catch block.
 */
class DiscountException extends Exception
{
}
