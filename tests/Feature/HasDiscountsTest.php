<?php

use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Models\Product;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (! Schema::hasTable('products')) {
        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    $this->product = Product::query()->create(['name' => 'Laptop', 'price' => 1000]);
});

test('validDiscounts only returns currently applicable discounts', function () {
    $valid = Discount::query()->create([
        'code' => 'VALID',
        'type' => DiscountType::Percentage,
        'value' => 10,
    ]);

    $expired = Discount::query()->create([
        'code' => 'EXPIRED',
        'type' => DiscountType::Percentage,
        'value' => 20,
        'expires_at' => now()->subDay(),
    ]);

    $this->product->discounts()->attach([$valid->id, $expired->id]);

    expect($this->product->discounts)->toHaveCount(2)
        ->and($this->product->validDiscounts())->toHaveCount(1)
        ->and($this->product->validDiscounts()->first()->is($valid))->toBeTrue();
});

test('hasDiscount checks by model and by code', function () {
    $attached = Discount::query()->create([
        'code' => 'TECH10',
        'type' => DiscountType::Percentage,
        'value' => 10,
    ]);

    $other = Discount::query()->create([
        'code' => 'OTHER',
        'type' => DiscountType::Percentage,
        'value' => 10,
    ]);

    $this->product->discounts()->attach($attached);

    expect($this->product->hasDiscount($attached))->toBeTrue()
        ->and($this->product->hasDiscount('TECH10'))->toBeTrue()
        ->and($this->product->hasDiscount($other))->toBeFalse()
        ->and($this->product->hasDiscount('OTHER'))->toBeFalse();
});

test('applyDiscounts applies attached valid discounts with stacking rules', function () {
    $percentage = Discount::query()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'is_stackable' => true,
    ]);

    $fixed = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 50,
        'is_stackable' => true,
    ]);

    $expired = Discount::query()->create([
        'type' => DiscountType::Percentage,
        'value' => 90,
        'expires_at' => now()->subDay(),
    ]);

    $this->product->discounts()->attach([$percentage->id, $fixed->id, $expired->id]);

    $result = $this->product->applyDiscounts((float) $this->product->price);

    expect($result->discountAmount)->toBe(150.0)
        ->and($result->payableAmount())->toBe(850.0)
        ->and($result->discounts)->toHaveCount(2);
});
