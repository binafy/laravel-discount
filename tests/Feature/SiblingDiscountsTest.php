<?php

use Binafy\LaravelCart\Models\Cart;
use Binafy\LaravelDiscount\Enums\DiscountType;
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
});

/*
 * Scenario from GitHub issue #1: sibling items each get a different
 * discount — sibling 1 gets 10%, sibling 2 gets 20%, sibling 3 gets 30%.
 */

test('sibling models each get their own discount percentage', function () {
    $siblings = collect([
        ['name' => 'Sibling 1', 'percent' => 10],
        ['name' => 'Sibling 2', 'percent' => 20],
        ['name' => 'Sibling 3', 'percent' => 30],
    ])->map(function ($data) {
        $product = Product::query()->create(['name' => $data['name'], 'price' => 100]);

        $product->discounts()->attach(Discount::query()->create([
            'name' => "{$data['percent']}% off {$data['name']}",
            'type' => DiscountType::Percentage,
            'value' => $data['percent'],
        ]));

        return $product;
    });

    $payables = $siblings->map(
        fn (Product $product) => $product->applyDiscounts((float) $product->price)->payableAmount()
    );

    expect($payables->all())->toBe([90.0, 80.0, 70.0]);
});

test('sibling items in one cart each get their own discount', function () {
    $user = User::query()->create([
        'name' => 'Milwad',
        'email' => 'milwad.dev@gmail.com',
        'password' => bcrypt('password'),
    ]);

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

    $percentages = [10, 20, 30];
    $cart = Cart::query()->create(['user_id' => $user->id]);

    foreach ($percentages as $i => $percent) {
        $product = Product::query()->create(['name' => 'Sibling '.($i + 1), 'price' => 100]);

        $product->discounts()->attach(Discount::query()->create([
            'type' => DiscountType::Percentage,
            'value' => $percent,
        ]));

        $cart->items()->create([
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => 1,
        ]);
    }

    $result = app(CartDiscount::class)->applyItemDiscounts($cart);

    // 10% + 20% + 30% of 100 each = 60 off the 300 total
    expect($result->originalAmount)->toBe(300.0)
        ->and($result->discountAmount)->toBe(60.0)
        ->and($result->payableAmount())->toBe(240.0)
        ->and($result->discounts)->toHaveCount(3);
});
