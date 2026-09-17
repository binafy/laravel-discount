<?php

namespace Binafy\LaravelDiscount\Conditions;

use Binafy\LaravelDiscount\Contracts\DiscountCondition;
use Binafy\LaravelDiscount\Support\DiscountContext;

class MinimumItemCountCondition implements DiscountCondition
{
    public function __construct(protected int $count = 1) {}

    /**
     * Stored as `['type' => 'minimum_item_count', 'count' => 3]`.
     */
    public static function fromArray(array $config): static
    {
        return new static((int) ($config['count'] ?? 1));
    }

    /**
     * The order must carry at least the configured number of items, which
     * is the quantity handed to the manager.
     */
    public function passes(DiscountContext $context): bool
    {
        return $context->quantity >= $this->count;
    }

    public function message(): string
    {
        return "This discount requires at least {$this->count} items.";
    }
}
