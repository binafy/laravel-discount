<?php

use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Exceptions\DiscountUsageLimitReachedException;
use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = app(DiscountManager::class);
});

test('a guest can redeem a discount with a session id', function () {
    $discount = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
    ]);

    $usage = $this->manager->redeem($discount, sessionId: 'guest-session-abc');

    expect($usage->user_id)->toBeNull()
        ->and($usage->session_id)->toBe('guest-session-abc')
        ->and($discount->used_count)->toBe(1);
});

test('per-user limit is enforced per session for guests', function () {
    $discount = Discount::query()->create([
        'code' => 'GUEST-ONCE',
        'type' => DiscountType::Fixed,
        'value' => 10,
        'usage_limit_per_user' => 1,
    ]);

    $this->manager->redeem($discount, sessionId: 'session-one');

    // A different guest session can still redeem
    $this->manager->redeem($discount, sessionId: 'session-two');

    // The first session is now blocked, in validation and in redemption
    expect($this->manager->isValid($discount->refresh(), sessionId: 'session-one'))->toBeFalse()
        ->and(fn () => $this->manager->redeem($discount, sessionId: 'session-one'))
        ->toThrow(DiscountUsageLimitReachedException::class);
});

test('guest sessions and users are limited independently', function () {
    $user = User::query()->create([
        'name' => 'Milwad',
        'email' => 'milwad.dev@gmail.com',
        'password' => bcrypt('password'),
    ]);

    $discount = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
        'usage_limit_per_user' => 1,
    ]);

    $this->manager->redeem($discount, $user);
    $this->manager->redeem($discount, sessionId: 'guest-session');

    expect($this->manager->isValid($discount->refresh(), user: $user))->toBeFalse()
        ->and($this->manager->isValid($discount->refresh(), sessionId: 'guest-session'))->toBeFalse()
        ->and($this->manager->isValid($discount->refresh(), sessionId: 'another-guest'))->toBeTrue();
});

test('applyCode works for guests with a session id', function () {
    Discount::query()->create([
        'code' => 'GUEST10',
        'type' => DiscountType::Percentage,
        'value' => 10,
        'usage_limit_per_user' => 1,
    ]);

    $result = $this->manager->applyCode('GUEST10', 200, sessionId: 'guest-session');

    expect($result->discountAmount)->toBe(20.0);
});

test('total usage limit still applies to anonymous redemptions', function () {
    $discount = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
        'usage_limit' => 1,
    ]);

    // No user and no session at all — still counts toward the global limit
    $this->manager->redeem($discount);

    expect(fn () => $this->manager->redeem($discount))
        ->toThrow(DiscountUsageLimitReachedException::class);
});
