<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('discounts table is created with expected columns', function () {
    expect(Schema::hasTable('discounts'))->toBeTrue()
        ->and(Schema::hasColumns('discounts', [
            'id',
            'name',
            'description',
            'code',
            'type',
            'value',
            'min_order_value',
            'conditions',
            'usage_limit',
            'usage_limit_per_user',
            'used_count',
            'is_stackable',
            'is_active',
            'starts_at',
            'expires_at',
        ]))->toBeTrue();
});

test('discount_usages table is created with expected columns', function () {
    expect(Schema::hasTable('discount_usages'))->toBeTrue()
        ->and(Schema::hasColumns('discount_usages', [
            'id',
            'discount_id',
            'user_id',
            'session_id',
            'amount',
            'used_at',
        ]))->toBeTrue();
});

test('discountables table is created with expected columns', function () {
    expect(Schema::hasTable('discountables'))->toBeTrue()
        ->and(Schema::hasColumns('discountables', [
            'id',
            'discount_id',
            'discountable_type',
            'discountable_id',
        ]))->toBeTrue();
});
