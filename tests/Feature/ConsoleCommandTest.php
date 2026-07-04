<?php

use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| discount:generate
|--------------------------------------------------------------------------
*/

test('discount:generate outputs a single code by default', function () {
    Artisan::call('discount:generate');

    $codes = array_filter(explode("\n", trim(Artisan::output())));

    expect($codes)->toHaveCount(1)
        ->and($codes[array_key_first($codes)])->toMatch('/^[A-Z2-9]{8}$/');
});

test('discount:generate outputs a batch of distinct prefixed codes', function () {
    Artisan::call('discount:generate', ['count' => 5, '--prefix' => 'VIP']);

    $codes = array_filter(explode("\n", trim(Artisan::output())));

    expect($codes)->toHaveCount(5)
        ->and(array_unique($codes))->toHaveCount(5);

    foreach ($codes as $code) {
        expect($code)->toMatch('/^VIP-[A-Z2-9]{8}$/');
    }
});

test('discount:generate fails for a count below one', function () {
    $this->artisan('discount:generate', ['count' => 0])->assertFailed();
});

/*
|--------------------------------------------------------------------------
| discount:prune
|--------------------------------------------------------------------------
*/

test('discount:prune deletes only expired discounts', function () {
    $expired = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
        'expires_at' => now()->subDay(),
    ]);

    $active = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
        'expires_at' => now()->addDay(),
    ]);

    $everlasting = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
    ]);

    $this->artisan('discount:prune')
        ->expectsOutput('Pruned 1 expired discount.')
        ->assertSuccessful();

    expect(Discount::query()->find($expired->id))->toBeNull()
        ->and(Discount::query()->find($active->id))->not->toBeNull()
        ->and(Discount::query()->find($everlasting->id))->not->toBeNull();
});

test('discount:prune respects the days option', function () {
    $recentlyExpired = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
        'expires_at' => now()->subDays(3),
    ]);

    $longExpired = Discount::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
        'expires_at' => now()->subDays(30),
    ]);

    $this->artisan('discount:prune', ['--days' => 7])->assertSuccessful();

    expect(Discount::query()->find($recentlyExpired->id))->not->toBeNull()
        ->and(Discount::query()->find($longExpired->id))->toBeNull();
});
