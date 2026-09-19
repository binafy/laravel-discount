<?php

use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Exceptions\DiscountCurrencyMismatchException;
use Binafy\LaravelDiscount\Models\Discount;
use Binafy\LaravelDiscount\Rules\ValidDiscountCode;
use Binafy\LaravelDiscount\Support\DiscountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = app(DiscountManager::class);

    config()->set('laravel-discount.currency', null);
});

function makePriced(?string $currency, array $attributes = []): Discount
{
    return Discount::query()->create(array_merge([
        'type' => DiscountType::Fixed,
        'value' => 10,
        'currency' => $currency,
    ], $attributes));
}

test('a discount without a currency fits every order', function () {
    $discount = makePriced(null);

    expect($this->manager->isValid($discount, 100))->toBeTrue()
        ->and($this->manager->isValid($discount, 100, payload: ['currency' => 'EUR']))->toBeTrue()
        ->and($this->manager->isValid($discount, 100, payload: ['currency' => 'IRR']))->toBeTrue();
});

test('a discount applies to orders in its own currency', function () {
    $result = $this->manager->apply(makePriced('EUR'), 100, payload: ['currency' => 'EUR']);

    expect($result->discountAmount)->toBe(10.0);
});

test('currencies are compared regardless of case', function () {
    $discount = makePriced('eur');

    expect($discount->currency)->toBe('EUR')
        ->and($this->manager->isValid($discount, 100, payload: ['currency' => 'eur']))->toBeTrue();
});

test('an empty currency is stored as no currency', function () {
    expect(makePriced('')->currency)->toBeNull();
});

test('a discount is refused for an order in another currency', function () {
    $this->manager->apply(makePriced('EUR'), 100, payload: ['currency' => 'USD']);
})->throws(DiscountCurrencyMismatchException::class, 'This discount is only available for orders in EUR.');

test('a priced discount is refused when the order currency is unknown', function () {
    $this->manager->apply(makePriced('EUR'), 100);
})->throws(DiscountCurrencyMismatchException::class, "This discount is priced in EUR, but the order's currency was not given.");

test('the store currency from the config file is assumed when the order gives none', function () {
    config()->set('laravel-discount.currency', 'EUR');

    expect($this->manager->isValid(makePriced('EUR'), 100))->toBeTrue()
        ->and($this->manager->isValid(makePriced('USD'), 100))->toBeFalse();
});

test('the order currency wins over the store currency', function () {
    config()->set('laravel-discount.currency', 'EUR');

    expect($this->manager->isValid(makePriced('USD'), 100, payload: ['currency' => 'USD']))->toBeTrue();
});

test('the currency is checked before the minimum order value', function () {
    $discount = makePriced('EUR', ['min_order_value' => 500]);

    // 100 USD is below 500 EUR only if you pretend the two are the same unit.
    $this->manager->validate($discount, 100, payload: ['currency' => 'USD']);
})->throws(DiscountCurrencyMismatchException::class);

test('the exception carries the discount', function () {
    $discount = makePriced('EUR');

    try {
        $this->manager->validate($discount, 100, payload: ['currency' => 'USD']);
    } catch (DiscountCurrencyMismatchException $e) {
        expect($e->getDiscount()->is($discount))->toBeTrue();

        return;
    }

    $this->fail('The currency should not have matched.');
});

test('discounts in another currency are skipped when applying many', function () {
    $euro = makePriced('EUR', ['value' => 10]);
    $dollar = makePriced('USD', ['value' => 30]);

    $result = $this->manager->applyMany([$euro, $dollar], 100, payload: ['currency' => 'EUR']);

    expect($result->discountAmount)->toBe(10.0)
        ->and($result->discounts->pluck('id')->all())->toBe([$euro->id]);
});

test('discounts can be queried by the currency they fit', function () {
    $euro = makePriced('EUR');
    $dollar = makePriced('USD');
    $any = makePriced(null);

    expect(Discount::query()->forCurrency('eur')->pluck('id')->sort()->values()->all())->toBe([$euro->id, $any->id])
        ->and(Discount::query()->forCurrency(null)->pluck('id')->all())->toBe([$any->id]);
});

test('the discount model answers for a single currency', function () {
    expect(makePriced('EUR')->appliesToCurrency('EUR'))->toBeTrue()
        ->and(makePriced('EUR')->appliesToCurrency('USD'))->toBeFalse()
        ->and(makePriced('EUR')->appliesToCurrency(null))->toBeFalse()
        ->and(makePriced(null)->appliesToCurrency(null))->toBeTrue();
});

test('the context exposes the order currency', function () {
    config()->set('laravel-discount.currency', 'IRR');

    $discount = makePriced(null);

    expect((new DiscountContext($discount, payload: ['currency' => 'usd']))->currency())->toBe('USD')
        ->and((new DiscountContext($discount))->currency())->toBe('IRR');
});

test('the validation rule checks the currency', function () {
    makePriced('EUR', ['code' => 'EURO10']);

    $validate = fn (string $currency) => Validator::make(
        ['code' => 'EURO10'],
        ['code' => [new ValidDiscountCode(100, payload: ['currency' => $currency])]]
    );

    expect($validate('EUR')->passes())->toBeTrue()
        ->and($validate('USD')->errors()->first('code'))->toBe('This discount is only available for orders in EUR.');
});
