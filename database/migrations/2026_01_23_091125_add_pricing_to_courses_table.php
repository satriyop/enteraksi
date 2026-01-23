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
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('difficulty_level');
            $table->string('currency', 3)->default('IDR')->after('price');
            $table->boolean('is_paid')->default(false)->after('currency');
            $table->string('payment_gateway')->nullable()->after('is_paid');
            $table->json('pricing_tiers')->nullable()->after('payment_gateway');
            $table->timestamp('price_valid_until')->nullable()->after('pricing_tiers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'price',
                'currency',
                'is_paid',
                'payment_gateway',
                'pricing_tiers',
                'price_valid_until',
            ]);
        });
    }
};
