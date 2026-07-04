<?php

use Binafy\LaravelCart\Models\Cart;
use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Exceptions\DiscountNotFoundException;
use Binafy\LaravelDiscount\Integrations\LaravelCart\CartDiscount;
use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Models\Product;
use Tests\Models\User;

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

    if (! Schema::hasTable('carts')) {
        Schema::create('carts', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_items', function ($table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->morphs('itemable');
            $table->unsignedInteger('quantity')->default(1);
            $table->json('options')->nullable();
            $table->timestamps();
        });
    }

    $this->cartDiscount = app(CartDiscount::class);

    $this->user = User::query()->create([
        'name' => 'Milwad',
        'email' => 'milwad.dev@gmail.com',
        'password' => bcrypt('password'),
    ]);

    $this->laptop = Product::query()->create(['name' => 'Laptop', 'price' => 800]);
    $this->mouse = Product::query()->create(['name' => 'Mouse', 'price' => 100]);

    $this->cart = Cart::query()->create(['user_id' => $this->user->id]);
    $this->cart->items()->create([
        'itemable_id' => $this->laptop->id,
        'itemable_type' => Product::class,
        'quantity' => 1,
    ]);
    $this->cart->items()->create([
        'itemable_id' => $this->mouse->id,
        'itemable_type' => Product::class,
        'quantity' => 2,
    ]);
    // Cart total: 800 + (2 × 100) = 1000
});

test('applies a discount code to the whole cart', function () {
    Discount::query()->create([
        'code' => 'CART10',
        'type' => DiscountType::Percentage,
        'value' => 10,
    ]);

    $result = $this->cartDiscount->applyToCart($this->cart, 'CART10');

    expect($result->originalAmount)->toBe(1000.0)
        ->and($result->discountAmount)->toBe(100.0)
        ->and($result->payableAmount())->toBe(900.0);
});

test('throws when the discount code does not exist', function () {
    $this->cartDiscount->applyToCart($this->cart, 'MISSING');
})->throws(DiscountNotFoundException::class, 'The discount code [MISSING] was not found.');

test('cart total is checked against the minimum order value', function () {
    Discount::query()->create([
        'code' => 'BIG-ORDERS',
        'type' => DiscountType::Percentage,
        'value' => 10,
        'min_order_value' => 5000,
    ]);

    $result = $this->cartDiscount->applyToCart($this->cart, 'BIG-ORDERS');

    // Cart total (1000) is below the minimum (5000), so nothing applies.
    expect($result->discountAmount)->toBe(0.0)
        ->and($result->payableAmount())->toBe(1000.0);
});

test('applies a discount to a specific cart item subtotal', function () {
    $discount = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 30,
    ]);

    $mouseItem = $this->cart->items()->where('itemable_id', $this->mouse->id)->first();

    $result = $this->cartDiscount->applyToItem($mouseItem, $discount);

    // Mouse subtotal: 2 × 100 = 200
    expect($result->originalAmount)->toBe(200.0)
        ->and($result->discountAmount)->toBe(30.0)
        ->and($result->payableAmount())->toBe(170.0);
});

test('applies the discounts attached to each item model across the cart', function () {
    $laptopDiscount = Discount::query()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
    ]);
    $expired = Discount::query()->create([
        'type' => DiscountType::Percentage,
        'value' => 50,
        'expires_at' => now()->subDay(),
    ]);

    $this->laptop->discounts()->attach([$laptopDiscount->id, $expired->id]);
    // Mouse has no discounts.

    $result = $this->cartDiscount->applyItemDiscounts($this->cart);

    // Only the laptop's valid discount applies: 10% of 800 = 80
    expect($result->originalAmount)->toBe(1000.0)
        ->and($result->discountAmount)->toBe(80.0)
        ->and($result->payableAmount())->toBe(920.0)
        ->and($result->discounts)->toHaveCount(1)
        ->and($result->discounts->first()->is($laptopDiscount))->toBeTrue();
});
