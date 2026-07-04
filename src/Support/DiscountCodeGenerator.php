<?php

namespace Binafy\LaravelDiscount\Support;

use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Support\Collection;
use RuntimeException;

class DiscountCodeGenerator
{
    /**
     * Generate a single discount code, unique against the `discounts` table.
     */
    public function generate(?string $prefix = null): string
    {
        return $this->generateMany(1, $prefix)->first();
    }

    /**
     * Generate a batch of discount codes, unique against the `discounts`
     * table and within the batch itself.
     *
     * @return Collection<int, string>
     *
     * @throws RuntimeException When enough unique codes cannot be generated.
     */
    public function generateMany(int $count, ?string $prefix = null): Collection
    {
        $codes = collect();
        $attempts = 0;
        $maxAttempts = max($count * 100, 1000);

        while ($codes->count() < $count) {
            if (++$attempts > $maxAttempts) {
                throw new RuntimeException(
                    "Could not generate {$count} unique discount codes; the code space may be exhausted."
                );
            }

            $code = $this->randomCode($prefix);

            if (! $codes->contains($code) && ! $this->exists($code)) {
                $codes->push($code);
            }
        }

        return $codes;
    }

    /**
     * Build a random code from the configured length, characters, and prefix.
     */
    protected function randomCode(?string $prefix = null): string
    {
        $length = (int) config('laravel-discount.codes.length', 8);
        $characters = (string) config('laravel-discount.codes.characters', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');
        $separator = (string) config('laravel-discount.codes.separator', '-');
        $prefix ??= config('laravel-discount.codes.prefix');

        $code = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, $max)];
        }

        return $prefix ? $prefix . $separator . $code : $code;
    }

    /**
     * Determine if a discount with the given code already exists.
     */
    protected function exists(string $code): bool
    {
        return Discount::query()->where('code', $code)->exists();
    }
}
