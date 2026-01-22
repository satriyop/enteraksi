<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Change prerequisites column from text to json for proper casting
     * via the LearningPathCourse pivot model.
     */
    public function up(): void
    {
        Schema::table('learning_path_course', function (Blueprint $table) {
            $table->json('prerequisites')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('learning_path_course', function (Blueprint $table) {
            $table->text('prerequisites')->nullable()->change();
        });
    }
};
