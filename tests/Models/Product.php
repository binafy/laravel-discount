<?php

namespace Tests\Models;

use Binafy\LaravelCart\Cartable;
use Binafy\LaravelDiscount\Traits\HasDiscounts;
use Illuminate\Database\Eloquent\Model;

class Product extends Model implements Cartable
{
    use HasDiscounts;

    protected $table = 'products';

    protected $guarded = [];

    public function getPrice(): float
    {
        return (float) $this->price;
    }
}
