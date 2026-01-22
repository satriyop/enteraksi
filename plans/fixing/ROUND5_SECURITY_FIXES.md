# Round 5: Security & Authorization Fixes

> Authorization gaps allowing cross-resource manipulation.

## Status: ✅ COMPLETED

| Priority | Issue | Status | Severity |
|----------|-------|--------|----------|
| 🔴 P0 | Question not scoped in submitAttempt | ✅ Fixed | SECURITY - Data Corruption |
| 🔴 P1 | Question ownership not verified in QuestionController | ✅ Fixed | SECURITY - Cross-Resource |
| 🟡 P2 | Dead code + inverted logic in hasValidInvitation | ✅ Fixed | CODE QUALITY |

---

## 🔴 P0: Question Not Scoped to Assessment in submitAttempt

### Problem

When submitting assessment answers, the question is fetched by ID without verifying it belongs to the current assessment. This allows cross-assessment answer injection.

### File

`app/Http/Controllers/AssessmentController.php:288-319`

### Current Code (BAD)

```php
foreach ($validated['answers'] as $answerData) {
    $question = Question::find($answerData['question_id']);  // ❌ Any question from DB!

    if (! $question) {
        continue;
    }

    // Creates answer and auto-grades without verifying question belongs to assessment
    $answer = $attempt->answers()->create([
        'question_id' => $question->id,  // ❌ Could be from different assessment
        'answer_text' => $answerData['answer_text'] ?? null,
        // ...
    ]);

    if (! $question->requiresManualGrading()) {
        $isCorrect = $this->autoGradeQuestion($question, $answerData['answer_text'] ?? '');
        // ❌ Grades against wrong question's correct answer
    }
}
```

### Attack Scenario

```
1. Learner starts attempt on Assessment A (easy quiz, 10 questions)
2. Learner knows Assessment B has same questions but different correct answers
3. Learner submits POST with question_ids from Assessment B
4. System auto-grades using Assessment B's correct answers
5. Learner gets 100% on Assessment A using Assessment B's answers
```

### Impact

- **Grade Fraud**: Learners can score points using questions from other assessments
- **Data Corruption**: Answers saved with wrong question references
- **Audit Trail Broken**: Answer records reference questions from different assessments

### Proposed Fix

**Option A: Validate in loop (Simple)**

```php
foreach ($validated['answers'] as $answerData) {
    // Scope question to current assessment
    $question = $assessment->questions()->find($answerData['question_id']);

    if (! $question) {
        continue;  // Skip questions not belonging to this assessment
    }

    // ... rest of logic
}
```

**Option B: Pre-validate all question IDs (Better)**

```php
// Get valid question IDs for this assessment
$validQuestionIds = $assessment->questions()->pluck('id')->toArray();

foreach ($validated['answers'] as $answerData) {
    if (! in_array($answerData['question_id'], $validQuestionIds)) {
        continue;  // Skip invalid questions
    }

    $question = Question::find($answerData['question_id']);
    // ... rest of logic
}
```

**Option C: Custom validation rule (Best)**

```php
$validated = $request->validate([
    'answers' => 'required|array',
    'answers.*.question_id' => [
        'required',
        'integer',
        Rule::exists('questions', 'id')->where('assessment_id', $assessment->id),
    ],
    // ...
]);
```

### Recommendation

Option C is cleanest - validation rule ensures only valid questions are accepted.

---

## 🔴 P1: Question Ownership Not Verified in QuestionController

### Problem

Multiple methods in QuestionController accept question IDs without verifying they belong to the current assessment.

### Files

`app/Http/Controllers/QuestionController.php`

### Affected Methods

#### 1. bulkUpdate() - Lines 99-109

```php
if (isset($questionData['id']) && $questionData['id'] > 0) {
    $question = Question::find($questionData['id']);  // ❌ Any question!
    if ($question) {
        $question->update([...]);  // Updates ANY question in database
    }
}
```

#### 2. reorder() - Lines 213-215

```php
foreach ($validated['question_ids'] as $index => $questionId) {
    Question::where('id', $questionId)->update(['order' => $index]);  // ❌ Any question!
}
```

#### 3. destroy() - Lines 192-194

```php
// $question comes from route model binding without scope
$question->delete();  // ❌ Deletes any question, not scoped to assessment
```

### Attack Scenario

```
1. Content Manager A owns Assessment X with Question 1
2. Content Manager B owns Assessment Y with Question 2
3. Manager B sends PUT to /courses/{courseA}/assessments/{assessmentX}/questions
   with body: { questions: [{ id: 2, question_text: "HACKED" }] }
4. Question 2 (belonging to Assessment Y) gets updated!
```

### Impact

- **Cross-Resource Manipulation**: Users can modify questions they don't own
- **Data Integrity**: Questions can be orphaned or corrupted
- **Authorization Bypass**: Policy checks assessment, but question isn't scoped

### Proposed Fix

#### Fix bulkUpdate()

```php
foreach ($validated['questions'] as $questionData) {
    if (isset($questionData['id']) && $questionData['id'] > 0) {
        // Scope to current assessment
        $question = $assessment->questions()->find($questionData['id']);

        if (! $question) {
            continue;  // Skip questions not belonging to this assessment
        }

        $question->update([...]);
    }
    // ... rest of logic
}
```

#### Fix reorder()

```php
// Validate all question IDs belong to this assessment
$validQuestionIds = $assessment->questions()->pluck('id')->toArray();

$validated = $request->validate([
    'question_ids' => 'required|array',
    'question_ids.*' => [
        'integer',
        Rule::in($validQuestionIds),  // Must belong to this assessment
    ],
]);

foreach ($validated['question_ids'] as $index => $questionId) {
    $assessment->questions()->where('id', $questionId)->update(['order' => $index]);
}
```

#### Fix destroy()

```php
public function destroy(Course $course, Assessment $assessment, Question $question): RedirectResponse
{
    Gate::authorize('update', [$assessment, $course]);

    // Verify question belongs to this assessment
    if ($question->assessment_id !== $assessment->id) {
        abort(404);
    }

    $question->delete();
    // ...
}
```

### Alternative: Use Route Model Binding Scoping

Laravel 9+ supports implicit scoping. Add to route group:

```php
Route::prefix('courses/{course}/assessments')->scopeBindings()->group(function () {
    // {question} will now be scoped to {assessment}
});
```

But this requires the Question model to have a relationship defined that Laravel can use for scoping.

---

## 🟡 P2: Dead Code + Inverted Logic in hasValidInvitation

### Problem

The `hasValidInvitation()` method has inverted logic AND is never called.

### File

`app/Domain/Enrollment/Services/EnrollmentService.php:207-212`

### Current Code (BAD)

```php
protected function hasValidInvitation(User $user, Course $course): bool
{
    return $course->invitations()
        ->where('user_id', $user->id)
        ->where('status', 'accepted')  // ❌ Checks ACCEPTED, should be PENDING
        ->exists();
}
```

### Why It's Wrong

1. **Inverted Logic**: "valid invitation" for enrollment should mean PENDING (not yet used)
   - `accepted` = already used the invitation, already enrolled
   - `pending` = can use to enroll

2. **Dead Code**: This method is called by `canEnroll()` (line 141), but:
   - `EnrollmentController::store()` uses `Gate::authorize('enroll', $course)`
   - This goes to `CoursePolicy::enroll()` which correctly checks `status = 'pending'`
   - `EnrollmentService::canEnroll()` is NEVER called for course enrollments

3. **Inconsistency**: Two places define enrollment eligibility differently:
   - `CoursePolicy::enroll()` → checks `pending` ✅
   - `EnrollmentService::hasValidInvitation()` → checks `accepted` ❌

### Impact

- **Confusion**: Future developers may use the wrong method
- **Bug Risk**: If someone calls `EnrollmentService::canEnroll()`, it will give wrong results
- **Maintenance Debt**: Dead code that contradicts working code

### Proposed Fix

**Option A: Delete dead code (Recommended)**

```php
// Remove hasValidInvitation() method entirely
// Remove canEnroll() from EnrollmentService (it's not used)
// Keep only CoursePolicy::enroll() as single source of truth
```

**Option B: Fix and rename**

```php
/**
 * Check if user has a pending (usable) invitation.
 */
protected function hasPendingInvitation(User $user, Course $course): bool
{
    return $course->invitations()
        ->where('user_id', $user->id)
        ->where('status', 'pending')  // Fixed: check pending, not accepted
        ->exists();
}
```

### Recommendation

Option A - Delete the dead code. Authorization should live in Policies, not duplicated in Services.

---

## Implementation Order

1. **P0 First**: submitAttempt question scoping - prevents grade fraud
2. **P1 Second**: QuestionController methods - prevents cross-resource manipulation
3. **P2 Third**: Dead code cleanup - reduces confusion

---

## Testing Requirements

### P0 Tests

```php
it('rejects answers for questions not belonging to assessment', function () {
    $user = User::factory()->learner()->create();
    $course = Course::factory()->published()->create();
    $assessment = Assessment::factory()->published()->for($course)->create();
    $attempt = AssessmentAttempt::factory()->inProgress()->for($assessment)->for($user)->create();

    // Create question for DIFFERENT assessment
    $otherAssessment = Assessment::factory()->for($course)->create();
    $otherQuestion = Question::factory()->for($otherAssessment)->create();

    Enrollment::factory()->active()->for($user)->for($course)->create();

    $this->actingAs($user)
        ->post(route('assessments.attempt.submit', [$course, $assessment, $attempt]), [
            'answers' => [
                ['question_id' => $otherQuestion->id, 'answer_text' => 'test'],
            ],
        ])
        ->assertSuccessful();  // Request succeeds but...

    // Answer should NOT be created for the other question
    expect($attempt->answers()->where('question_id', $otherQuestion->id)->exists())->toBeFalse();
});
```

### P1 Tests

```php
it('cannot update questions from another assessment', function () {
    $cm = User::factory()->contentManager()->create();
    $course = Course::factory()->create(['user_id' => $cm->id]);
    $assessment = Assessment::factory()->for($course)->create(['user_id' => $cm->id]);

    // Question belonging to DIFFERENT assessment
    $otherAssessment = Assessment::factory()->for($course)->create(['user_id' => $cm->id]);
    $otherQuestion = Question::factory()->for($otherAssessment)->create();

    $this->actingAs($cm)
        ->put(route('assessments.questions.bulkUpdate', [$course, $assessment]), [
            'questions' => [
                ['id' => $otherQuestion->id, 'question_text' => 'HACKED', 'question_type' => 'short_answer', 'points' => 10],
            ],
        ]);

    // Other question should NOT be modified
    expect($otherQuestion->fresh()->question_text)->not->toBe('HACKED');
});

it('cannot delete questions from another assessment', function () {
    $cm = User::factory()->contentManager()->create();
    $course = Course::factory()->create(['user_id' => $cm->id]);
    $assessment = Assessment::factory()->for($course)->create(['user_id' => $cm->id]);

    $otherAssessment = Assessment::factory()->for($course)->create(['user_id' => $cm->id]);
    $otherQuestion = Question::factory()->for($otherAssessment)->create();

    $this->actingAs($cm)
        ->delete(route('assessments.questions.destroy', [$course, $assessment, $otherQuestion]))
        ->assertNotFound();  // Should 404, not delete

    expect(Question::find($otherQuestion->id))->not->toBeNull();
});
```

---

## Related Files to Audit

After fixing these, audit similar patterns:

```bash
# Find unscoped Model::find() calls in controllers
grep -r "::find(\$" app/Http/Controllers/ --include="*.php"

# Find validation rules that only check exists without scoping
grep -r "exists:.*,id" app/Http/Requests/ --include="*.php"

# Check for route groups without scopeBindings
grep -r "->group(" routes/ --include="*.php"
```
