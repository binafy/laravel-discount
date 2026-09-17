<?php

use App\Models\User;
use Binafy\LaravelDiscount\Conditions\CategoryCondition;
use Binafy\LaravelDiscount\Conditions\FirstPurchaseCondition;
use Binafy\LaravelDiscount\Conditions\MinimumItemCountCondition;

return [
    /*
    |--------------------------------------------------------------------------
    | Users Table
    |--------------------------------------------------------------------------
    |
    | The table and model of your application's users. The `discount_usages`
    | table defines a foreign key to this table so redemptions can be
    | tracked per user and per-user usage limits can be enforced.
    |
    */
    'users' => [
        'table' => 'users',
        'model' => User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Discounts Table
    |--------------------------------------------------------------------------
    |
    | The table that stores discount definitions: percentage or fixed
    | amount, optional discount code, conditions, usage limits, stacking
    | behavior, and the start/expiry window.
    |
    */
    'discounts' => [
        'table' => 'discounts',
    ],

    /*
    |--------------------------------------------------------------------------
    | Discount Usages Table
    |--------------------------------------------------------------------------
    |
    | The table that records every discount redemption (who used which
    | discount, when, and the amount saved). It is used to enforce total
    | and per-user usage limits.
    |
    */
    'discount_usages' => [
        'table' => 'discount_usages',
    ],

    /*
    |--------------------------------------------------------------------------
    | Discountables Table
    |--------------------------------------------------------------------------
    |
    | The polymorphic pivot table that attaches discounts to your models,
    | such as products, categories, or cart items. This powers conditional
    | discounts that only apply to specific targets.
    |
    */
    'discountables' => [
        'table' => 'discountables',
    ],

    /*
    |--------------------------------------------------------------------------
    | Conditions
    |--------------------------------------------------------------------------
    |
    | The condition engine behind a discount's `conditions.rules` array.
    | `aliases` maps the short `type` stored in the database to a class
    | implementing `Binafy\LaravelDiscount\Contracts\DiscountCondition` —
    | add your own here to store them by name rather than by class name.
    |
    | `first_purchase` tells the bundled condition how to count a user's
    | purchases: the simple way is your Order model and its buyer column,
    | and `count_using` takes over when counting is your application's own
    | business (only paid orders count, or the number comes from elsewhere)
    | — name an invokable class here, or register a closure with
    | `FirstPurchaseCondition::countUsing()`. `category` tells the bundled
    | condition where to read an item's category, in dot notation (e.g.
    | "category.id" to follow a relation).
    |
    */
    'conditions' => [
        'aliases' => [
            'category' => CategoryCondition::class,
            'first_purchase' => FirstPurchaseCondition::class,
            'minimum_item_count' => MinimumItemCountCondition::class,
        ],

        'first_purchase' => [
            'model' => null,
            'column' => 'user_id',
            'count_using' => null,
        ],

        'category' => [
            'attribute' => 'category_id',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Code Generation
    |--------------------------------------------------------------------------
    |
    | Defaults used by the discount code generator. `length` is the number
    | of random characters, drawn from `characters` (ambiguous characters
    | such as 0/O and 1/I are excluded by default). When a `prefix` is
    | set, it is prepended to every code using the `separator`,
    | e.g. "SUMMER-8FJ2K9QW".
    |
    */
    'codes' => [
        'length' => 8,
        'characters' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
        'prefix' => null,
        'separator' => '-',
    ],
];
