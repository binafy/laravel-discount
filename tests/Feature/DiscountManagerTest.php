<?php

use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Exceptions\DiscountExpiredException;
use Binafy\LaravelDiscount\Exceptions\DiscountNotActiveException;
use Binafy\LaravelDiscount\Exceptions\DiscountNotStartedException;
use Binafy\LaravelDiscount\Exceptions\DiscountUsageLimitReachedException;
use Binafy\LaravelDiscount\Exceptions\MinimumOrderValueException;
use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = app(DiscountManager::class);
});

function makeDiscount(array $attributes = []): Discount
{
    return Discount::query()->create(array_merge([
        'type' => DiscountType::Percentage,
        'value' => 10,
    ], $attributes));
}

function makeUser(string $email = 'milwad.dev@gmail.com'): User
{
    return User::query()->create([
        'name' => 'Milwad',
        'email' => $email,
        'password' => bcrypt('password'),
    ]);
}

/*
|--------------------------------------------------------------------------
| Calculation
|--------------------------------------------------------------------------
*/

test('calculates percentage discount', function () {
    $discount = makeDiscount(['type' => DiscountType::Percentage, 'value' => 20]);

    expect($this->manager->calculate($discount, 250))->toBe(50.0);
});

test('calculates fixed discount', function () {
    $discount = makeDiscount(['type' => DiscountType::Fixed, 'value' => 30]);

    expect($this->manager->calculate($discount, 100))->toBe(30.0);
});

test('fixed discount never exceeds the amount', function () {
    $discount = makeDiscount(['type' => DiscountType::Fixed, 'value' => 500]);

    expect($this->manager->calculate($discount, 100))->toBe(100.0);
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('validate throws when discount is not active', function () {
    $this->manager->validate(makeDiscount(['is_active' => false]));
})->throws(DiscountNotActiveException::class);

test('validate throws when discount has not started', function () {
    $this->manager->validate(makeDiscount(['starts_at' => now()->addDay()]));
})->throws(DiscountNotStartedException::class);

test('validate throws when discount has expired', function () {
    $this->manager->validate(makeDiscount(['expires_at' => now()->subDay()]));
})->throws(DiscountExpiredException::class);

test('validate throws when usage limit is reached', function () {
    $this->manager->validate(makeDiscount(['usage_limit' => 3, 'used_count' => 3]));
})->throws(DiscountUsageLimitReachedException::class);

test('validate throws when per-user usage limit is reached', function () {
    $user = makeUser();
    $discount = makeDiscount(['usage_limit_per_user' => 1]);

    $this->manager->redeem($discount, $user);

    $this->manager->validate($discount, 0, $user);
})->throws(DiscountUsageLimitReachedException::class, 'The discount usage limit for this user has been reached.');

test('validate throws when order is below the minimum', function () {
    $this->manager->validate(makeDiscount(['min_order_value' => 100]), 50);
})->throws(MinimumOrderValueException::class);

test('isValid returns true for an applicable discount and false otherwise', function () {
    expect($this->manager->isValid(makeDiscount()))->toBeTrue()
        ->and($this->manager->isValid(makeDiscount(['is_active' => false])))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Applying
|--------------------------------------------------------------------------
*/

test('apply returns the discounted result', function () {
    $discount = makeDiscount(['type' => DiscountType::Percentage, 'value' => 25]);

    $result = $this->manager->apply($discount, 200);

    expect($result->discountAmount)->toBe(50.0)
        ->and($result->payableAmount())->toBe(150.0)
        ->and($result->discounts->first()->is($discount))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Stacking
|--------------------------------------------------------------------------
*/

test('stackable discounts are combined', function () {
    $ten = makeDiscount(['value' => 10, 'is_stackable' => true]);
    $fixed = makeDiscount(['type' => DiscountType::Fixed, 'value' => 15, 'is_stackable' => true]);

    $result = $this->manager->applyMany([$ten, $fixed], 100);

    expect($result->discountAmount)->toBe(25.0)
        ->and($result->discounts)->toHaveCount(2);
});

test('non-stackable discounts compete and the best one wins', function () {
    $small = makeDiscount(['value' => 10]);
    $big = makeDiscount(['value' => 30]);

    $result = $this->manager->applyMany([$small, $big], 100);

    expect($result->discountAmount)->toBe(30.0)
        ->and($result->discounts)->toHaveCount(1)
        ->and($result->discounts->first()->is($big))->toBeTrue();
});

test('the better of stacked total and best single discount wins', function () {
    $stackableA = makeDiscount(['value' => 10, 'is_stackable' => true]);
    $stackableB = makeDiscount(['value' => 15, 'is_stackable' => true]);
    $solo = makeDiscount(['value' => 50]);

    $result = $this->manager->applyMany([$stackableA, $stackableB, $solo], 100);

    expect($result->discountAmount)->toBe(50.0)
        ->and($result->discounts->first()->is($solo))->toBeTrue();
});

test('invalid discounts are skipped when applying many', function () {
    $valid = makeDiscount(['value' => 10]);
    $expired = makeDiscount(['value' => 90, 'expires_at' => now()->subDay()]);

    $result = $this->manager->applyMany([$valid, $expired], 100);

    expect($result->discountAmount)->toBe(10.0)
        ->and($result->discounts->first()->is($valid))->toBeTrue();
});

test('stacked discounts never exceed the amount', function () {
    $a = makeDiscount(['type' => DiscountType::Fixed, 'value' => 80, 'is_stackable' => true]);
    $b = makeDiscount(['type' => DiscountType::Fixed, 'value' => 70, 'is_stackable' => true]);

    $result = $this->manager->applyMany([$a, $b], 100);

    expect($result->discountAmount)->toBe(100.0)
        ->and($result->payableAmount())->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| Redemption
|--------------------------------------------------------------------------
*/

test('redeem creates a usage record and increments used count', function () {
    $user = makeUser();
    $discount = makeDiscount();

    $usage = $this->manager->redeem($discount, $user, 25.50);

    expect($discount->used_count)->toBe(1)
        ->and($usage->discount_id)->toBe($discount->id)
        ->and($usage->user_id)->toBe($user->id)
        ->and((float) $usage->amount)->toBe(25.50)
        ->and($usage->used_at)->not->toBeNull();
});

test('redeem throws when the usage limit is exhausted', function () {
    $user = makeUser();
    $discount = makeDiscount(['usage_limit' => 1]);

    $this->manager->redeem($discount, $user);
    $this->manager->redeem($discount, $user);
})->throws(DiscountUsageLimitReachedException::class);

test('redeem throws when the per-user limit is exhausted', function () {
    $discount = makeDiscount(['usage_limit_per_user' => 1]);
    $user = makeUser();
    $other = makeUser('other@example.com');

    $this->manager->redeem($discount, $user);
    $this->manager->redeem($discount, $other);
    $this->manager->redeem($discount, $user);
})->throws(DiscountUsageLimitReachedException::class, 'The discount usage limit for this user has been reached.');
