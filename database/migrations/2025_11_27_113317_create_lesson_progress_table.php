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
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();

            // Progress tracking
            $table->unsignedInteger('current_page')->default(1);
            $table->unsignedInteger('total_pages')->nullable();
            $table->unsignedInteger('highest_page_reached')->default(1);
            $table->float('time_spent_seconds')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Pagination metadata (viewport info for text recalculation)
            $table->json('pagination_metadata')->nullable();

            $table->timestamps();

            // Constraints
            $table->unique(['enrollment_id', 'lesson_id']);
            $table->index(['enrollment_id', 'last_viewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
    }
};
