<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(config('laravel-discount.discounts.table', 'discounts'), function (Blueprint $table) {
            // ISO 4217 code of the discount's amounts; null means any currency.
            $table->char('currency', 3)->nullable()->after('value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('laravel-discount.discounts.table', 'discounts'), function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
