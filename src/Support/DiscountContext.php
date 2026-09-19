<?php

namespace Binafy\LaravelDiscount\Support;

use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DiscountContext
{
    /**
     * @param  array<string, mixed>  $payload  Anything a condition needs beyond
     *                                         the order basics, e.g. `items`.
     */
    public function __construct(
        public Discount $discount,
        public float $amount = 0,
        public Model|int|null $user = null,
        public ?string $sessionId = null,
        public int $quantity = 1,
        public array $payload = [],
    ) {}

    /**
     * The id of the user the discount is being applied for, or null for a guest.
     */
    public function userId(): int|string|null
    {
        return $this->user instanceof Model ? $this->user->getKey() : $this->user;
    }

    /**
     * The order's ISO 4217 currency code: the payload's `currency`, falling
     * back to the store currency in the config file, or null when neither says.
     */
    public function currency(): ?string
    {
        $currency = $this->payload['currency'] ?? config('laravel-discount.currency');

        return filled($currency) ? strtoupper($currency) : null;
    }

    /**
     * The items the order is made of, as passed in the payload. Conditions
     * that inspect the basket (categories, for example) read this.
     *
     * @return Collection<int, mixed>
     */
    public function items(): Collection
    {
        return collect($this->payload['items'] ?? []);
    }

    /**
     * Read an arbitrary payload value, with dot notation support.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }

    /**
     * Determine if the payload carries the given key.
     */
    public function has(string $key): bool
    {
        return ! is_null($this->get($key));
    }
}
