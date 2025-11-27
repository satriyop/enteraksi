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
        Schema::table('lesson_progress', function (Blueprint $table) {
            // Media progress tracking (video/youtube/audio)
            $table->unsignedInteger('media_position_seconds')->nullable()->after('time_spent_seconds');
            $table->unsignedInteger('media_duration_seconds')->nullable()->after('media_position_seconds');
            $table->decimal('media_progress_percentage', 5, 2)->default(0)->after('media_duration_seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->dropColumn([
                'media_position_seconds',
                'media_duration_seconds',
                'media_progress_percentage',
            ]);
        });
    }
};
