<?php

use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Events\DiscountApplied;
use Binafy\LaravelDiscount\Exceptions\MinimumOrderValueException;
use Binafy\LaravelDiscount\Facades\LaravelDiscount;
use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = app(DiscountManager::class);
});

function makeFreeShipping(array $attributes = []): Discount
{
    return Discount::query()->create(array_merge([
        'name' => 'Free shipping',
        'code' => 'FREESHIP',
        'type' => DiscountType::FreeShipping,
    ], $attributes));
}

/*
|--------------------------------------------------------------------------
| Calculation
|--------------------------------------------------------------------------
*/

test('free shipping deducts nothing from the amount', function () {
    expect($this->manager->calculate(makeFreeShipping(), 500))->toBe(0.0);
});

test('free shipping deducts nothing even when a value is set', function () {
    expect($this->manager->calculate(makeFreeShipping(['value' => 50]), 500))->toBe(0.0);
});

test('a free shipping discount can be recognised on the model', function () {
    expect(makeFreeShipping()->isFreeShipping())->toBeTrue()
        ->and(Discount::query()->create(['type' => DiscountType::Fixed, 'value' => 10])->isFreeShipping())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Result flag
|--------------------------------------------------------------------------
*/

test('applying free shipping raises the flag on the result', function () {
    $result = LaravelDiscount::apply(makeFreeShipping(), 500);

    expect($result->hasFreeShipping())->toBeTrue()
        ->and($result->freeShipping)->toBeTrue()
        ->and($result->discountAmount)->toBe(0.0)
        ->and($result->payableAmount())->toBe(500.0);
});

test('other discount types leave the flag down', function () {
    $result = LaravelDiscount::apply(
        Discount::query()->create(['type' => DiscountType::Percentage, 'value' => 10]),
        500
    );

    expect($result->hasFreeShipping())->toBeFalse();
});

test('the result waives the shipping cost when shipping is free', function () {
    $result = LaravelDiscount::apply(makeFreeShipping(), 500);

    expect($result->payableShipping(35))->toBe(0.0)
        ->and($result->payableTotal(35))->toBe(500.0);
});

test('the result keeps the shipping cost when shipping is not free', function () {
    $result = LaravelDiscount::apply(
        Discount::query()->create(['type' => DiscountType::Fixed, 'value' => 100]),
        500
    );

    expect($result->payableShipping(35))->toBe(35.0)
        ->and($result->payableTotal(35))->toBe(435.0);
});

test('applying free shipping by code raises the flag', function () {
    makeFreeShipping(['code' => 'FREESHIP']);

    expect(LaravelDiscount::applyCode('FREESHIP', 500)->hasFreeShipping())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('free shipping still honours the minimum order value', function () {
    $this->manager->validate(makeFreeShipping(['min_order_value' => 1000]), 500);
})->throws(MinimumOrderValueException::class);

test('free shipping below the minimum order value is skipped when applying many', function () {
    $result = LaravelDiscount::applyMany([makeFreeShipping(['min_order_value' => 1000])], 500);

    expect($result->hasFreeShipping())->toBeFalse()
        ->and($result->discounts)->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| Stacking
|--------------------------------------------------------------------------
*/

test('free shipping applies alongside the winning monetary discount', function () {
    $shipping = makeFreeShipping();
    $percentage = Discount::query()->create([
        'type' => DiscountType::Percentage,
        'value' => 20,
    ]);

    $result = LaravelDiscount::applyMany([$shipping, $percentage], 500);

    expect($result->discountAmount)->toBe(100.0)
        ->and($result->hasFreeShipping())->toBeTrue()
        ->and($result->discounts->pluck('id')->all())->toBe([$percentage->id, $shipping->id]);
});

test('free shipping does not steal the win from a monetary discount', function () {
    $shipping = makeFreeShipping(['is_stackable' => true]);
    $fixed = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 80,
    ]);

    $result = LaravelDiscount::applyMany([$shipping, $fixed], 500);

    expect($result->discountAmount)->toBe(80.0)
        ->and($result->hasFreeShipping())->toBeTrue();
});

test('free shipping alone still produces a result', function () {
    $shipping = makeFreeShipping();

    $result = LaravelDiscount::applyMany([$shipping], 500);

    expect($result->discountAmount)->toBe(0.0)
        ->and($result->hasFreeShipping())->toBeTrue()
        ->and($result->discounts->pluck('id')->all())->toBe([$shipping->id]);
});

test('applying free shipping alone dispatches the applied event', function () {
    Event::fake([DiscountApplied::class]);

    LaravelDiscount::applyMany([makeFreeShipping()], 500);

    Event::assertDispatched(DiscountApplied::class, fn ($event) => $event->result->hasFreeShipping());
});

test('two free shipping discounts both apply', function () {
    $result = LaravelDiscount::applyMany([
        makeFreeShipping(['code' => 'SHIP-A']),
        makeFreeShipping(['code' => 'SHIP-B']),
    ], 500);

    expect($result->hasFreeShipping())->toBeTrue()
        ->and($result->discounts)->toHaveCount(2);
});
