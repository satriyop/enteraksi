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
        Schema::create('learning_path_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('learning_path_id')->constrained('learning_paths')->cascadeOnDelete();
            $table->string('state')->default('active')->comment('active, completed, dropped');
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->timestamp('enrolled_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dropped_at')->nullable();
            $table->string('drop_reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'learning_path_id']);
            $table->index(['user_id', 'state']);
            $table->index(['learning_path_id', 'state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_path_enrollments');
    }
};
