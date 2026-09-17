<?php

namespace Binafy\LaravelDiscount\Conditions;

use Binafy\LaravelDiscount\Contracts\DiscountCondition;
use Binafy\LaravelDiscount\Exceptions\InvalidDiscountConditionsException;
use Binafy\LaravelDiscount\Support\DiscountContext;

class FirstPurchaseCondition implements DiscountCondition
{
    /**
     * The application's own way of counting a user's purchases, registered
     * with `countUsing()`. Takes precedence over the configured model.
     *
     * @var (callable(DiscountContext): int)|null
     */
    protected static $resolver = null;

    /**
     * @param  string|null  $model  The model that records a purchase, e.g. your
     *                              Order model. Defaults to the config file's.
     * @param  string|null  $column  The column holding the buyer's id.
     * @param  string|null  $countUsing  An invokable class that counts the user's
     *                                   purchases, for rules that need their own.
     */
    public function __construct(
        protected ?string $model = null,
        protected ?string $column = null,
        protected ?string $countUsing = null,
    ) {}

    /**
     * Count a user's purchases with the application's own logic, for the many
     * shops where a purchase is more than a row: only paid orders count, or
     * the number lives in another service entirely.
     *
     * The callback receives the `DiscountContext` and returns how many
     * purchases the user has made. Pass null to forget it again.
     *
     * @param  (callable(DiscountContext): int)|null  $resolver
     */
    public static function countUsing(?callable $resolver): void
    {
        static::$resolver = $resolver;
    }

    /**
     * Stored as `['type' => 'first_purchase']`, optionally overriding the
     * model, column, or counter configured in
     * `laravel-discount.conditions.first_purchase`.
     */
    public static function fromArray(array $config): static
    {
        return new static(
            $config['model'] ?? null,
            $config['column'] ?? null,
            $config['count_using'] ?? null,
        );
    }

    /**
     * The user must have no purchase on record yet.
     *
     * @throws InvalidDiscountConditionsException When there is no way to count purchases.
     */
    public function passes(DiscountContext $context): bool
    {
        return $this->purchaseCount($context) === 0;
    }

    /**
     * How many purchases the user has made, by whichever means the
     * application has given us: an invokable named on the rule or in the
     * config file, a callback registered with `countUsing()`, or a query
     * against the configured purchase model.
     *
     * @throws InvalidDiscountConditionsException
     */
    protected function purchaseCount(DiscountContext $context): int
    {
        if ($counter = $this->counter($context)) {
            return (int) $counter($context);
        }

        // Guests never pass the model path: with no user to look up, a first
        // purchase cannot be proven. A callback gets to decide for itself,
        // since it may recognise a guest by e-mail or session.
        if (is_null($userId = $context->userId())) {
            return 1;
        }

        $model = $this->model ?? config('laravel-discount.conditions.first_purchase.model');

        if (! is_string($model) || ! class_exists($model)) {
            throw InvalidDiscountConditionsException::for(
                $context->discount,
                'The first purchase condition needs a way to count purchases. Set `conditions.first_purchase.model` in the laravel-discount config, or register a callback with FirstPurchaseCondition::countUsing().'
            );
        }

        $column = $this->column
            ?? config('laravel-discount.conditions.first_purchase.column', 'user_id');

        return $model::query()->where($column, $userId)->count();
    }

    /**
     * The application's purchase counter, if it gave us one.
     *
     * @return (callable(DiscountContext): int)|null
     *
     * @throws InvalidDiscountConditionsException
     */
    protected function counter(DiscountContext $context): ?callable
    {
        $class = $this->countUsing ?? config('laravel-discount.conditions.first_purchase.count_using');

        if (is_null($class)) {
            return static::$resolver;
        }

        if (! is_string($class) || ! class_exists($class) || ! is_callable($counter = app($class))) {
            throw InvalidDiscountConditionsException::for(
                $context->discount,
                'The first purchase condition\'s `count_using` must be an invokable class that returns the number of purchases a user has made.'
            );
        }

        return $counter;
    }

    public function message(): string
    {
        return 'This discount is only available on your first purchase.';
    }
}
