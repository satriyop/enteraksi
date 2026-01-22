  Found 18 issues across the codebase. Here are the Top 3 Critical Issues:

  ---
  🔴 #1: N+1 Query Bomb in LearningPathEnrollmentController::transformProgress()

  Location: app/Http/Controllers/LearningPathEnrollmentController.php:320-361

  // Inside a loop - 3 queries PER COURSE!
  foreach ($courses as $course) {
      $courseModel = Course::find($course['course_id']);  // Query 1
      $lessonsCount = $courseModel->lessons()->count();   // Query 2

      $courseEnrollment = Enrollment::find($course['enrollment_id']);
      $completedLessons = $courseEnrollment->lessonProgress()
          ->where('is_completed', true)->count();         // Query 3
  }

  Impact: Learning path with 10 courses = 30+ queries per request

  ---
  🔴 #2: Missing Database Index

  Location: database/migrations/*_create_enrollments_table.php

  // Has this:
  $table->index(['user_id', 'status']);

  // MISSING this commonly-queried combo:
  $table->index(['course_id', 'status']);

  Impact: Every ->where('course_id', $id)->where('status', 'active') does full table scan. 50x slower at scale.

  ---
  🔴 #3: Assessment Model N+1 Accessors (Same pattern we just fixed)

  Location: app/Models/Assessment.php:93-101

  public function getTotalQuestionsAttribute(): int
  {
      return $this->questions()->count();  // N+1!
  }

  public function getTotalPointsAttribute(): int
  {
      return $this->questions()->sum('points');  // N+1!
  }

  ---
  Full Issue Summary
  ┌──────────┬───────────────────────────────────────┬──────────────────────────────┐
  │ Priority │                 Issue                 │            Impact            │
  ├──────────┼───────────────────────────────────────┼──────────────────────────────┤
  │ 🔴 P0    │ N+1 in transformProgress()            │ 30+ queries/request          │
  ├──────────┼───────────────────────────────────────┼──────────────────────────────┤
  │ 🔴 P0    │ Missing DB index (course_id, status)  │ 50x slower queries           │
  ├──────────┼───────────────────────────────────────┼──────────────────────────────┤
  │ 🔴 P1    │ Assessment model N+1 accessors        │ Same as Course issue         │
  ├──────────┼───────────────────────────────────────┼──────────────────────────────┤
  │ 🔴 P1    │ DTOs holding Eloquent models          │ Tight coupling, can't cache  │
  ├──────────┼───────────────────────────────────────┼──────────────────────────────┤
  │ 🟠 P1    │ Fat controller - AssessmentController │ Business logic in controller │
  ├──────────┼───────────────────────────────────────┼──────────────────────────────┤
  │ 🟠 P2    │ AssessmentPolicy admin bug            │ Admin can't update published │
  ├──────────┼───────────────────────────────────────┼──────────────────────────────┤
  │ 🟠 P2    │ EnrollmentService missing state check │ State machine bypass         │
  ├──────────┼───────────────────────────────────────┼──────────────────────────────┤
  │ 🟡 P3    │ Large Vue components (300+ lines)     │ Maintainability              │
  └──────────┴───────────────────────────────────────┴──────────────────────────────┘