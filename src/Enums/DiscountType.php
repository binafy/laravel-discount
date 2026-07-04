<?php

namespace Binafy\LaravelDiscount\Enums;

enum DiscountType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
