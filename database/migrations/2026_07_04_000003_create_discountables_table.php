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
        Schema::create(config('laravel-discount.discountables.table', 'discountables'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')
                ->constrained(config('laravel-discount.discounts.table', 'discounts'))
                ->cascadeOnDelete();
            $table->morphs('discountable');
            $table->timestamps();

            $table->unique(['discount_id', 'discountable_type', 'discountable_id'], 'discountables_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('laravel-discount.discountables.table', 'discountables'));
    }
};
