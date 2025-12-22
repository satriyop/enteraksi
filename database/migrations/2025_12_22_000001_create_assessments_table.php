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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->integer('time_limit_minutes')->nullable();
            $table->integer('passing_score')->default(70);
            $table->integer('max_attempts')->default(1);
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('show_correct_answers')->default(false);
            $table->boolean('allow_review')->default(true);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->enum('visibility', ['public', 'restricted', 'hidden'])->default('public');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};