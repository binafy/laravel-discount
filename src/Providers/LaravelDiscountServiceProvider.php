<?php

namespace Binafy\LaravelDiscount\Providers;

use Illuminate\Support\ServiceProvider;

class LaravelDiscountServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-discount.php', 'laravel-discount');
    }
}
