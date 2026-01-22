<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This index optimizes queries that filter by course_id and status,
     * which is common in:
     * - Checking active enrollments for a course
     * - Counting completed enrollments per course
     * - Dashboard/reporting queries
     */
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index(['course_id', 'status'], 'enrollments_course_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('enrollments_course_status_index');
        });
    }
};
