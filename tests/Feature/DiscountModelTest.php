<?php

use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Models\Discount;
use Binafy\LaravelDiscount\Models\DiscountUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Models\Product;
use Tests\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (! Illuminate\Support\Facades\Schema::hasTable('products')) {
        Illuminate\Support\Facades\Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 15, 2)->default(0);
            $table->timestamps();
        });
    }
});

test('discount attributes are cast to proper types', function () {
    $discount = Discount::query()->create([
        'name' => 'Summer Sale',
        'code' => 'SUMMER20',
        'type' => DiscountType::Percentage,
        'value' => 20,
        'conditions' => ['categories' => [1, 2]],
        'is_stackable' => 1,
        'is_active' => 1,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addDay(),
    ]);

    $discount->refresh();

    expect($discount->type)->toBe(DiscountType::Percentage)
        ->and($discount->conditions)->toBe(['categories' => [1, 2]])
        ->and($discount->is_stackable)->toBeTrue()
        ->and($discount->is_active)->toBeTrue()
        ->and($discount->starts_at)->toBeInstanceOf(Illuminate\Support\Carbon::class)
        ->and($discount->expires_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
});

test('valid scope only returns applicable discounts', function () {
    $valid = Discount::query()->create([
        'code' => 'VALID',
        'type' => DiscountType::Fixed,
        'value' => 10,
    ]);

    Discount::query()->create([
        'code' => 'INACTIVE',
        'type' => DiscountType::Fixed,
        'value' => 10,
        'is_active' => false,
    ]);

    Discount::query()->create([
        'code' => 'EXPIRED',
        'type' => DiscountType::Fixed,
        'value' => 10,
        'expires_at' => now()->subDay(),
    ]);

    Discount::query()->create([
        'code' => 'NOT-STARTED',
        'type' => DiscountType::Fixed,
        'value' => 10,
        'starts_at' => now()->addDay(),
    ]);

    Discount::query()->create([
        'code' => 'EXHAUSTED',
        'type' => DiscountType::Fixed,
        'value' => 10,
        'usage_limit' => 5,
        'used_count' => 5,
    ]);

    expect(Discount::query()->valid()->pluck('code')->all())->toBe([$valid->code]);
});

test('validity helpers report discount state', function () {
    $expired = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
        'expires_at' => now()->subDay(),
    ]);

    $exhausted = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
        'usage_limit' => 1,
        'used_count' => 1,
    ]);

    $valid = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
    ]);

    expect($expired->isExpired())->toBeTrue()
        ->and($expired->isValid())->toBeFalse()
        ->and($exhausted->usageLimitReached())->toBeTrue()
        ->and($exhausted->isValid())->toBeFalse()
        ->and($valid->isValid())->toBeTrue();
});

test('discount has usages and usage belongs to user and discount', function () {
    $user = User::query()->create([
        'name' => 'Milwad',
        'email' => 'milwad.dev@gmail.com',
        'password' => bcrypt('password'),
    ]);

    $discount = Discount::query()->create([
        'code' => 'WELCOME',
        'type' => DiscountType::Percentage,
        'value' => 10,
    ]);

    $usage = DiscountUsage::query()->create([
        'discount_id' => $discount->id,
        'user_id' => $user->id,
        'amount' => 25.50,
        'used_at' => now(),
    ]);

    expect($discount->usages)->toHaveCount(1)
        ->and($usage->discount->is($discount))->toBeTrue()
        ->and($usage->user->is($user))->toBeTrue();
});

test('discount can be attached to models through morph relation', function () {
    $product = Product::query()->create(['name' => 'Laptop', 'price' => 1000]);

    $discount = Discount::query()->create([
        'code' => 'TECH10',
        'type' => DiscountType::Percentage,
        'value' => 10,
    ]);

    $product->discounts()->attach($discount);

    expect($product->discounts)->toHaveCount(1)
        ->and($product->discounts->first()->is($discount))->toBeTrue()
        ->and($discount->discountables(Product::class)->first()->is($product))->toBeTrue();
});
