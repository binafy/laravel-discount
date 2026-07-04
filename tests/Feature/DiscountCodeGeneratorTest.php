<?php

use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Models\Discount;
use Binafy\LaravelDiscount\Support\DiscountCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->generator = app(DiscountCodeGenerator::class);
});

test('generates a code with the configured length and characters', function () {
    $code = $this->generator->generate();

    expect($code)->toHaveLength(8)
        ->and($code)->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]+$/');
});

test('generates a code with a prefix and separator', function () {
    $code = $this->generator->generate('SUMMER');

    expect($code)->toMatch('/^SUMMER-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8}$/');
});

test('respects code generation config', function () {
    config()->set('laravel-discount.codes.length', 12);
    config()->set('laravel-discount.codes.prefix', 'VIP');
    config()->set('laravel-discount.codes.separator', '_');

    $code = $this->generator->generate();

    expect($code)->toMatch('/^VIP_[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{12}$/');
});

test('generates a batch of distinct codes', function () {
    $codes = $this->generator->generateMany(50);

    expect($codes)->toHaveCount(50)
        ->and($codes->unique())->toHaveCount(50);
});

test('avoids codes that already exist in the database', function () {
    // Shrink the code space to two possible codes: "A" and "B".
    config()->set('laravel-discount.codes.length', 1);
    config()->set('laravel-discount.codes.characters', 'AB');

    Discount::query()->create([
        'code' => 'A',
        'type' => DiscountType::Fixed,
        'value' => 10,
    ]);

    expect($this->generator->generate())->toBe('B');
});

test('throws when the code space is exhausted', function () {
    // Only one possible code, and it is already taken.
    config()->set('laravel-discount.codes.length', 1);
    config()->set('laravel-discount.codes.characters', 'A');

    Discount::query()->create([
        'code' => 'A',
        'type' => DiscountType::Fixed,
        'value' => 10,
    ]);

    $this->generator->generate();
})->throws(RuntimeException::class);
