  Top 3 Bad Things About This Codebase

  🔴 1. Excessive ->fresh() Abuse (~100+ occurrences)

  Location: ProgressTrackingService.php:65-71, and pervasive throughout services/tests

  // Inside a transaction, calling fresh() 5 TIMES for what should be 0
  return DB::transaction(function () use (...) {
      // ... do stuff ...
      ProgressUpdated::dispatch($enrollment->fresh(), $progress->fresh());  // 2 queries

      return new ProgressResult(
          progress: $progress->fresh(),                           // +1 query
          coursePercentage: new Percentage($enrollment->fresh()->progress_percentage),  // +1 query
          courseCompleted: $enrollment->fresh()->status === 'completed',  // +1 query
      );
  });

  Why this is bad:
  - 5 extra queries per lesson progress update - in a hot path called frequently
  - Inside a transaction, you already have the models updated. fresh() just re-fetches what you already have
  - This is cargo-cult programming - someone thought "we need fresh data" without understanding transactions
  - At scale (1000 learners, 50 lessons each), this adds 250,000 unnecessary queries

  What should happen: Remove fresh() calls inside transactions. If you need updated data, just use the model you already have after ->save() or ->update().

  ---
  🔴 2. Copy-Paste Code in PathProgressService (DRY Violation)

  Location: PathProgressService.php:110-139 vs PathProgressService.php:250-273

  // calculateProgressPercentage() - lines 110-139
  $totalRequired = $enrollment->courseProgress()
      ->whereHas('pathCourse', fn ($q) => $q->where('is_required', true))
      ->count();
  if ($totalRequired === 0) {
      $totalRequired = $enrollment->courseProgress()->count();
  }
  $completedRequired = $enrollment->courseProgress()
      ->where('state', CompletedCourseState::$name)
      ->when($totalRequired > 0, ...)
      ->count();

  // isPathCompleted() - lines 250-273
  $totalRequired = $enrollment->courseProgress()          // SAME QUERY
      ->whereHas('pathCourse', fn ($q) => $q->where('is_required', true))
      ->count();
  if ($totalRequired === 0) {                              // SAME LOGIC
      $totalRequired = $enrollment->courseProgress()->count();
  }
  $completedRequired = $enrollment->courseProgress()       // SAME QUERY
      ->where('state', CompletedCourseState::$name)
      ->when($totalRequired > 0, ...)
      ->count();

  Why this is bad:
  - Same 30 lines of code duplicated in the same file
  - Maintenance nightmare - change one, forget the other
  - Both are called from onCourseCompleted() - so the queries run twice unnecessarily
  - Shows lack of refactoring discipline

  What should happen: Extract to a private method that returns both values, or make isPathCompleted() use calculateProgressPercentage() >= 100.

  ---
  🔴 3. N+1 Query Traps Hidden in Model Accessors

  Location: Course.php:173-202

  // These cause N+1 queries when listing courses:
  public function getTotalLessonsAttribute(): int
  {
      return $this->lessons()->count();  // 1 query per course
  }

  public function getAverageRatingAttribute(): ?float
  {
      $avg = $this->ratings()->avg('rating');  // 1 query per course
      return $avg !== null ? round($avg, 1) : null;
  }

  public function getRatingsCountAttribute(): int
  {
      return $this->ratings()->count();  // 1 query per course
  }

  How it's used (Browse page):
  // CourseController returns courses to frontend
  // Frontend accesses: course.total_lessons, course.average_rating, course.ratings_count
  // With 12 courses per page = 36 extra queries PER PAGE LOAD

  Why this is bad:
  - Accessors silently execute queries - invisible to developers
  - Every time you access $course->total_lessons in a Blade/Vue template, boom - database hit
  - Makes performance profiling a nightmare
  - The "fix" via withCount() exists but isn't consistently used

  What should happen:
  - Replace with withCount(['lessons', 'ratings']) on queries
  - Access via $course->lessons_count (Laravel magic)
  - For average rating: compute once and store, or use withAvg('ratings', 'rating')

  ---
  Honorable Mentions
  ┌───────────────────────────────────┬───────────────────────────────────────────┬──────────────────────────────────────────────────────────────┐
  │               Issue               │                   Where                   │                            Impact                            │
  ├───────────────────────────────────┼───────────────────────────────────────────┼──────────────────────────────────────────────────────────────┤
  │ DTOs holding Eloquent models      │ ProgressResult holds LessonProgress model │ Tight coupling, serialization issues, memory leaks in queues │
  ├───────────────────────────────────┼───────────────────────────────────────────┼──────────────────────────────────────────────────────────────┤
  │ Hardcoded magic numbers           │ ->take(10) for ratings                    │ Should be config                                             │
  ├───────────────────────────────────┼───────────────────────────────────────────┼──────────────────────────────────────────────────────────────┤
  │ Inconsistent DTO naming           │ toResponse() vs toArray()                 │ Minor but shows lack of standards                            │
  ├───────────────────────────────────┼───────────────────────────────────────────┼──────────────────────────────────────────────────────────────┤
  │ Tests calling fresh() obsessively │ 100+ test files                           │ Teaches bad habits, slows tests                              │
  └───────────────────────────────────┴───────────────────────────────────────────┴──────────────────────────────────────────────────────────────┘
  ---
  ★ Insight ─────────────────────────────────────
  These aren't "someone made a mistake" problems - they're systematic pattern failures:
  1. The fresh() abuse suggests nobody profiled the database queries
  2. The copy-paste code suggests no code review caught it
  3. The accessor N+1 traps suggest missing performance testing

  The architecture (DDD, services, events) is solid. The implementation details are where it falls apart. This is classic "looks good on paper, performs terribly in production."
  ─────────────────────────────────────────────────