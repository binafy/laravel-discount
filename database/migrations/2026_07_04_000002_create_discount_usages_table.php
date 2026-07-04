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
        Schema::create(config('laravel-discount.discount_usages.table', 'discount_usages'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')
                ->constrained(config('laravel-discount.discounts.table', 'discounts'))
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained(config('laravel-discount.users.table', 'users'))
                ->cascadeOnDelete();
            $table->decimal('amount', 15, 2)->nullable();
            $table->timestamp('used_at');
            $table->timestamps();

            $table->index(['discount_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('laravel-discount.discount_usages.table', 'discount_usages'));
    }
};
