 🔴 #1: STATE MUTATION BUG (CRITICAL)

  File: app/Domain/LearningPath/Listeners/UpdatePathProgressOnCourseDrop.php:96-97

  // If path was completed, revert to active
  if ($pathEnrollment->isCompleted()) {
      $pathEnrollment->state = ActivePathState::$name;  // ❌ WRONG
      $pathEnrollment->completed_at = null;
  }
  $pathEnrollment->save();

  Problem: Direct string assignment to a Spatie state-casted column. The state column expects a state class instance, not a raw string.

  Impact:
  - After save(), the state is stored as raw string, not proper state object
  - $pathEnrollment->isActive() will return incorrect results or crash
  - $pathEnrollment->state->canAccessContent() → "Call to member function on string" error
  - Creates "zombie" enrollments with corrupted state

  Fix: Use proper state transition:
  $pathEnrollment->state->transitionTo(ActivePathState::class);

  ---
  🔴 #2: RACE CONDITION - Concurrent Enrollment (HIGH)

  File: app/Http/Controllers/EnrollmentController.php:31-52

  // Outside transaction
  $invitation = $user->courseInvitations()...->first();

  try {
      $this->enrollmentService->enroll($dto);  // Transaction starts HERE

      // Still outside the service transaction!
      if ($invitation) {
          $invitation->update(['status' => 'accepted'...]);
      }
  }

  Problem:
  1. Invitation lookup outside transaction
  2. Two concurrent requests pass canEnroll() check
  3. Second request hits UNIQUE (user_id, course_id) constraint
  4. User gets 500 error instead of "Already enrolled"
  5. Invitation marked "accepted" even if enrollment failed

  Impact:
  - Database exception leaks to user during traffic spikes
  - Invitation state inconsistent with enrollment state
  - No pessimistic locking prevents race

  Fix: Wrap entire flow with lockForUpdate():
  DB::transaction(function () use ($user, $course) {
      $invitation = $user->courseInvitations()
          ->where('course_id', $course->id)
          ->lockForUpdate()
          ->first();

      $this->enrollmentService->enroll($dto);
      $invitation?->update(['status' => 'accepted'...]);
  });

  ---
  🔴 #3: AUTHORIZATION LOGIC BUG - Blocks Enrolled Users (HIGH)

  File: app/Policies/CoursePolicy.php:44-47

  if ($course->isPublished() && $course->visibility === 'restricted') {
      return $user->courseInvitations()
          ->where('course_id', $course->id)
          ->where('status', 'pending')  // ❌ Only PENDING, not ACCEPTED
          ->exists();
  }

  Problem: Flow breakdown:
  1. User has pending invitation → Policy allows viewing ✓
  2. User accepts invitation → Enrollment created, invitation → accepted
  3. Now policy checks for pending → FALSE
  4. User gets 403 Forbidden on the course they just enrolled in!

  Impact:
  - Breaks the entire invitation workflow
  - Enrolled users locked out of restricted courses
  - False security: accepted invitations give LESS access than pending ones

  Fix: Check enrollment first, then invitation status:
  if ($course->isPublished() && $course->visibility === 'restricted') {
      // User is already enrolled
      if ($user->enrollments()->where('course_id', $course->id)->exists()) {
          return true;
      }

      // Has valid invitation (pending or accepted)
      return $user->courseInvitations()
          ->where('course_id', $course->id)
          ->whereIn('status', ['pending', 'accepted'])
          ->exists();
  }

  ---
  Summary
  ┌─────┬──────────────────────────────────────┬──────────┬─────────────────────┐
  │  #  │                Issue                 │ Severity │        Type         │
  ├─────┼──────────────────────────────────────┼──────────┼─────────────────────┤
  │ 1   │ State mutation via string assignment │ CRITICAL │ Data Corruption     │
  ├─────┼──────────────────────────────────────┼──────────┼─────────────────────┤
  │ 2   │ Race condition in enrollment         │ HIGH     │ Concurrency Bug     │
  ├─────┼──────────────────────────────────────┼──────────┼─────────────────────┤
  │ 3   │ Policy blocks enrolled users         │ HIGH     │ Authorization Logic │
  └─────┴──────────────────────────────────────┴──────────┴─────────────────────┘