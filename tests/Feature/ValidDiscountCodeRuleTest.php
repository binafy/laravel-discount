<?php

use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Models\Discount;
use Binafy\LaravelDiscount\Rules\ValidDiscountCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\Models\User;

uses(RefreshDatabase::class);

function validateCode(mixed $code, ValidDiscountCode $rule): Illuminate\Validation\Validator
{
    return Validator::make(['code' => $code], ['code' => ['required', $rule]]);
}

test('passes for a valid discount code', function () {
    Discount::query()->create([
        'code' => 'SUMMER20',
        'type' => DiscountType::Percentage,
        'value' => 20,
    ]);

    expect(validateCode('SUMMER20', new ValidDiscountCode)->passes())->toBeTrue();
});

test('fails with a not-found message for an unknown code', function () {
    $validator = validateCode('MISSING', new ValidDiscountCode);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('code'))->toBe('The discount code [MISSING] was not found.');
});

test('fails with the exact reason for an expired code', function () {
    Discount::query()->create([
        'code' => 'OLD',
        'type' => DiscountType::Percentage,
        'value' => 20,
        'expires_at' => now()->subDay(),
    ]);

    $validator = validateCode('OLD', new ValidDiscountCode);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('code'))->toBe('The discount has expired.');
});

test('respects the order amount for minimum order value', function () {
    Discount::query()->create([
        'code' => 'BIG',
        'type' => DiscountType::Percentage,
        'value' => 10,
        'min_order_value' => 500,
    ]);

    expect(validateCode('BIG', new ValidDiscountCode(orderAmount: 300))->fails())->toBeTrue()
        ->and(validateCode('BIG', new ValidDiscountCode(orderAmount: 800))->passes())->toBeTrue();
});

test('respects the per-user limit for the given user', function () {
    $user = User::query()->create([
        'name' => 'Milwad',
        'email' => 'milwad.dev@gmail.com',
        'password' => bcrypt('password'),
    ]);

    $discount = Discount::query()->create([
        'code' => 'ONCE',
        'type' => DiscountType::Fixed,
        'value' => 10,
        'usage_limit_per_user' => 1,
    ]);

    expect(validateCode('ONCE', new ValidDiscountCode(user: $user))->passes())->toBeTrue();

    app(DiscountManager::class)->redeem($discount, $user);

    $validator = validateCode('ONCE', new ValidDiscountCode(user: $user));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('code'))->toBe('The discount usage limit for this user has been reached.');
});

test('respects the session id for guests', function () {
    $discount = Discount::query()->create([
        'code' => 'GUEST',
        'type' => DiscountType::Fixed,
        'value' => 10,
        'usage_limit_per_user' => 1,
    ]);

    app(DiscountManager::class)->redeem($discount, sessionId: 'session-a');

    expect(validateCode('GUEST', new ValidDiscountCode(sessionId: 'session-a'))->fails())->toBeTrue()
        ->and(validateCode('GUEST', new ValidDiscountCode(sessionId: 'session-b'))->passes())->toBeTrue();
});
