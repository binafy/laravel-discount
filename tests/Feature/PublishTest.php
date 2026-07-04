<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::delete(config_path('laravel-discount.php'));

    foreach (File::glob(database_path('migrations/*_create_discount*.php')) as $file) {
        File::delete($file);
    }
    foreach (File::glob(database_path('migrations/*_create_discountables_table.php')) as $file) {
        File::delete($file);
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
        ->and($published->contains(fn ($name) => str_contains($name, 'create_discountables_table')))->toBeTrue();
});
