<?php

namespace Tests;

use Binafy\LaravelDiscount\Providers\LaravelDiscountServiceProvider;
use Illuminate\Encryption\Encrypter;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * Load package service provider.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getPackageProviders($app): array
    {
        return [LaravelDiscountServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Set default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Set app key
        $app['config']->set('app.key', 'base64:'.base64_encode(
            Encrypter::generateKey(config()['app.cipher'])
        ));

        // Set user model
//        $app['config']->set('auth.providers.users.model', User::class);

        // Set user model for monitoring config
//        $app['config']->set('laravel-discount.user.model', User::class);
    }
}
