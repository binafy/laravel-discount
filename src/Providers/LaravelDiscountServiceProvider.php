<?php

namespace Binafy\LaravelDiscount\Providers;

use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Integrations\LaravelCart\CartDiscount;
use Binafy\LaravelDiscount\Support\DiscountCodeGenerator;
use Illuminate\Support\ServiceProvider;

class LaravelDiscountServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->mergeConfigFrom(__DIR__ . '/../../config/laravel-discount.php', 'laravel-discount');

        $this->app->singleton(DiscountManager::class);
        $this->app->singleton(DiscountCodeGenerator::class);

        // Optional binafy/laravel-cart integration
        if (class_exists(\Binafy\LaravelCart\Models\Cart::class)) {
            $this->app->singleton(CartDiscount::class);
        }
    }
}
