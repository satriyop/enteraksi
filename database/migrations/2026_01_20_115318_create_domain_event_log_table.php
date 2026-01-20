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
        Schema::create('domain_event_log', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_name', 100)->index();
            $table->string('aggregate_type', 50)->index();
            $table->unsignedBigInteger('aggregate_id')->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata');
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at');

            // Composite index for common queries
            $table->index(['aggregate_type', 'aggregate_id', 'occurred_at']);
            $table->index(['event_name', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_event_log');
    }
};
