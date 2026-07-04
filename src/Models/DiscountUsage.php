<?php

namespace Binafy\LaravelDiscount\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $discount_id
 * @property int|null $user_id Null for guest redemptions.
 * @property string|null $session_id Set for guest redemptions.
 * @property string|null $amount
 * @property \Illuminate\Support\Carbon $used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Discount $discount
 * @property-read \Illuminate\Database\Eloquent\Model $user
 */
class DiscountUsage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'discount_id',
        'user_id',
        'session_id',
        'amount',
        'used_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'used_at' => 'datetime',
    ];

    /**
     * Get the table name from the package config.
     */
    public function getTable(): string
    {
        return config('laravel-discount.discount_usages.table', 'discount_usages');
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * The discount that was redeemed.
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    /**
     * The user who redeemed the discount. The user model is resolved
     * from the package config, falling back to the auth provider.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            config('laravel-discount.users.model', config('auth.providers.users.model'))
        );
    }
}
