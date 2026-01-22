⏺ Round 5: Brutal Code Review - Top 3 Issues
  ┌─────┬────────────────────────────────────────────────────┬───────────────────────────────┬───────────────────┐
  │  #  │                       Issue                        │             File              │     Severity      │
  ├─────┼────────────────────────────────────────────────────┼───────────────────────────────┼───────────────────┤
  │ 1   │ Question Not Scoped to Assessment in submitAttempt │ AssessmentController.php:289  │ P0 - SECURITY     │
  ├─────┼────────────────────────────────────────────────────┼───────────────────────────────┼───────────────────┤
  │ 2   │ Question Ownership Not Verified in bulkUpdate      │ QuestionController.php:101    │ P1 - SECURITY     │
  ├─────┼────────────────────────────────────────────────────┼───────────────────────────────┼───────────────────┤
  │ 3   │ Dead Code + Inverted Logic in hasValidInvitation   │ EnrollmentService.php:207-212 │ P2 - CODE QUALITY │
  └─────┴────────────────────────────────────────────────────┴───────────────────────────────┴───────────────────┘
  ---
  🔴 P0: Question Not Scoped to Assessment (SECURITY VULNERABILITY)

  File: app/Http/Controllers/AssessmentController.php:289

  foreach ($validated['answers'] as $answerData) {
      $question = Question::find($answerData['question_id']);  // ❌ Any question!

      if (! $question) {
          continue;
      }
      // Auto-grades and saves answer for this question
  }

  Problem: Validation at line 276 uses exists:questions,id but doesn't verify the question belongs to THIS assessment. A learner could:
  1. Start attempt on Assessment A
  2. Submit answers with question_id from Assessment B
  3. Those answers get graded and saved

  Impact: Data corruption, grade fraud, cross-course answer injection.

  ---
  🔴 P1: Question Ownership Not Verified in bulkUpdate (SECURITY)

  File: app/Http/Controllers/QuestionController.php:101

  if (isset($questionData['id']) && $questionData['id'] > 0) {
      $question = Question::find($questionData['id']);  // ❌ Any question!
      if ($question) {
          $question->update([...]);  // Updates ANY question, not just this assessment's
      }
  }

  Problem: Validation checks exists:questions,id but NOT assessment_id = current_assessment. A content manager could update questions belonging to another instructor's assessment.

  Same issue in:
  - reorder() line 214 - updates any question's order
  - destroy() line 194 - deletes any question (no assessment scope)

  ---
  🟡 P2: Dead Code + Inverted Logic (CODE QUALITY)

  File: app/Domain/Enrollment/Services/EnrollmentService.php:207-212

  protected function hasValidInvitation(User $user, Course $course): bool
  {
      return $course->invitations()
          ->where('user_id', $user->id)
          ->where('status', 'accepted')  // ❌ Checks ACCEPTED, not PENDING
          ->exists();
  }

  Problems:
  1. Inverted logic: "valid invitation" should check pending, not accepted
  2. Dead code: canEnroll() in EnrollmentService is never called for courses
  3. Inconsistent: CoursePolicy::enroll() correctly checks status = 'pending'

  Context: The controller uses Gate::authorize('enroll') → CoursePolicy::enroll() (correct), so this service method is never invoked. But it's confusing and a bug waiting to
  happen.