<?php

use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Events\DiscountApplied;
use Binafy\LaravelDiscount\Events\DiscountExpired;
use Binafy\LaravelDiscount\Events\DiscountRedeemed;
use Binafy\LaravelDiscount\Exceptions\DiscountUsageLimitReachedException;
use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = app(DiscountManager::class);

    Event::fake([DiscountApplied::class, DiscountExpired::class, DiscountRedeemed::class]);
});

test('applying a discount dispatches DiscountApplied', function () {
    $discount = Discount::query()->create([
        'type' => DiscountType::Percentage,
        'value' => 20,
    ]);

    $this->manager->apply($discount, 100);

    Event::assertDispatched(DiscountApplied::class, function (DiscountApplied $event) use ($discount) {
        return $event->result->discountAmount === 20.0
            && $event->result->discounts->first()->is($discount);
    });
});

test('applying many discounts dispatches DiscountApplied once with the applied set', function () {
    $a = Discount::query()->create(['type' => DiscountType::Percentage, 'value' => 10, 'is_stackable' => true]);
    $b = Discount::query()->create(['type' => DiscountType::Fixed, 'value' => 15, 'is_stackable' => true]);

    $this->manager->applyMany([$a, $b], 100);

    Event::assertDispatchedTimes(DiscountApplied::class, 1);
    Event::assertDispatched(DiscountApplied::class, fn (DiscountApplied $event) => $event->result->discounts->count() === 2);
});

test('DiscountApplied is not dispatched when nothing applies', function () {
    $expired = Discount::query()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'expires_at' => now()->subDay(),
    ]);

    $this->manager->applyMany([$expired], 100);

    Event::assertNotDispatched(DiscountApplied::class);
});

test('validating an expired discount dispatches DiscountExpired', function () {
    $discount = Discount::query()->create([
        'code' => 'OLD',
        'type' => DiscountType::Percentage,
        'value' => 10,
        'expires_at' => now()->subDay(),
    ]);

    expect($this->manager->isValid($discount))->toBeFalse();

    Event::assertDispatched(
        DiscountExpired::class,
        fn (DiscountExpired $event) => $event->discount->is($discount)
    );
});

test('redeeming a discount dispatches DiscountRedeemed', function () {
    $user = User::query()->create([
        'name' => 'Milwad',
        'email' => 'milwad.dev@gmail.com',
        'password' => bcrypt('password'),
    ]);

    $discount = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
    ]);

    $usage = $this->manager->redeem($discount, $user, 10);

    Event::assertDispatched(DiscountRedeemed::class, function (DiscountRedeemed $event) use ($discount, $usage) {
        return $event->discount->is($discount) && $event->usage->is($usage);
    });
});

test('DiscountRedeemed is not dispatched when redemption fails', function () {
    $user = User::query()->create([
        'name' => 'Milwad',
        'email' => 'milwad.dev@gmail.com',
        'password' => bcrypt('password'),
    ]);

    $discount = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
        'usage_limit' => 0,
    ]);

    try {
        $this->manager->redeem($discount, $user);
    } catch (DiscountUsageLimitReachedException) {
        // expected
    }

    Event::assertNotDispatched(DiscountRedeemed::class);
});
