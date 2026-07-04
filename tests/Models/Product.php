<?php

namespace Tests\Models;

use Binafy\LaravelDiscount\Traits\HasDiscounts;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasDiscounts;

    protected $table = 'products';

    protected $guarded = [];
}
