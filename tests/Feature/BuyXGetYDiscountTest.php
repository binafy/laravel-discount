<?php

use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Exceptions\InvalidDiscountConditionsException;
use Binafy\LaravelDiscount\Facades\LaravelDiscount;
use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = app(DiscountManager::class);
});

function makeBuyXGetY(array $conditions = ['buy' => 2, 'get' => 1], array $attributes = []): Discount
{
    return Discount::query()->create(array_merge([
        'name' => 'Buy 2 get 1 free',
        'type' => DiscountType::BuyXGetY,
        'conditions' => $conditions,
    ], $attributes));
}

/*
|--------------------------------------------------------------------------
| Calculation
|--------------------------------------------------------------------------
*/

test('buy two get one free discounts the third item', function () {
    $discount = makeBuyXGetY();

    // 3 items at 100 each: one comes free.
    expect($this->manager->calculate($discount, 300, 3))->toBe(100.0);
});

test('buy x get y needs a full set before it applies', function () {
    $discount = makeBuyXGetY();

    expect($this->manager->calculate($discount, 100, 1))->toBe(0.0)
        ->and($this->manager->calculate($discount, 200, 2))->toBe(0.0);
});

test('buy x get y repeats for every full set', function () {
    $discount = makeBuyXGetY();

    // 6 items at 50 each: two full sets, so two items come free.
    expect($this->manager->calculate($discount, 300, 6))->toBe(100.0);
});

test('buy x get y ignores the leftover items of a partial set', function () {
    $discount = makeBuyXGetY();

    // 7 items at 50 each: still only two full sets.
    expect($this->manager->calculate($discount, 350, 7))->toBe(100.0);
});

test('buy x get y can free more than one item per set', function () {
    $discount = makeBuyXGetY(['buy' => 3, 'get' => 2]);

    // 5 items at 20 each: two of them come free.
    expect($this->manager->calculate($discount, 100, 5))->toBe(40.0);
});

test('buy x get y can discount the free items partially', function () {
    $discount = makeBuyXGetY(['buy' => 2, 'get' => 1, 'get_discount_percentage' => 50]);

    // 3 items at 100 each: the third is half price.
    expect($this->manager->calculate($discount, 300, 3))->toBe(50.0);
});

test('buy x get y caps the number of free items', function () {
    $discount = makeBuyXGetY(['buy' => 2, 'get' => 1, 'max_free_items' => 2]);

    // 9 items at 100 each: three sets earn three free items, capped at two.
    expect($this->manager->calculate($discount, 900, 9))->toBe(200.0);
});

test('buy x get y respects the max discount amount cap', function () {
    $discount = makeBuyXGetY(['buy' => 2, 'get' => 1], ['max_discount_amount' => 60]);

    expect($this->manager->calculate($discount, 300, 3))->toBe(60.0);
});

test('buy x get y prices the free item at the average unit price', function () {
    $discount = makeBuyXGetY();

    // A mixed basket of 3 items totalling 330 averages 110 per item.
    expect($this->manager->calculate($discount, 330, 3))->toBe(110.0);
});

test('buy x get y defaults to a quantity of one', function () {
    expect($this->manager->calculate(makeBuyXGetY(), 300))->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('buy x get y without conditions is invalid', function () {
    $this->manager->validate(Discount::query()->create([
        'type' => DiscountType::BuyXGetY,
    ]));
})->throws(InvalidDiscountConditionsException::class);

test('buy x get y with an incomplete deal is invalid', function () {
    $this->manager->validate(makeBuyXGetY(['buy' => 2]));
})->throws(InvalidDiscountConditionsException::class);

test('buy x get y with a zero get is invalid', function () {
    expect($this->manager->isValid(makeBuyXGetY(['buy' => 2, 'get' => 0])))->toBeFalse();
});

test('a well formed buy x get y is valid', function () {
    expect($this->manager->isValid(makeBuyXGetY()))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Applying
|--------------------------------------------------------------------------
*/

test('applying a buy x get y discount passes the quantity through', function () {
    $result = LaravelDiscount::apply(makeBuyXGetY(), 300, quantity: 3);

    expect($result->discountAmount)->toBe(100.0)
        ->and($result->payableAmount())->toBe(200.0)
        ->and($result->hasFreeShipping())->toBeFalse();
});

test('applying many discounts passes the quantity through', function () {
    $result = LaravelDiscount::applyMany(
        [makeBuyXGetY(['buy' => 2, 'get' => 1], ['is_stackable' => true])],
        300,
        quantity: 3
    );

    expect($result->discountAmount)->toBe(100.0);
});

test('a malformed buy x get y is skipped when applying many', function () {
    $result = LaravelDiscount::applyMany([makeBuyXGetY(['buy' => 2])], 300, quantity: 3);

    expect($result->discounts)->toBeEmpty()
        ->and($result->discountAmount)->toBe(0.0);
});
