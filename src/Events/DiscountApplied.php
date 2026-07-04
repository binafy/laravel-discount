<?php

namespace Binafy\LaravelDiscount\Events;

use Binafy\LaravelDiscount\Support\DiscountResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscountApplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DiscountResult $result,
        public Model|int|null $user = null,
    ) {
    }
}
