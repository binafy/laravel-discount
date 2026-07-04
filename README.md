# Laravel Discount

<img src="https://banners.beyondco.de/Laravel%20Discount.png?theme=light&packageManager=composer+require&packageName=binafy%2Flaravel-discount&pattern=kiwi&style=style_1&description=Handle+discounts+in+your+application+effortlessly&md=1&showWatermark=0&fontSize=125px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg" alt="laravel-discount">

The `Laravel-Discount` is a Laravel package designed to handle discounts in your application effortlessly. This package provides a comprehensive and flexible solution to apply various discount strategies, making it easy to integrate promotional offers, seasonal sales, and other discount-related functionalities into your Laravel project.

## Features

- Percentage Discounts: Apply percentage-based discounts to your products or services.
- Fixed Amount Discounts: Deduct a fixed amount from the total cost.
- Conditional Discounts: Set conditions for discounts, such as minimum order value or specific product categories.
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
- [Usage](#usage)
  - [Create a Discount](#create-a-discount)
    - [Percentage Discount](#percentage-discount)
    - [Fixed Amount Discount](#fixed-amount-discount)
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
  - [Stackable Discounts](#stackable-discounts)
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

- PHP 8.0 or higher
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

<a name="apply-a-discount"></a>
### Apply a Discount

Use the `LaravelDiscount` facade to apply a discount to an amount. It validates the discount first and returns a `DiscountResult`:

```php
use Binafy\LaravelDiscount\Facades\LaravelDiscount;

$result = LaravelDiscount::apply($discount, 200);

$result->originalAmount;   // 200.0
$result->discountAmount;   // 40.0
$result->payableAmount();  // 160.0
$result->discounts;        // Collection of the applied discounts
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

The `conditions` JSON column is also available for storing your own arbitrary condition data.

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

<a name="stackable-discounts"></a>
### Stackable Discounts

Mark a discount with `is_stackable => true` to allow it to combine with other stackable discounts. When you apply multiple discounts, the package resolves stacking automatically:

- Stackable discounts are combined (their total never exceeds the amount).
- Non-stackable discounts compete alone.
- Whichever saves the customer the most wins.
- Invalid discounts are silently skipped.

```php
$result = LaravelDiscount::applyMany([$tenPercent, $tenFixed, $bigSolo], 100);

$result->discounts;       // the discounts that were actually applied
$result->discountAmount;  // the winning total
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
