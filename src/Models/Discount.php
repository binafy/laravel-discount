<?php

namespace Binafy\LaravelDiscount\Models;

use Binafy\LaravelDiscount\Enums\DiscountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $description
 * @property string|null $code
 * @property DiscountType $type
 * @property string $value
 * @property string|null $min_order_value
 * @property array|null $conditions
 * @property int|null $usage_limit
 * @property int|null $usage_limit_per_user
 * @property int $used_count
 * @property bool $is_stackable
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DiscountUsage> $usages
 *
 * @method static Builder|Discount active()
 * @method static Builder|Discount started()
 * @method static Builder|Discount notExpired()
 * @method static Builder|Discount withRemainingUsages()
 * @method static Builder|Discount valid()
 */
class Discount extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
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
    ];

    /**
     * The model's attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'used_count' => 0,
        'is_stackable' => false,
        'is_active' => true,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => DiscountType::class,
        'value' => 'decimal:2',
        'min_order_value' => 'decimal:2',
        'conditions' => 'array',
        'usage_limit' => 'integer',
        'usage_limit_per_user' => 'integer',
        'used_count' => 'integer',
        'is_stackable' => 'boolean',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('laravel-discount.discounts.table', 'discounts');
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * The redemption records of this discount.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(DiscountUsage::class);
    }

    /**
     * The models of the given type this discount is attached to
     * (e.g. products, categories, or cart items).
     */
    public function discountables(string $related): MorphToMany
    {
        return $this->morphedByMany(
            $related,
            'discountable',
            config('laravel-discount.discountables.table', 'discountables')
        )->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope the query to active discounts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope the query to discounts whose start date has passed (or that have no start date).
     */
    public function scopeStarted(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
        });
    }

    /**
     * Scope the query to discounts that have not expired yet (or that never expire).
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope the query to discounts that have not reached their total usage limit (or that have no limit).
     */
    public function scopeWithRemainingUsages(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query
                ->whereNull('usage_limit')
                ->orWhereColumn('used_count', '<', 'usage_limit');
        });
    }

    /**
     * Scope the query to discounts that are currently applicable:
     * active, inside their time window, and not exhausted.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->active()->started()->notExpired()->withRemainingUsages();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine if the discount's start date has passed (or is not set).
     */
    public function hasStarted(): bool
    {
        return is_null($this->starts_at) || $this->starts_at->isPast();
    }

    /**
     * Determine if the discount has expired.
     */
    public function isExpired(): bool
    {
        return ! is_null($this->expires_at) && $this->expires_at->isPast();
    }

    /**
     * Determine if the discount has reached its total usage limit.
     */
    public function usageLimitReached(): bool
    {
        return ! is_null($this->usage_limit) && $this->used_count >= $this->usage_limit;
    }

    /**
     * Determine if the discount is currently applicable: active, inside its time window, and not exhausted.
     */
    public function isValid(): bool
    {
        return $this->is_active
            && $this->hasStarted()
            && ! $this->isExpired()
            && ! $this->usageLimitReached();
    }
}
