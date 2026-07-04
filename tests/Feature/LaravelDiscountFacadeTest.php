<?php

use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Exceptions\DiscountNotFoundException;
use Binafy\LaravelDiscount\Facades\LaravelDiscount;
use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Models\User;

uses(RefreshDatabase::class);

test('facade resolves the discount manager singleton', function () {
    expect(LaravelDiscount::getFacadeRoot())->toBeInstanceOf(DiscountManager::class)
        ->and(LaravelDiscount::getFacadeRoot())->toBe(app(DiscountManager::class));
});

test('finds a discount by code through the facade', function () {
    $discount = Discount::query()->create([
        'code' => 'WELCOME',
        'type' => DiscountType::Percentage,
        'value' => 10,
    ]);

    expect(LaravelDiscount::findByCode('WELCOME')->is($discount))->toBeTrue();
});

test('findByCode throws for an unknown code', function () {
    LaravelDiscount::findByCode('MISSING');
})->throws(DiscountNotFoundException::class, 'The discount code [MISSING] was not found.');

test('applies a discount by code through the facade', function () {
    Discount::query()->create([
        'code' => 'SUMMER25',
        'type' => DiscountType::Percentage,
        'value' => 25,
    ]);

    $result = LaravelDiscount::applyCode('SUMMER25', 200);

    expect($result->discountAmount)->toBe(50.0)
        ->and($result->payableAmount())->toBe(150.0);
});

test('redeems a discount through the facade', function () {
    $user = User::query()->create([
        'name' => 'Milwad',
        'email' => 'milwad.dev@gmail.com',
        'password' => bcrypt('password'),
    ]);

    $discount = Discount::query()->create([
        'code' => 'ONCE',
        'type' => DiscountType::Fixed,
        'value' => 10,
    ]);

    $usage = LaravelDiscount::redeem($discount, $user, 10);

    expect($usage->user_id)->toBe($user->id)
        ->and($discount->used_count)->toBe(1);
});

test('generates discount codes through the facade', function () {
    $code = LaravelDiscount::generateCode('VIP');
    $codes = LaravelDiscount::generateCodes(5);

    expect($code)->toMatch('/^VIP-[A-Z2-9]{8}$/')
        ->and($codes)->toHaveCount(5)
        ->and($codes->unique())->toHaveCount(5);
});
