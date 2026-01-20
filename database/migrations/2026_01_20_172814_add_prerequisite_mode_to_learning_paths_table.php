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
        Schema::table('learning_paths', function (Blueprint $table) {
            $table->string('prerequisite_mode')
                ->default('sequential')
                ->after('thumbnail_url')
                ->comment('sequential, immediate_previous, none');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('learning_paths', function (Blueprint $table) {
            $table->dropColumn('prerequisite_mode');
        });
    }
};
