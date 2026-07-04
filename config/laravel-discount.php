<?php

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
        'model' => \App\Models\User::class,
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
];
