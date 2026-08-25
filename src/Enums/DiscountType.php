<?php

namespace Binafy\LaravelDiscount\Enums;

enum DiscountType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case BuyXGetY = 'buy_x_get_y';
    case Tiered = 'tiered';
    case FreeShipping = 'free_shipping';

    /**
     * All case values, e.g. for the migration's enum column.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Whether the type is configured through the `conditions` column
     * rather than through the `value` column.
     */
    public function usesConditions(): bool
    {
        return match ($this) {
            self::BuyXGetY, self::Tiered => true,
            default => false,
        };
    }

    /**
     * Whether calculating the type needs the item quantity, on top of the amount.
     */
    public function needsQuantity(): bool
    {
        return $this === self::BuyXGetY;
    }

    /**
     * Whether the type deducts money from the amount. Free shipping does
     * not: it only raises a flag on the result.
     */
    public function deductsAmount(): bool
    {
        return $this !== self::FreeShipping;
    }
}
