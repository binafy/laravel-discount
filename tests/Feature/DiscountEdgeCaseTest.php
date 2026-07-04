<?php

use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Exceptions\DiscountNotActiveException;
use Binafy\LaravelDiscount\Exceptions\DiscountNotFoundException;
use Binafy\LaravelDiscount\Exceptions\DiscountUsageLimitReachedException;
use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = app(DiscountManager::class);
});

test('applyMany with no discounts returns a zero result', function () {
    $result = $this->manager->applyMany([], 100);

    expect($result->discounts)->toBeEmpty()
        ->and($result->discountAmount)->toBe(0.0)
        ->and($result->payableAmount())->toBe(100.0);
});

test('an order exactly at the minimum order value passes', function () {
    $discount = Discount::query()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'min_order_value' => 100,
    ]);

    expect($this->manager->isValid($discount, 100))->toBeTrue()
        ->and($this->manager->isValid($discount, 99.99))->toBeFalse();
});

test('a hundred percent discount makes the order free', function () {
    $discount = Discount::query()->create([
        'type' => DiscountType::Percentage,
        'value' => 100,
    ]);

    $result = $this->manager->apply($discount, 250);

    expect($result->discountAmount)->toBe(250.0)
        ->and($result->payableAmount())->toBe(0.0);
});

test('fractional percentages are rounded to two decimals', function () {
    $discount = Discount::query()->create([
        'type' => DiscountType::Percentage,
        'value' => 12.5,
    ]);

    // 12.5% of 99.99 = 12.49875 → 12.5
    expect($this->manager->calculate($discount, 99.99))->toBe(12.5);
});

test('apply throws instead of returning a result for an invalid discount', function () {
    $discount = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
        'is_active' => false,
    ]);

    $this->manager->apply($discount, 100);
})->throws(DiscountNotActiveException::class);

test('applyCode throws for an unknown code', function () {
    $this->manager->applyCode('NOPE', 100);
})->throws(DiscountNotFoundException::class);

test('redeem accepts a plain user id', function () {
    $user = User::query()->create([
        'name' => 'Milwad',
        'email' => 'milwad.dev@gmail.com',
        'password' => bcrypt('password'),
    ]);

    $discount = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
    ]);

    $usage = $this->manager->redeem($discount, $user->id);

    expect($usage->user_id)->toBe($user->id);
});

test('a discount with one remaining usage is valid until redeemed', function () {
    $user = User::query()->create([
        'name' => 'Milwad',
        'email' => 'milwad.dev@gmail.com',
        'password' => bcrypt('password'),
    ]);

    $discount = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
        'usage_limit' => 2,
        'used_count' => 1,
    ]);

    expect($this->manager->isValid($discount))->toBeTrue();

    $this->manager->redeem($discount, $user);

    expect($this->manager->isValid($discount->refresh()))->toBeFalse();
});

test('full lifecycle: generate, create, apply, redeem, exhaust', function () {
    $user = User::query()->create([
        'name' => 'Milwad',
        'email' => 'milwad.dev@gmail.com',
        'password' => bcrypt('password'),
    ]);

    // Generate a unique code and create a discount with it
    $code = $this->manager->generateCode('LAUNCH');

    $discount = Discount::query()->create([
        'name' => 'Launch offer',
        'code' => $code,
        'type' => DiscountType::Percentage,
        'value' => 20,
        'min_order_value' => 50,
        'usage_limit' => 1,
        'expires_at' => now()->addWeek(),
    ]);

    // Apply it to an order by code
    $result = $this->manager->applyCode($code, 200, $user);

    expect($result->discountAmount)->toBe(40.0)
        ->and($result->payableAmount())->toBe(160.0);

    // Record the redemption
    $this->manager->redeem($discount, $user, $result->discountAmount);

    expect($discount->used_count)->toBe(1)
        ->and($discount->usages()->count())->toBe(1);

    // The single usage is spent: applying again fails
    expect(fn () => $this->manager->applyCode($code, 200, $user))
        ->toThrow(DiscountUsageLimitReachedException::class);
});
