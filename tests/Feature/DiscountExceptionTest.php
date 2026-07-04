<?php

use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Exceptions\DiscountException;
use Binafy\LaravelDiscount\Exceptions\DiscountExpiredException;
use Binafy\LaravelDiscount\Exceptions\DiscountNotActiveException;
use Binafy\LaravelDiscount\Exceptions\DiscountNotStartedException;
use Binafy\LaravelDiscount\Exceptions\DiscountUsageLimitReachedException;
use Binafy\LaravelDiscount\Exceptions\MinimumOrderValueException;
use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('every discount exception extends the base exception', function () {
    expect(is_subclass_of(DiscountNotActiveException::class, DiscountException::class))->toBeTrue()
        ->and(is_subclass_of(DiscountNotStartedException::class, DiscountException::class))->toBeTrue()
        ->and(is_subclass_of(DiscountExpiredException::class, DiscountException::class))->toBeTrue()
        ->and(is_subclass_of(DiscountUsageLimitReachedException::class, DiscountException::class))->toBeTrue()
        ->and(is_subclass_of(MinimumOrderValueException::class, DiscountException::class))->toBeTrue();
});

test('exceptions carry the discount that failed', function () {
    $discount = Discount::query()->create([
        'code' => 'EXPIRED',
        'type' => DiscountType::Fixed,
        'value' => 10,
        'expires_at' => now()->subDay(),
    ]);

    try {
        app(DiscountManager::class)->validate($discount);
        $this->fail('Expected a DiscountExpiredException to be thrown.');
    } catch (DiscountExpiredException $exception) {
        expect($exception->getDiscount())->not->toBeNull()
            ->and($exception->getDiscount()->is($discount))->toBeTrue()
            ->and($exception->getMessage())->toBe('The discount has expired.');
    }
});

test('a custom message does not replace the default when omitted', function () {
    $discount = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
    ]);

    $default = DiscountExpiredException::for($discount);
    $custom = DiscountExpiredException::for($discount, 'This code is no longer valid.');

    expect($default->getMessage())->toBe('The discount has expired.')
        ->and($custom->getMessage())->toBe('This code is no longer valid.');
});

test('each failure case can be handled separately via its own exception type', function () {
    $manager = app(DiscountManager::class);

    $cases = [
        [Discount::query()->create(['type' => DiscountType::Fixed, 'value' => 10, 'is_active' => false]), DiscountNotActiveException::class],
        [Discount::query()->create(['type' => DiscountType::Fixed, 'value' => 10, 'starts_at' => now()->addDay()]), DiscountNotStartedException::class],
        [Discount::query()->create(['type' => DiscountType::Fixed, 'value' => 10, 'expires_at' => now()->subDay()]), DiscountExpiredException::class],
        [Discount::query()->create(['type' => DiscountType::Fixed, 'value' => 10, 'usage_limit' => 1, 'used_count' => 1]), DiscountUsageLimitReachedException::class],
        [Discount::query()->create(['type' => DiscountType::Fixed, 'value' => 10, 'min_order_value' => 100]), MinimumOrderValueException::class],
    ];

    foreach ($cases as [$discount, $expected]) {
        try {
            $manager->validate($discount);
            $this->fail("Expected {$expected} to be thrown.");
        } catch (DiscountException $exception) {
            expect($exception)->toBeInstanceOf($expected);
        }
    }
});
