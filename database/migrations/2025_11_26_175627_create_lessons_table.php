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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_section_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->enum('content_type', ['text', 'video', 'audio', 'document', 'youtube', 'conference'])->default('text');
            $table->json('rich_content')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('conference_url')->nullable();
            $table->enum('conference_type', ['zoom', 'google_meet', 'other'])->nullable();
            $table->integer('estimated_duration_minutes')->nullable();
            $table->boolean('is_free_preview')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
