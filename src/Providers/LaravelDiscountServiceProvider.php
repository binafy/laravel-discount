<?php

namespace Binafy\LaravelDiscount\Providers;

use Binafy\LaravelCart\Models\Cart;
use Binafy\LaravelDiscount\Console\Commands\GenerateDiscountCodesCommand;
use Binafy\LaravelDiscount\Console\Commands\PruneDiscountsCommand;
use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Integrations\LaravelCart\CartDiscount;
use Binafy\LaravelDiscount\Support\DiscountCodeGenerator;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class LaravelDiscountServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->mergeConfigFrom(__DIR__.'/../../config/laravel-discount.php', 'laravel-discount');

        $this->app->singleton(DiscountManager::class);
        $this->app->singleton(DiscountCodeGenerator::class);

        // Optional binafy/laravel-cart integration
        if (class_exists(Cart::class)) {
            $this->app->singleton(CartDiscount::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish Config
        $this->publishes([
            __DIR__.'/../../config/laravel-discount.php' => config_path('laravel-discount.php'),
        ], 'laravel-discount-config');

        // Publish Migrations
        if (version_compare(Application::VERSION, '11.0.0', '<')) {
            $this->publishes([
                __DIR__.'/../../database/migrations' => database_path('migrations'),
            ], 'laravel-discount-migrations');
        } else {
            $this->publishesMigrations([
                __DIR__.'/../../database/migrations' => database_path('migrations'),
            ], 'laravel-discount-migrations');
        }

        // Register Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDiscountCodesCommand::class,
                PruneDiscountsCommand::class,
            ]);
        }
    }
}
