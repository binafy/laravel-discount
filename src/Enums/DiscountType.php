<?php

namespace Binafy\LaravelDiscount\Enums;

enum DiscountType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    /**
     * All case values, e.g. for the migration's enum column.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
