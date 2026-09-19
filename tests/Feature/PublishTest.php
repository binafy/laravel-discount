<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::delete(config_path('laravel-discount.php'));

    // Every package migration, whatever timestamp its published copy was given.
    foreach (File::glob(__DIR__.'/../../database/migrations/*.php') as $migration) {
        $name = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($migration));

        File::delete(File::glob(database_path('migrations/*_'.$name)));
    }
});

test('config is publishable with the laravel-discount-config tag', function () {
    $this->artisan('vendor:publish', ['--tag' => 'laravel-discount-config'])->assertSuccessful();

    expect(File::exists(config_path('laravel-discount.php')))->toBeTrue();
});

test('migrations are publishable with the laravel-discount-migrations tag', function () {
    $this->artisan('vendor:publish', ['--tag' => 'laravel-discount-migrations'])->assertSuccessful();

    $published = collect(File::files(database_path('migrations')))
        ->map(fn ($file) => $file->getFilename());

    expect($published->contains(fn ($name) => str_contains($name, 'create_discounts_table')))->toBeTrue()
        ->and($published->contains(fn ($name) => str_contains($name, 'create_discount_usages_table')))->toBeTrue()
        ->and($published->contains(fn ($name) => str_contains($name, 'create_discountables_table')))->toBeTrue()
        ->and($published->contains(fn ($name) => str_contains($name, 'add_currency_to_discounts_table')))->toBeTrue();
});
