<?php

namespace Binafy\LaravelDiscount\Support;

use Binafy\LaravelDiscount\Contracts\DiscountCondition;
use Binafy\LaravelDiscount\Exceptions\InvalidDiscountConditionsException;
use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class ConditionFactory
{
    /**
     * Extra aliases registered at runtime, on top of the config file's.
     *
     * @var array<string, class-string<DiscountCondition>>
     */
    protected static array $aliases = [];

    /**
     * Register a condition under a short alias, so it can be stored as
     * `['type' => 'your_alias', ...]` instead of a full class name.
     *
     * @param  class-string<DiscountCondition>  $condition
     */
    public static function register(string $alias, string $condition): void
    {
        static::$aliases[$alias] = $condition;
    }

    /**
     * Forget the aliases registered at runtime.
     */
    public static function flushAliases(): void
    {
        static::$aliases = [];
    }

    /**
     * Every known alias, the config file's plus the runtime ones.
     *
     * @return array<string, class-string<DiscountCondition>>
     */
    public static function aliases(): array
    {
        return array_merge(
            (array) config('laravel-discount.conditions.aliases', []),
            static::$aliases
        );
    }

    /**
     * Build the conditions stored in the discount's `conditions.rules` array.
     *
     * @return Collection<int, DiscountCondition>
     *
     * @throws InvalidDiscountConditionsException
     */
    public function make(Discount $discount): Collection
    {
        return collect(($discount->conditions ?? [])['rules'] ?? [])
            ->map(fn ($rule) => $this->makeOne($discount, $rule));
    }

    /**
     * Build a single condition from its stored rule.
     *
     * @throws InvalidDiscountConditionsException
     */
    protected function makeOne(Discount $discount, mixed $rule): DiscountCondition
    {
        if ($rule instanceof DiscountCondition) {
            return $rule;
        }

        if (! is_array($rule) || ! is_string($type = $rule['type'] ?? null)) {
            throw InvalidDiscountConditionsException::for(
                $discount,
                'Every entry of `conditions.rules` must be an array with a `type` key.'
            );
        }

        $class = static::aliases()[$type] ?? $type;

        if (! is_subclass_of($class, DiscountCondition::class)) {
            throw InvalidDiscountConditionsException::for(
                $discount,
                "The discount condition [{$type}] is not registered and is not a DiscountCondition class."
            );
        }

        return $class::fromArray(Arr::except($rule, 'type'));
    }
}
