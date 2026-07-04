<?php

namespace Binafy\LaravelDiscount\Exceptions;

use Binafy\LaravelDiscount\Models\Discount;
use Exception;

class DiscountException extends Exception
{
    protected ?Discount $discount = null;

    /**
     * Create the exception carrying the discount that failed, so the
     * application can inspect it inside the catch block.
     */
    public static function for(Discount $discount, ?string $message = null): static
    {
        $exception = is_null($message) ? new static : new static($message);

        return $exception->setDiscount($discount);
    }

    public function setDiscount(Discount $discount): static
    {
        $this->discount = $discount;

        return $this;
    }

    /**
     * The discount that failed validation, when available.
     */
    public function getDiscount(): ?Discount
    {
        return $this->discount;
    }
}
