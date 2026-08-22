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
        $this->loadUnpublishedMigrations();
        $this->mergeConfigFrom(__DIR__.'/../../config/laravel-discount.php', 'laravel-discount');

        $this->app->singleton(DiscountManager::class);
        $this->app->singleton(DiscountCodeGenerator::class);

        // Optional binafy/laravel-cart integration
        if (class_exists(Cart::class)) {
            $this->app->singleton(CartDiscount::class);
        }
    }

    /**
     * Register the package migrations that have not been published.
     *
     * When the migrations are published, `vendor:publish` copies them into the
     * application's `database/migrations` directory, on Laravel 11+ with a
     * fresh timestamp. Loading the package copies too would run the same
     * migration a second time and fail with "table already exists".
     */
    protected function loadUnpublishedMigrations(): void
    {
        $this->callAfterResolving('migrator', function ($migrator) {
            foreach (glob(__DIR__.'/../../database/migrations/*.php') ?: [] as $migration) {
                if (! $this->migrationIsPublished($migration)) {
                    $migrator->path($migration);
                }
            }
        });
    }

    /**
     * Determine if the given package migration has been published to the application.
     */
    protected function migrationIsPublished(string $migration): bool
    {
        $name = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($migration, '.php'));

        return (bool) (glob(database_path('migrations').DIRECTORY_SEPARATOR.'*_'.$name.'.php') ?: []);
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
