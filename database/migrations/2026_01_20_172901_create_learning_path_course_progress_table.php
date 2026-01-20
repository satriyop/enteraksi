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
        Schema::create('learning_path_course_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_enrollment_id')
                ->constrained('learning_path_enrollments')
                ->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('state')->default('locked')->comment('locked, available, in_progress, completed');
            $table->unsignedSmallInteger('position');
            $table->foreignId('course_enrollment_id')
                ->nullable()
                ->constrained('enrollments')
                ->nullOnDelete();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['learning_path_enrollment_id', 'course_id'], 'lp_enrollment_course_unique');
            $table->index(['learning_path_enrollment_id', 'state'], 'lp_enrollment_state_index');
            $table->index(['learning_path_enrollment_id', 'position'], 'lp_enrollment_position_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_path_course_progress');
    }
};
