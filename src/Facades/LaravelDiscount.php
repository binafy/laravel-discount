<?php

namespace Binafy\LaravelDiscount\Facades;

use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Models\Discount;
use Binafy\LaravelDiscount\Models\DiscountUsage;
use Binafy\LaravelDiscount\Support\DiscountResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static float calculate(Discount $discount, float $amount, int $quantity = 1)
 * @method static void validate(Discount $discount, float $orderAmount = 0, Model|int|null $user = null, string|null $sessionId = null)
 * @method static bool isValid(Discount $discount, float $orderAmount = 0, Model|int|null $user = null, string|null $sessionId = null)
 * @method static Discount findByCode(string $code)
 * @method static DiscountResult apply(Discount $discount, float $amount, Model|int|null $user = null, string|null $sessionId = null, int $quantity = 1)
 * @method static DiscountResult applyCode(string $code, float $amount, Model|int|null $user = null, string|null $sessionId = null, int $quantity = 1)
 * @method static DiscountResult applyMany(iterable $discounts, float $amount, Model|int|null $user = null, string|null $sessionId = null, int $quantity = 1)
 * @method static DiscountUsage redeem(Discount $discount, Model|int|null $user = null, float|null $amount = null, string|null $sessionId = null)
 * @method static string generateCode(string|null $prefix = null)
 * @method static Collection generateCodes(int $count, string|null $prefix = null)
 * @method static array|null matchingTier(Discount $discount, float $amount)
 *
 * @see DiscountManager
 */
class LaravelDiscount extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return DiscountManager::class;
    }
}
