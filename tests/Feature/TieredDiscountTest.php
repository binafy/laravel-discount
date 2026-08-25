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

    $this->tiers = [
        ['min' => 1_000_000, 'value' => 5],
        ['min' => 5_000_000, 'value' => 10],
    ];
});

function makeTiered(array $tiers, array $attributes = []): Discount
{
    return Discount::query()->create(array_merge([
        'name' => 'Spend more, save more',
        'type' => DiscountType::Tiered,
        'conditions' => ['tiers' => $tiers],
    ], $attributes));
}

/*
|--------------------------------------------------------------------------
| Calculation
|--------------------------------------------------------------------------
*/

test('an order below every tier gets nothing', function () {
    expect($this->manager->calculate(makeTiered($this->tiers), 999_999))->toBe(0.0);
});

test('an order reaching the first tier gets its percentage', function () {
    expect($this->manager->calculate(makeTiered($this->tiers), 2_000_000))->toBe(100_000.0);
});

test('an order reaching the top tier gets the highest percentage', function () {
    expect($this->manager->calculate(makeTiered($this->tiers), 6_000_000))->toBe(600_000.0);
});

test('a tier applies exactly at its minimum', function () {
    $discount = makeTiered($this->tiers);

    expect($this->manager->calculate($discount, 1_000_000))->toBe(50_000.0)
        ->and($this->manager->calculate($discount, 5_000_000))->toBe(500_000.0);
});

test('the highest matching tier wins regardless of the order they are stored in', function () {
    $discount = makeTiered([
        ['min' => 5_000_000, 'value' => 10],
        ['min' => 1_000_000, 'value' => 5],
        ['min' => 3_000_000, 'value' => 7],
    ]);

    expect($this->manager->calculate($discount, 6_000_000))->toBe(600_000.0)
        ->and($this->manager->calculate($discount, 4_000_000))->toBe(280_000.0);
});

test('a tier can deduct a fixed amount instead of a percentage', function () {
    $discount = makeTiered([
        ['min' => 1_000_000, 'value' => 50_000, 'type' => 'fixed'],
        ['min' => 5_000_000, 'value' => 400_000, 'type' => 'fixed'],
    ]);

    expect($this->manager->calculate($discount, 2_000_000))->toBe(50_000.0)
        ->and($this->manager->calculate($discount, 5_000_000))->toBe(400_000.0);
});

test('a tiered discount respects the max discount amount cap', function () {
    $discount = makeTiered($this->tiers, ['max_discount_amount' => 250_000]);

    expect($this->manager->calculate($discount, 6_000_000))->toBe(250_000.0);
});

test('the matching tier can be inspected', function () {
    $discount = makeTiered($this->tiers);

    expect($this->manager->matchingTier($discount, 6_000_000))->toBe(['min' => 5_000_000, 'value' => 10])
        ->and($this->manager->matchingTier($discount, 2_000_000))->toBe(['min' => 1_000_000, 'value' => 5])
        ->and($this->manager->matchingTier($discount, 10))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('a tiered discount without tiers is invalid', function () {
    $this->manager->validate(Discount::query()->create(['type' => DiscountType::Tiered]));
})->throws(InvalidDiscountConditionsException::class);

test('a tiered discount with an empty ladder is invalid', function () {
    $this->manager->validate(makeTiered([]));
})->throws(InvalidDiscountConditionsException::class);

test('a tier missing its value is invalid', function () {
    expect($this->manager->isValid(makeTiered([['min' => 1_000_000]])))->toBeFalse();
});

test('a tier with a non numeric minimum is invalid', function () {
    expect($this->manager->isValid(makeTiered([['min' => 'a lot', 'value' => 5]])))->toBeFalse();
});

test('a well formed tiered discount is valid', function () {
    expect($this->manager->isValid(makeTiered($this->tiers)))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Applying
|--------------------------------------------------------------------------
*/

test('applying a tiered discount uses the order amount', function () {
    $discount = makeTiered($this->tiers);

    expect(LaravelDiscount::apply($discount, 6_000_000)->payableAmount())->toBe(5_400_000.0)
        ->and(LaravelDiscount::apply($discount, 2_000_000)->payableAmount())->toBe(1_900_000.0);
});

test('a tiered discount competes with other discounts on the amount it saves', function () {
    $tiered = makeTiered($this->tiers);
    $fixed = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 200_000,
    ]);

    $result = LaravelDiscount::applyMany([$tiered, $fixed], 6_000_000);

    expect($result->discountAmount)->toBe(600_000.0)
        ->and($result->discounts->pluck('id')->all())->toBe([$tiered->id]);
});
