<?php

namespace Binafy\LaravelDiscount\Conditions;

use Binafy\LaravelDiscount\Contracts\DiscountCondition;
use Binafy\LaravelDiscount\Exceptions\InvalidDiscountConditionsException;
use Binafy\LaravelDiscount\Support\DiscountContext;

class FirstPurchaseCondition implements DiscountCondition
{
    /**
     * @param  string|null  $model  The model that records a purchase, e.g. your
     *                              Order model. Defaults to the config file's.
     * @param  string|null  $column  The column holding the buyer's id.
     */
    public function __construct(
        protected ?string $model = null,
        protected ?string $column = null,
    ) {}

    /**
     * Stored as `['type' => 'first_purchase']`, optionally overriding the
     * model and column configured in `laravel-discount.conditions.first_purchase`.
     */
    public static function fromArray(array $config): static
    {
        return new static($config['model'] ?? null, $config['column'] ?? null);
    }

    /**
     * The user must have no purchase on record yet. Guests never pass: with
     * no user to look up, a first purchase cannot be proven.
     *
     * @throws InvalidDiscountConditionsException When no purchase model is configured.
     */
    public function passes(DiscountContext $context): bool
    {
        if (is_null($userId = $context->userId())) {
            return false;
        }

        $model = $this->model ?? config('laravel-discount.conditions.first_purchase.model');

        if (! is_string($model) || ! class_exists($model)) {
            throw InvalidDiscountConditionsException::for(
                $context->discount,
                'The first purchase condition needs a purchase model. Set `conditions.first_purchase.model` in the laravel-discount config.'
            );
        }

        $column = $this->column
            ?? config('laravel-discount.conditions.first_purchase.column', 'user_id');

        return ! $model::query()->where($column, $userId)->exists();
    }

    public function message(): string
    {
        return 'This discount is only available on your first purchase.';
    }
}
