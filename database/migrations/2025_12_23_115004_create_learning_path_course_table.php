<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('learning_path_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_id')->constrained('learning_paths')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->integer('position')->default(0);
            $table->boolean('is_required')->default(true);
            $table->text('prerequisites')->nullable()->comment('JSON: conditions that must be met before this course can be started');
            $table->integer('min_completion_percentage')->nullable()->comment('Minimum percentage required to complete this course in the path');
            $table->timestamps();

            $table->unique(['learning_path_id', 'course_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('learning_path_course');
    }
};