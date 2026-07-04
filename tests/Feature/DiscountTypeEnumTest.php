<?php

use Binafy\LaravelDiscount\Enums\DiscountType;

test('discount type enum has percentage and fixed cases', function () {
    expect(DiscountType::values())->toBe(['percentage', 'fixed'])
        ->and(DiscountType::from('percentage'))->toBe(DiscountType::Percentage)
        ->and(DiscountType::from('fixed'))->toBe(DiscountType::Fixed);
});
