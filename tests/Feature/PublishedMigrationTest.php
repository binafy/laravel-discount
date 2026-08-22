<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

function publishDiscountMigrations(): void
{
    File::ensureDirectoryExists(database_path('migrations'));

    foreach (File::glob(__DIR__.'/../../database/migrations/*.php') as $index => $migration) {
        $name = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($migration));

        File::copy($migration, database_path('migrations/2099_01_01_00000'.$index.'_'.$name));
    }
}

afterEach(function () {
    foreach (File::glob(database_path('migrations/*_create_discount*.php')) as $file) {
        File::delete($file);
    }
});

test('published migrations are not run a second time from the package', function () {
    publishDiscountMigrations();

    // Boot a fresh application so the package registers its migrations while the
    // published copies are already in place, like a real application does.
    $this->refreshApplication();

    $this->artisan('migrate')->assertSuccessful();

    expect(Schema::hasTable('discounts'))->toBeTrue()
        ->and(Schema::hasTable('discount_usages'))->toBeTrue()
        ->and(Schema::hasTable('discountables'))->toBeTrue();

    $ran = DB::table('migrations')->pluck('migration')
        ->filter(fn ($migration) => str_contains($migration, 'create_discounts_table'));

    expect($ran)->toHaveCount(1);
});

test('the migrations copied by vendor:publish are not duplicated by the package', function () {
    $this->artisan('vendor:publish', ['--tag' => 'laravel-discount-migrations'])->assertSuccessful();

    $published = collect(File::glob(database_path('migrations/*_create_discounts_table.php')));

    // On Laravel 11 and above the published migration gets a fresh timestamp,
    // so it no longer shares a name with the package copy.
    expect($published)->toHaveCount(1)
        ->and(basename($published->first()))->not->toStartWith('2026_07_04_');

    $this->refreshApplication();

    $this->artisan('migrate')->assertSuccessful();

    expect(Schema::hasTable('discounts'))->toBeTrue()
        ->and(Schema::hasTable('discount_usages'))->toBeTrue()
        ->and(Schema::hasTable('discountables'))->toBeTrue();
})->skip(
    version_compare(Application::VERSION, '11.0.0', '<'),
    'Migrations are only re-stamped on publish from Laravel 11 onwards.'
);

test('package migrations are loaded when they are not published', function () {
    $this->refreshApplication();

    $this->artisan('migrate')->assertSuccessful();

    expect(Schema::hasTable('discounts'))->toBeTrue()
        ->and(Schema::hasTable('discount_usages'))->toBeTrue()
        ->and(Schema::hasTable('discountables'))->toBeTrue();
});
