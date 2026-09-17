<?php

namespace Binafy\LaravelDiscount\Contracts;

use Binafy\LaravelDiscount\Support\DiscountContext;

interface DiscountCondition
{
    /**
     * Build the condition from one entry of the discount's `conditions.rules`
     * array, so a condition stored as JSON can be rebuilt at runtime.
     *
     * @param  array<string, mixed>  $config  The rule entry, minus its `type` key.
     */
    public static function fromArray(array $config): static;

    /**
     * Determine if the condition is met for the order being discounted.
     */
    public function passes(DiscountContext $context): bool;

    /**
     * The reason shown to the customer when the condition is not met.
     */
    public function message(): string;
}
