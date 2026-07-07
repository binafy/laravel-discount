<?php

use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = app(DiscountManager::class);
});

test('a percentage discount is capped at the max discount amount', function () {
    $discount = Discount::query()->create([
        'type' => DiscountType::Percentage,
        'value' => 20,
        'max_discount_amount' => 100,
    ]);

    // 20% of 1000 = 200, capped at 100
    expect($this->manager->calculate($discount, 1000))->toBe(100.0);
});

test('the cap does not change discounts below it', function () {
    $discount = Discount::query()->create([
        'type' => DiscountType::Percentage,
        'value' => 20,
        'max_discount_amount' => 100,
    ]);

    // 20% of 300 = 60, below the cap
    expect($this->manager->calculate($discount, 300))->toBe(60.0);
});

test('a discount without a cap behaves as before', function () {
    $discount = Discount::query()->create([
        'type' => DiscountType::Percentage,
        'value' => 20,
    ]);

    expect($this->manager->calculate($discount, 1000))->toBe(200.0);
});

test('the cap also applies to fixed discounts', function () {
    $discount = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 150,
        'max_discount_amount' => 100,
    ]);

    expect($this->manager->calculate($discount, 1000))->toBe(100.0);
});

test('capped discounts flow through apply and stacking', function () {
    $capped = Discount::query()->create([
        'type' => DiscountType::Percentage,
        'value' => 50,
        'max_discount_amount' => 100,
        'is_stackable' => true,
    ]);

    $fixed = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 30,
        'is_stackable' => true,
    ]);

    $result = $this->manager->apply($capped, 1000);
    expect($result->discountAmount)->toBe(100.0)
        ->and($result->payableAmount())->toBe(900.0);

    // Stacked: 100 (capped) + 30 (fixed) = 130
    $stacked = $this->manager->applyMany([$capped, $fixed], 1000);
    expect($stacked->discountAmount)->toBe(130.0);
});
