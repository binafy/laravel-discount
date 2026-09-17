# Laravel Discount

<img src="https://banners.beyondco.de/Laravel%20Discount.png?theme=light&packageManager=composer+require&packageName=binafy%2Flaravel-discount&pattern=kiwi&style=style_1&description=Handle+discounts+in+your+application+effortlessly&md=1&showWatermark=0&fontSize=125px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg" alt="laravel-discount">

[![PHP Version Require](https://img.shields.io/packagist/dependency-v/binafy/laravel-discount/php)](https://packagist.org/packages/binafy/laravel-discount)
[![Latest Stable Version](https://img.shields.io/packagist/v/binafy/laravel-discount.svg?style=flat-square)](https://packagist.org/packages/binafy/laravel-discount)
[![Total Downloads](https://img.shields.io/packagist/dt/binafy/laravel-discount.svg?style=flat-square)](https://packagist.org/packages/binafy/laravel-discount)
[![License](https://img.shields.io/packagist/l/binafy/laravel-discount)](https://packagist.org/packages/binafy/laravel-discount)
[![Passed Tests](https://github.com/binafy/laravel-discount/actions/workflows/run-tests.yml/badge.svg)](https://github.com/binafy/laravel-discount/actions/workflows/run-tests.yml)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/binafy/laravel-discount)

The `Laravel-Discount` is a Laravel package designed to handle discounts in your application effortlessly. This package provides a comprehensive and flexible solution to apply various discount strategies, making it easy to integrate promotional offers, seasonal sales, and other discount-related functionalities into your Laravel project.

## Features

- Percentage Discounts: Apply percentage-based discounts to your products or services.
- Fixed Amount Discounts: Deduct a fixed amount from the total cost.
- Buy X Get Y: Run "buy 2, get 1 free" deals that price the free items automatically.
- Tiered Discounts: Grow the discount with the order total, e.g. 5% over 1,000,000 and 10% over 5,000,000.
- Free Shipping: Waive the shipping cost instead of deducting from the total.
- Conditional Discounts: Set conditions for discounts, such as minimum order value or specific product categories.
- Condition Engine: Store reusable rules on a discount — category, first purchase, minimum item count — or write your own.
- Discount Codes: Generate and manage discount codes for your customers.
- Expiry Dates: Set expiration dates for discounts to create time-limited offers.
- Usage Limits: Restrict the number of times a discount can be used.
- Stackable Discounts: Allow multiple discounts to be applied simultaneously or restrict stacking.
- Support [Laravel Cart](https://github.com/binafy/laravel-cart)
- Detailed Documentation: Comprehensive guides and examples to help you get started quickly.

- - -

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Publish Config & Migrations](#publish-config--migrations)
  - [Upgrading an Existing Installation](#upgrading-an-existing-installation)
- [Usage](#usage)
  - [Create a Discount](#create-a-discount)
    - [Percentage Discount](#percentage-discount)
    - [Fixed Amount Discount](#fixed-amount-discount)
    - [Maximum Discount Amount](#maximum-discount-amount)
    - [Buy X Get Y](#buy-x-get-y)
    - [Tiered Discount](#tiered-discount)
    - [Free Shipping](#free-shipping)
  - [Apply a Discount](#apply-a-discount)
  - [Discount Codes](#discount-codes)
    - [Generate Codes](#generate-codes)
    - [Apply by Code](#apply-by-code)
  - [Expiry Dates & Time Windows](#expiry-dates--time-windows)
  - [Usage Limits](#usage-limits)
    - [Redeeming](#redeeming)
    - [Guest Discounts](#guest-discounts)
  - [Conditional Discounts](#conditional-discounts)
    - [Minimum Order Value](#minimum-order-value)
    - [Attach Discounts to Models](#attach-discounts-to-models)
  - [Condition Engine](#condition-engine)
    - [Bundled Conditions](#bundled-conditions)
    - [Combining Rules](#combining-rules)
    - [Writing Your Own Condition](#writing-your-own-condition)
  - [Stackable Discounts](#stackable-discounts)
  - [Form Request Validation](#form-request-validation)
  - [Validation & Exceptions](#validation--exceptions)
  - [Events](#events)
  - [Laravel Cart Integration](#laravel-cart-integration)
  - [Artisan Commands](#artisan-commands)
- [Testing](#testing)
- [Contributors](#contributors)
- [Security](#security)
- [License](#license)

<a name="requirements"></a>
## Requirements

- PHP 8.1 or higher
- Laravel 9.0 or higher

<a name="installation"></a>
## Installation

Install the package with Composer:

```bash
composer require binafy/laravel-discount
```

The service provider is registered automatically. Run the migrations to create the `discounts`, `discount_usages`, and `discountables` tables:

```bash
php artisan migrate
```

<a name="publish-config--migrations"></a>
## Publish Config & Migrations

Publishing is optional — the package works out of the box. Publish the config to customize table names, the user model, or code generation defaults:

```bash
php artisan vendor:publish --tag="laravel-discount-config"
```

Publish the migrations if you want to change the table structure before migrating:

```bash
php artisan vendor:publish --tag="laravel-discount-migrations"
```

Once a migration is published, the package no longer loads its own copy of it, so `php artisan migrate` runs each migration exactly once.

<a name="upgrading-an-existing-installation"></a>
### Upgrading an Existing Installation

Fresh installs need nothing here. If you already migrated the `discounts` table before the `buy_x_get_y`, `tiered`, and `free_shipping` types existed, the `type` enum still rejects them. Widen it — and make `value` optional, since the new types do not use it — with your own migration:

```php
use Binafy\LaravelDiscount\Enums\DiscountType;

Schema::table('discounts', function (Blueprint $table) {
    $table->enum('type', DiscountType::values())->default(DiscountType::Percentage->value)->change();
    $table->decimal('value', 10, 2)->default(0)->change();
});
```

> On Laravel 10 and below, `->change()` requires `doctrine/dbal`.

<a name="usage"></a>
## Usage

<a name="create-a-discount"></a>
### Create a Discount

`Binafy\LaravelDiscount\Models\Discount` is a regular Eloquent model.

<a name="percentage-discount"></a>
#### Percentage Discount

```php
use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Models\Discount;

$discount = Discount::query()->create([
    'name' => 'Summer Sale',
    'type' => DiscountType::Percentage,
    'value' => 20, // 20%
]);
```

<a name="fixed-amount-discount"></a>
#### Fixed Amount Discount

```php
$discount = Discount::query()->create([
    'name' => 'Ten dollars off',
    'type' => DiscountType::Fixed,
    'value' => 10, // deducts 10 from the total
]);
```

> A fixed discount never exceeds the amount it is applied to, so the payable amount can never go below zero.

<a name="maximum-discount-amount"></a>
#### Maximum Discount Amount

Cap how much a discount can deduct — "20% off, up to 100":

```php
$discount = Discount::query()->create([
    'code' => 'SAVE20',
    'type' => DiscountType::Percentage,
    'value' => 20,
    'max_discount_amount' => 100,
]);

LaravelDiscount::apply($discount, 300)->discountAmount;  // 60.0  (20% of 300)
LaravelDiscount::apply($discount, 1000)->discountAmount; // 100.0 (capped)
```

<a name="buy-x-get-y"></a>
#### Buy X Get Y

"Buy 2, get 1 free" — the deal lives in the `conditions` column, and the manager works out how many items come free:

```php
$discount = Discount::query()->create([
    'name' => 'Buy 2 get 1 free',
    'code' => 'BUY2GET1',
    'type' => DiscountType::BuyXGetY,
    'conditions' => ['buy' => 2, 'get' => 1],
]);
```

Because the deal counts items, pass the quantity the amount covers:

```php
// 3 items at 100 each: the third one is free.
LaravelDiscount::apply($discount, 300, quantity: 3)->discountAmount; // 100.0

// 6 items at 50 each: two full sets, so two items are free.
LaravelDiscount::apply($discount, 300, quantity: 6)->discountAmount; // 100.0

// Below a full set, nothing applies.
LaravelDiscount::apply($discount, 200, quantity: 2)->discountAmount; // 0.0
```

The free items are priced at the basket's average unit price, so a mixed basket is handled too. Two optional conditions refine the deal:

| Condition                 | Meaning                                                            | Default |
|---------------------------|--------------------------------------------------------------------|---------|
| `buy`                     | Items that must be paid for (required)                             | —       |
| `get`                     | Items that come free per set (required)                            | —       |
| `get_discount_percentage` | How much off the free items, e.g. `50` for "the third at half off" | `100`   |
| `max_free_items`          | Cap on free items per order                                        | none    |

```php
// Buy 2, get the third at 50% off, at most 2 discounted items per order.
'conditions' => ['buy' => 2, 'get' => 1, 'get_discount_percentage' => 50, 'max_free_items' => 2],
```

> The `quantity` argument is also accepted by `applyCode()`, `applyMany()` and the `applyDiscounts()` trait method. Every other discount type ignores it.

<a name="tiered-discount"></a>
#### Tiered Discount

Grow the discount with the order total. The ladder lives in the `conditions` column, and the highest tier the amount reaches wins:

```php
$discount = Discount::query()->create([
    'name' => 'Spend more, save more',
    'type' => DiscountType::Tiered,
    'conditions' => ['tiers' => [
        ['min' => 1_000_000, 'value' => 5],  // over 1,000,000 → 5%
        ['min' => 5_000_000, 'value' => 10], // over 5,000,000 → 10%
    ]],
]);

LaravelDiscount::apply($discount, 900_000)->discountAmount;   // 0.0      (below every tier)
LaravelDiscount::apply($discount, 2_000_000)->discountAmount; // 100_000.0 (5%)
LaravelDiscount::apply($discount, 6_000_000)->discountAmount; // 600_000.0 (10%)
```

Tiers may be listed in any order, and a tier applies exactly at its `min`. A tier discounts by percentage unless it sets `'type' => 'fixed'`:

```php
'conditions' => ['tiers' => [
    ['min' => 1_000_000, 'value' => 50_000, 'type' => 'fixed'],
    ['min' => 5_000_000, 'value' => 400_000, 'type' => 'fixed'],
]],
```

To show the customer which tier they landed on:

```php
LaravelDiscount::matchingTier($discount, 6_000_000); // ['min' => 5000000, 'value' => 10]
```

<a name="free-shipping"></a>
#### Free Shipping

A free shipping discount deducts nothing from the order total. Instead it raises a flag on the result, which you honour when charging for shipping:

```php
$discount = Discount::query()->create([
    'name' => 'Free shipping',
    'code' => 'FREESHIP',
    'type' => DiscountType::FreeShipping,
]);

$result = LaravelDiscount::applyCode('FREESHIP', 500);

$result->discountAmount;        // 0.0
$result->payableAmount();       // 500.0
$result->hasFreeShipping();     // true
$result->payableShipping(35);   // 0.0   (35 when shipping is not free)
$result->payableTotal(35);      // 500.0 (535 when shipping is not free)
```

Free shipping sits outside the stacking competition described in [Stackable Discounts](#stackable-discounts): since it saves nothing on the amount, it would always lose. Every valid free shipping discount is applied on top of whichever monetary discount wins:

```php
$result = LaravelDiscount::applyMany([$freeShipping, $twentyPercent], 500);

$result->discountAmount;    // 100.0 (the 20%)
$result->hasFreeShipping(); // true
```

Everything else still applies, so `min_order_value` gates free shipping the usual way:

```php
Discount::query()->create([
    'code' => 'SHIP-OVER-1000',
    'type' => DiscountType::FreeShipping,
    'min_order_value' => 1000,
]);
```

<a name="apply-a-discount"></a>
### Apply a Discount

Use the `LaravelDiscount` facade to apply a discount to an amount. It validates the discount first and returns a `DiscountResult`:

```php
use Binafy\LaravelDiscount\Facades\LaravelDiscount;

$result = LaravelDiscount::apply($discount, 200);

$result->originalAmount;    // 200.0
$result->discountAmount;    // 40.0
$result->payableAmount();   // 160.0
$result->discounts;         // Collection of the applied discounts
$result->hasFreeShipping(); // false — see Free Shipping
```

To check a discount without throwing exceptions:

```php
LaravelDiscount::isValid($discount, orderAmount: 200, user: $user); // true|false
```

<a name="discount-codes"></a>
### Discount Codes

A discount with a `code` acts as a coupon; a discount without one is an automatic discount.

```php
$discount = Discount::query()->create([
    'code' => 'WELCOME10',
    'type' => DiscountType::Percentage,
    'value' => 10,
]);
```

<a name="generate-codes"></a>
#### Generate Codes

Generate cryptographically random, unique codes (ambiguous characters like `0/O` and `1/I` are excluded by default):

```php
LaravelDiscount::generateCode();              // "8FJ2K9QW"
LaravelDiscount::generateCode('SUMMER');      // "SUMMER-8FJ2K9QW"
LaravelDiscount::generateCodes(100, 'VIP');   // Collection of 100 unique codes
```

Customize the length, character set, prefix, and separator in `config/laravel-discount.php` under the `codes` key.

<a name="apply-by-code"></a>
#### Apply by Code

```php
$result = LaravelDiscount::applyCode('WELCOME10', 200, $user);
```

If the code does not exist, a `DiscountNotFoundException` is thrown. You can also look a discount up yourself:

```php
$discount = LaravelDiscount::findByCode('WELCOME10');
```

<a name="expiry-dates--time-windows"></a>
### Expiry Dates & Time Windows

Give a discount a start date, an expiry date, or both to create time-limited offers:

```php
$discount = Discount::query()->create([
    'code' => 'BLACK-FRIDAY',
    'type' => DiscountType::Percentage,
    'value' => 30,
    'starts_at' => now()->startOfDay(),
    'expires_at' => now()->addDays(3),
]);
```

- Before `starts_at`, applying throws `DiscountNotStartedException`.
- After `expires_at`, applying throws `DiscountExpiredException` (and dispatches the `DiscountExpired` event).
- Query only the currently applicable discounts with the `valid()` scope:

```php
Discount::query()->valid()->get();
```

<a name="usage-limits"></a>
### Usage Limits

Limit how many times a discount can be used — in total and per user:

```php
$discount = Discount::query()->create([
    'code' => 'FIRST-100',
    'type' => DiscountType::Fixed,
    'value' => 15,
    'usage_limit' => 100,        // first 100 redemptions only
    'usage_limit_per_user' => 1, // once per user
]);
```

<a name="redeeming"></a>
#### Redeeming

When an order is finalized, record the redemption. This creates a `DiscountUsage` row and increments the `used_count` counter atomically — the limit check happens inside the update query, so concurrent requests can never exceed the limit:

```php
LaravelDiscount::redeem($discount, $user, $result->discountAmount);
```

When the limit is exhausted, `DiscountUsageLimitReachedException` is thrown.

<a name="guest-discounts"></a>
#### Guest Discounts

Guests (not-logged-in visitors) can use discounts too. Pass a session id instead of a user, and the per-user limit is enforced per session:

```php
$result = LaravelDiscount::applyCode('GUEST10', $total, sessionId: session()->getId());

LaravelDiscount::redeem($discount, amount: $result->discountAmount, sessionId: session()->getId());
```

The `discount_usages.user_id` column is nullable — guest redemptions store the `session_id` instead.

<a name="conditional-discounts"></a>
### Conditional Discounts

<a name="minimum-order-value"></a>
#### Minimum Order Value

```php
$discount = Discount::query()->create([
    'code' => 'BIG-SPENDER',
    'type' => DiscountType::Percentage,
    'value' => 15,
    'min_order_value' => 500,
]);

LaravelDiscount::applyCode('BIG-SPENDER', 300); // throws MinimumOrderValueException
LaravelDiscount::applyCode('BIG-SPENDER', 800); // OK
```

The `conditions` JSON column also configures the [Buy X Get Y](#buy-x-get-y) and [Tiered](#tiered-discount) types, and hosts the rules of the [Condition Engine](#condition-engine).

<a name="attach-discounts-to-models"></a>
#### Attach Discounts to Models

Add the `HasDiscounts` trait to any model (products, categories, ...) to make it discountable:

```php
use Binafy\LaravelDiscount\Traits\HasDiscounts;

class Product extends Model
{
    use HasDiscounts;
}
```

```php
// Attach and query
$product->discounts()->attach($discount);
$product->validDiscounts();       // only the currently applicable ones
$product->hasDiscount('TECH10');  // by code or by model instance

// Apply all attached valid discounts to a price (stacking rules included)
$result = $product->applyDiscounts($product->price);
$result->payableAmount();
```

<a name="condition-engine"></a>
### Condition Engine

The `conditions` column is not just storage — the rules you put in `conditions.rules` are evaluated on every `validate()`, `isValid()` and `apply*()` call. A discount whose rules are not met is invalid, exactly like an expired one:

```php
$discount = Discount::query()->create([
    'code' => 'WELCOME10',
    'type' => DiscountType::Percentage,
    'value' => 10,
    'conditions' => ['rules' => [
        ['type' => 'first_purchase'],
        ['type' => 'minimum_item_count', 'count' => 2],
    ]],
]);

LaravelDiscount::apply($discount, 500, $user, quantity: 1);
// throws DiscountConditionFailedException: "This discount requires at least 2 items."
```

Conditions read an order through a `DiscountContext`, so pass what they need. The `quantity` argument feeds item-count rules, and the `payload` argument carries everything else — most often the order's items:

```php
$result = LaravelDiscount::apply(
    $discount,
    $order->total,
    $user,
    quantity: $order->items->sum('quantity'),
    payload: ['items' => $order->items->pluck('product')],
);
```

> The [Laravel Cart integration](#laravel-cart-integration) fills both in for you.

The exception names the rule that failed, so you can tell the customer why:

```php
use Binafy\LaravelDiscount\Exceptions\DiscountConditionFailedException;

try {
    $result = LaravelDiscount::applyCode($code, $total, $user, quantity: $count, payload: ['items' => $items]);
} catch (DiscountConditionFailedException $e) {
    return back()->withErrors($e->getMessage()); // e.g. "This discount is only available on your first purchase."
}
```

To inspect the rules without applying anything — to render them on the coupon, say:

```php
LaravelDiscount::conditions($discount); // Collection<DiscountCondition>
```

<a name="bundled-conditions"></a>
#### Bundled Conditions

| Alias                | Class                        | Passes when                                                     |
|----------------------|------------------------------|-----------------------------------------------------------------|
| `category`           | `CategoryCondition`          | The order's items belong to the given categories                |
| `first_purchase`     | `FirstPurchaseCondition`     | The user has no purchase on record yet                          |
| `minimum_item_count` | `MinimumItemCountCondition`  | The order carries at least the given number of items            |

**Category** — limits a discount to certain categories:

```php
'conditions' => ['rules' => [
    ['type' => 'category', 'categories' => [7, 9]],              // any item in 7 or 9
    ['type' => 'category', 'categories' => [7], 'match' => 'all'], // every item in 7
]],
```

It reads each item's `category_id`; change that globally in the config file, or per rule with `'attribute' => 'category.id'` (dot notation follows relations). An order with no items never matches, since there is nothing to check.

**First purchase** — tell the package where purchases are recorded:

```php
// config/laravel-discount.php
'conditions' => [
    'first_purchase' => [
        'model' => \App\Models\Order::class,
        'column' => 'user_id',
    ],
],
```

```php
'conditions' => ['rules' => [
    ['type' => 'first_purchase'],
    // or override the model for this rule only:
    ['type' => 'first_purchase', 'model' => \App\Models\Subscription::class, 'column' => 'user_id'],
]],
```

> Guests never pass this rule: with no user to look up, a first purchase cannot be proven.

**Minimum item count** — counts the `quantity` handed to the manager:

```php
'conditions' => ['rules' => [['type' => 'minimum_item_count', 'count' => 3]]],
```

<a name="combining-rules"></a>
#### Combining Rules

Every rule must pass by default. Set `rules_match` to `any` when one is enough:

```php
'conditions' => [
    'rules_match' => 'any',
    'rules' => [
        ['type' => 'category', 'categories' => [7]],
        ['type' => 'minimum_item_count', 'count' => 5],
    ],
],
```

Rules sit alongside the configuration of [Buy X Get Y](#buy-x-get-y) and [Tiered](#tiered-discount) discounts, so the two can be combined freely:

```php
'conditions' => [
    'tiers' => [['min' => 1_000_000, 'value' => 5]],
    'rules' => [['type' => 'first_purchase']],
],
```

<a name="writing-your-own-condition"></a>
#### Writing Your Own Condition

Implement `DiscountCondition`. `fromArray()` rebuilds the condition from its stored JSON, `passes()` decides, and `message()` explains a refusal to the customer:

```php
use Binafy\LaravelDiscount\Contracts\DiscountCondition;
use Binafy\LaravelDiscount\Support\DiscountContext;

class WeekendOnlyCondition implements DiscountCondition
{
    public function __construct(protected array $days = ['Saturday', 'Sunday']) {}

    public static function fromArray(array $config): static
    {
        return new static($config['days'] ?? ['Saturday', 'Sunday']);
    }

    public function passes(DiscountContext $context): bool
    {
        return in_array(now()->englishDayOfWeek, $this->days);
    }

    public function message(): string
    {
        return 'This discount only runs at the weekend.';
    }
}
```

Register an alias so it can be stored by name:

```php
// config/laravel-discount.php
'conditions' => [
    'aliases' => [
        'weekend_only' => \App\Discounts\WeekendOnlyCondition::class,
    ],
],
```

```php
// or at runtime, e.g. in a service provider
use Binafy\LaravelDiscount\Support\ConditionFactory;

ConditionFactory::register('weekend_only', WeekendOnlyCondition::class);
```

```php
'conditions' => ['rules' => [['type' => 'weekend_only', 'days' => ['Friday']]]],
```

An alias is optional — a fully qualified class name works as the `type` too. Anything else is rejected with `InvalidDiscountConditionsException`.

Inside `passes()`, the context gives you the whole picture:

```php
$context->discount;    // the Discount being validated
$context->amount;      // the order amount
$context->user;        // the user model or id, may be null
$context->userId();    // the id, or null for a guest
$context->sessionId;   // the guest session, may be null
$context->quantity;    // how many items the amount covers
$context->items();     // Collection of the payload's items
$context->get('key');  // any other payload value, dot notation supported
```

<a name="stackable-discounts"></a>
### Stackable Discounts

Mark a discount with `is_stackable => true` to allow it to combine with other stackable discounts. When you apply multiple discounts, the package resolves stacking automatically:

- Stackable discounts are combined (their total never exceeds the amount).
- Non-stackable discounts compete alone.
- Whichever saves the customer the most wins.
- Invalid discounts are silently skipped.
- [Free shipping](#free-shipping) discounts sit outside the competition and always apply.

```php
$result = LaravelDiscount::applyMany([$tenPercent, $tenFixed, $bigSolo], 100);

$result->discounts;       // the discounts that were actually applied
$result->discountAmount;  // the winning total
```

<a name="form-request-validation"></a>
### Form Request Validation

Validate a submitted coupon code with the `ValidDiscountCode` rule. It checks that the code exists and is currently applicable, and the error message states the exact reason (not found, expired, usage limit reached, below minimum order, ...):

```php
use Binafy\LaravelDiscount\Rules\ValidDiscountCode;

public function rules(): array
{
    return [
        'code' => ['required', new ValidDiscountCode(
            orderAmount: $this->cartTotal(),
            user: $this->user(),
        )],
    ];
}
```

For guests, pass a session id instead of a user:

```php
'code' => ['required', new ValidDiscountCode($total, sessionId: session()->getId())],
```

<a name="validation--exceptions"></a>
### Validation & Exceptions

Every failure case has its own exception, all extending `Binafy\LaravelDiscount\Exceptions\DiscountException`:

| Exception                            | Thrown when                                    |
|--------------------------------------|------------------------------------------------|
| `DiscountNotFoundException`          | The given code does not exist                  |
| `DiscountNotActiveException`         | The discount is disabled (`is_active = false`) |
| `DiscountNotStartedException`        | `starts_at` is in the future                   |
| `DiscountExpiredException`           | `expires_at` is in the past                    |
| `DiscountUsageLimitReachedException` | The total or per-user usage limit is reached   |
| `MinimumOrderValueException`         | The order total is below `min_order_value`     |
| `InvalidDiscountConditionsException` | A "buy X get Y", tiered, or condition rule is misconfigured |
| `DiscountConditionFailedException`   | The order does not meet the discount's conditions |

Each exception carries the discount that failed, so you can handle every case separately:

```php
use Binafy\LaravelDiscount\Exceptions\DiscountException;
use Binafy\LaravelDiscount\Exceptions\DiscountExpiredException;

try {
    $result = LaravelDiscount::applyCode($code, $total, $user);
} catch (DiscountExpiredException $e) {
    return back()->withErrors("Code {$e->getDiscount()->code} has expired.");
} catch (DiscountException $e) {
    return back()->withErrors($e->getMessage());
}
```

<a name="events"></a>
### Events

| Event               | Dispatched when                                                  |
|---------------------|------------------------------------------------------------------|
| `DiscountApplied`   | One or more discounts are applied to an amount                   |
| `DiscountRedeemed`  | A redemption is recorded (after the transaction commits)         |
| `DiscountExpired`   | Validation encounters an expired discount                        |

```php
use Binafy\LaravelDiscount\Events\DiscountRedeemed;

Event::listen(DiscountRedeemed::class, function (DiscountRedeemed $event) {
    // $event->discount, $event->usage
});
```

<a name="laravel-cart-integration"></a>
### Laravel Cart Integration

If [binafy/laravel-cart](https://github.com/binafy/laravel-cart) is installed, the `CartDiscount` service becomes available:

```bash
composer require binafy/laravel-cart
```

```php
use Binafy\LaravelDiscount\Integrations\LaravelCart\CartDiscount;

$cartDiscount = app(CartDiscount::class);

// Apply a code (or discount models) to the whole cart total
$result = $cartDiscount->applyToCart($cart, 'SUMMER-8FJ2K9QW');
$result->payableAmount();

// Apply a discount to a specific cart item (price × quantity)
$result = $cartDiscount->applyToItem($cartItem, $discount);

// Automatically apply the discounts attached to each item's model
// (via the HasDiscounts trait) across the whole cart
$result = $cartDiscount->applyItemDiscounts($cart);
```

The cart total is checked against `min_order_value`, and the cart's user is used for per-user usage limits automatically.

Item quantities and items are collected for you, so [Buy X Get Y](#buy-x-get-y) discounts and the [Condition Engine](#condition-engine) work without passing anything extra: `applyToCart()` counts every unit in the cart and hands over every item model, while `applyToItem()` and `applyItemDiscounts()` scope both to the item at hand. Free shipping attached to any single item makes the whole order's shipping free:

```php
$result = $cartDiscount->applyItemDiscounts($cart);

$result->hasFreeShipping();  // true when any item's discount grants it
$result->payableTotal(35);   // the cart total plus the shipping still due
```

<a name="artisan-commands"></a>
### Artisan Commands

Generate unique discount codes from the command line:

```bash
php artisan discount:generate                    # one code
php artisan discount:generate 100 --prefix=VIP  # 100 codes like VIP-8FJ2K9QW
```

Delete expired discounts (their usage records are removed with them):

```bash
php artisan discount:prune            # everything already expired
php artisan discount:prune --days=30  # only discounts expired 30+ days ago
```

`discount:prune` works well as a [scheduled task](https://laravel.com/docs/scheduling):

```php
Schedule::command('discount:prune --days=30')->daily();
```

<a name="testing"></a>
## Testing

```bash
composer install
./vendor/bin/pest
```

<a name="contributors"></a>
## Contributors

Thanks to all the people who contributed. [Contributors](https://github.com/binafy/laravel-discount/graphs/contributors).

<a name="security"></a>
## Security

If you discover any security-related issues, please email `binafy23@gmail.com` instead of using the issue tracker.

<a name="license"></a>
## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
