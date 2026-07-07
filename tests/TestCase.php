<?php

namespace Tests;

use Binafy\LaravelDiscount\Facades\LaravelDiscount;
use Binafy\LaravelDiscount\Providers\LaravelDiscountServiceProvider;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Tests\Models\User;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    use WithLaravelMigrations;

    /**
     * Load package service provider.
     *
     * @param  Application  $app
     */
    protected function getPackageProviders($app): array
    {
        return [LaravelDiscountServiceProvider::class];
    }

    /**
     * Load package facade aliases.
     */
    protected function getPackageAliases($app): array
    {
        return [
            'LaravelDiscount' => LaravelDiscount::class,
        ];
    }

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        if (! method_exists($this, 'setUpWithLaravelMigrations')) {
            $this->loadLaravelMigrations();
        }
    }

    /**
     * Define environment setup.
     *
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Set default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set app key
        $app['config']->set('app.key', 'base64:'.base64_encode(
            Encrypter::generateKey(config()['app.cipher'])
        ));

        // Set user model
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('laravel-discount.users.model', User::class);
    }
}
