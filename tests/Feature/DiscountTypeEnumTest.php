<?php

use Binafy\LaravelDiscount\Enums\DiscountType;

test('discount type enum exposes every case value', function () {
    expect(DiscountType::values())->toBe(['percentage', 'fixed', 'buy_x_get_y', 'tiered', 'free_shipping'])
        ->and(DiscountType::from('percentage'))->toBe(DiscountType::Percentage)
        ->and(DiscountType::from('fixed'))->toBe(DiscountType::Fixed)
        ->and(DiscountType::from('buy_x_get_y'))->toBe(DiscountType::BuyXGetY)
        ->and(DiscountType::from('tiered'))->toBe(DiscountType::Tiered)
        ->and(DiscountType::from('free_shipping'))->toBe(DiscountType::FreeShipping);
});

test('only buy x get y and tiered are configured through conditions', function () {
    expect(DiscountType::BuyXGetY->usesConditions())->toBeTrue()
        ->and(DiscountType::Tiered->usesConditions())->toBeTrue()
        ->and(DiscountType::Percentage->usesConditions())->toBeFalse()
        ->and(DiscountType::Fixed->usesConditions())->toBeFalse()
        ->and(DiscountType::FreeShipping->usesConditions())->toBeFalse();
});

test('only buy x get y needs the quantity', function () {
    expect(DiscountType::BuyXGetY->needsQuantity())->toBeTrue()
        ->and(DiscountType::Percentage->needsQuantity())->toBeFalse()
        ->and(DiscountType::Tiered->needsQuantity())->toBeFalse();
});

test('every type except free shipping deducts an amount', function () {
    expect(DiscountType::FreeShipping->deductsAmount())->toBeFalse()
        ->and(DiscountType::Percentage->deductsAmount())->toBeTrue()
        ->and(DiscountType::Fixed->deductsAmount())->toBeTrue()
        ->and(DiscountType::BuyXGetY->deductsAmount())->toBeTrue()
        ->and(DiscountType::Tiered->deductsAmount())->toBeTrue();
});
