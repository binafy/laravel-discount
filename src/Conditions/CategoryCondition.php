<?php

namespace Binafy\LaravelDiscount\Conditions;

use Binafy\LaravelDiscount\Contracts\DiscountCondition;
use Binafy\LaravelDiscount\Support\DiscountContext;

class CategoryCondition implements DiscountCondition
{
    /**
     * @param  array<int, mixed>  $categories  The categories the discount is limited to.
     * @param  string  $attribute  Where to read an item's category, in dot notation.
     * @param  string  $match  `any` when one matching item is enough, `all` when
     *                         every item must match.
     */
    public function __construct(
        protected array $categories = [],
        protected ?string $attribute = null,
        protected string $match = 'any',
    ) {}

    /**
     * Stored as `['type' => 'category', 'categories' => [1, 5], 'match' => 'any']`.
     */
    public static function fromArray(array $config): static
    {
        return new static(
            array_values((array) ($config['categories'] ?? [])),
            $config['attribute'] ?? null,
            ($config['match'] ?? 'any') === 'all' ? 'all' : 'any',
        );
    }

    /**
     * The order's items must belong to one of the configured categories.
     * An order with no items never matches, since nothing can be checked.
     */
    public function passes(DiscountContext $context): bool
    {
        $items = $context->items();

        if ($items->isEmpty() || $this->categories === []) {
            return false;
        }

        $matching = $items->filter(
            fn ($item) => in_array(data_get($item, $this->attribute()), $this->categories)
        );

        return $this->match === 'all'
            ? $matching->count() === $items->count()
            : $matching->isNotEmpty();
    }

    /**
     * Where an item's category is read from, falling back to the config file.
     */
    protected function attribute(): string
    {
        return $this->attribute
            ?? config('laravel-discount.conditions.category.attribute', 'category_id');
    }

    public function message(): string
    {
        return $this->match === 'all'
            ? 'Every item in the order must belong to a category this discount covers.'
            : 'The order does not contain an item from a category this discount covers.';
    }
}
