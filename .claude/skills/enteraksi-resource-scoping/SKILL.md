---
name: enteraksi-resource-scoping
description: Resource scoping and authorization patterns for nested routes in Enteraksi LMS. Use when working with parent-child resource relationships, validating foreign keys, or preventing cross-resource manipulation.
triggers:
  - nested route
  - parent child
  - belongs to
  - assessment_id
  - course_id
  - question belongs
  - scoped validation
  - Rule::exists
  - cross-resource
  - authorization gap
  - ownership verification
  - route model binding
  - scopeBindings
---

# Enteraksi Resource Scoping Patterns

## When to Use This Skill

- Writing controllers for nested routes (e.g., `/courses/{course}/assessments/{assessment}/questions/{question}`)
- Validating that submitted IDs belong to the parent resource
- Preventing cross-resource manipulation attacks
- Working with bulk operations on child resources
- Debugging authorization issues where policy passes but wrong resource is affected

---

## The Core Problem: Unscoped Route Model Binding

Laravel's implicit route model binding does NOT automatically scope child resources to parents.

```php
// Route: /courses/{course}/assessments/{assessment}/questions/{question}
public function destroy(Course $course, Assessment $assessment, Question $question)
{
    Gate::authorize('update', [$assessment, $course]);

    // ❌ SECURITY BUG: $question could be from ANY assessment!
    // Route model binding just finds Question by ID, ignores $assessment
    $question->delete();  // Deletes question from wrong assessment!
}
```

### The Attack

```
1. Attacker owns Assessment A with Question 1
2. Victim owns Assessment B with Question 2
3. Attacker sends DELETE /courses/1/assessments/A/questions/2
4. Policy checks: Can attacker update Assessment A? YES
5. Question 2 (from Assessment B) gets deleted!
```

---

## Pattern 1: Ownership Verification

For single-resource operations, verify the child belongs to the parent.

### The Fix

```php
public function destroy(Course $course, Assessment $assessment, Question $question): RedirectResponse
{
    Gate::authorize('update', [$assessment, $course]);

    // ✅ SECURE: Verify question belongs to THIS assessment
    if ($question->assessment_id !== $assessment->id) {
        abort(404);
    }

    $question->delete();

    return redirect()
        ->route('assessments.edit', [$course, $assessment])
        ->with('success', 'Pertanyaan berhasil dihapus.');
}
```

### When to Use

- Single-item operations (show, edit, update, delete)
- Route model binding provides the child resource
- Simple parent-child relationship

---

## Pattern 2: Scoped Validation Rules

For form submissions with IDs, validate they belong to the parent at the validation layer.

### The Problem

```php
// ❌ BAD: Only checks ID exists, not ownership
$validated = $request->validate([
    'answers.*.question_id' => 'required|integer|exists:questions,id',
]);

foreach ($validated['answers'] as $answerData) {
    $question = Question::find($answerData['question_id']);  // Any question!
    // Process with wrong question...
}
```

### The Fix

```php
use Illuminate\Validation\Rule;

// ✅ SECURE: Scoped validation rule
$validated = $request->validate([
    'answers' => 'required|array',
    'answers.*.question_id' => [
        'required',
        'integer',
        Rule::exists('questions', 'id')->where('assessment_id', $assessment->id),
    ],
    'answers.*.answer_text' => 'nullable|string',
]);

// Now safe - validation already enforced ownership
foreach ($validated['answers'] as $answerData) {
    $question = $assessment->questions()->find($answerData['question_id']);
    // ...
}
```

### When to Use

- Array inputs with IDs (bulk operations)
- Form submissions referencing related resources
- Answer submissions, question reordering, etc.

---

## Pattern 3: Scoped Queries

Always use relationship queries instead of global `Model::find()`.

### The Problem

```php
// ❌ BAD: Finds ANY question in database
foreach ($validated['questions'] as $questionData) {
    if (isset($questionData['id']) && $questionData['id'] > 0) {
        $question = Question::find($questionData['id']);  // Not scoped!
        if ($question) {
            $question->update([...]);  // Updates wrong assessment's question
        }
    }
}
```

### The Fix

```php
// ✅ SECURE: Scoped to current assessment
foreach ($validated['questions'] as $questionData) {
    if (isset($questionData['id']) && $questionData['id'] > 0) {
        // Only finds questions belonging to THIS assessment
        $question = $assessment->questions()->find($questionData['id']);

        if (!$question) {
            continue;  // Skip - doesn't belong to this assessment
        }

        $question->update([...]);
    }
}
```

### When to Use

- Bulk update operations
- Any loop processing submitted IDs
- When you need to find a child by ID

---

## Pattern 4: Scoped Updates (Reorder, Bulk Status)

For operations that update multiple records, scope the update query itself.

### The Problem

```php
// ❌ BAD: Updates ANY question with that ID
foreach ($validated['question_ids'] as $index => $questionId) {
    Question::where('id', $questionId)->update(['order' => $index]);
}
```

### The Fix

```php
// ✅ SECURE: Scoped validation + scoped update
$validated = $request->validate([
    'question_ids' => 'required|array',
    'question_ids.*' => [
        'integer',
        Rule::exists('questions', 'id')->where('assessment_id', $assessment->id),
    ],
]);

foreach ($validated['question_ids'] as $index => $questionId) {
    // Double protection: scoped query even after scoped validation
    $assessment->questions()->where('id', $questionId)->update(['order' => $index]);
}
```

---

## Alternative: Route scopeBindings()

Laravel 9+ supports implicit binding scoping via route configuration.

```php
// routes/web.php
Route::prefix('courses/{course}/assessments/{assessment}')
    ->scopeBindings()  // ← Enables auto-scoping
    ->group(function () {
        Route::get('questions/{question}', [QuestionController::class, 'show']);
        // {question} is now automatically scoped to {assessment}
    });
```

### Requirements for scopeBindings()

1. Question model must have `assessment()` relationship defined
2. Relationship name must match route parameter (`assessment` → `assessment()`)
3. Only works for route model binding, NOT for IDs in request body

### Limitation

```php
// scopeBindings() helps here (route param)
public function show(Assessment $assessment, Question $question) { }

// scopeBindings() does NOT help here (request body IDs)
public function bulkUpdate(Request $request, Assessment $assessment) {
    // Still need manual scoping for $request->input('questions.*.id')
}
```

---

## Quick Reference: When to Use Each Pattern

| Scenario | Pattern | Example |
|----------|---------|---------|
| Single child via route | Ownership verification | `$question->assessment_id !== $assessment->id` |
| Array of IDs in request | Scoped validation rule | `Rule::exists()->where()` |
| Finding child by ID | Scoped query | `$assessment->questions()->find($id)` |
| Updating multiple children | Scoped update | `$assessment->questions()->where('id', $id)->update()` |
| Simple nested routes | scopeBindings() | Route group with `->scopeBindings()` |

---

## Common Vulnerable Patterns to Audit

```bash
# Find unscoped Model::find() in controllers
grep -r "::find(\$" app/Http/Controllers/ --include="*.php"

# Find validation rules that only check exists without scoping
grep -r "exists:.*,id" app/Http/Requests/ --include="*.php"

# Check for route groups without scopeBindings
grep -r "->group(" routes/ --include="*.php"
```

---

## Real Examples from Enteraksi

### AssessmentController::submitAttempt (Fixed)

```php
// Before: ❌ Any question from database
$question = Question::find($answerData['question_id']);

// After: ✅ Scoped validation + scoped query
$validated = $request->validate([
    'answers.*.question_id' => [
        'required',
        'integer',
        Rule::exists('questions', 'id')->where('assessment_id', $assessment->id),
    ],
]);

$question = $assessment->questions()->find($answerData['question_id']);
```

### QuestionController::destroy (Fixed)

```php
// Before: ❌ Route model binding not scoped
public function destroy(Course $course, Assessment $assessment, Question $question)
{
    Gate::authorize('update', [$assessment, $course]);
    $question->delete();  // Could delete any question!
}

// After: ✅ Ownership verification
public function destroy(Course $course, Assessment $assessment, Question $question)
{
    Gate::authorize('update', [$assessment, $course]);

    if ($question->assessment_id !== $assessment->id) {
        abort(404);
    }

    $question->delete();
}
```

### QuestionController::reorder (Fixed)

```php
// Before: ❌ No scoping
foreach ($validated['question_ids'] as $index => $questionId) {
    Question::where('id', $questionId)->update(['order' => $index]);
}

// After: ✅ Scoped validation + scoped update
$validated = $request->validate([
    'question_ids.*' => [
        'integer',
        Rule::exists('questions', 'id')->where('assessment_id', $assessment->id),
    ],
]);

foreach ($validated['question_ids'] as $index => $questionId) {
    $assessment->questions()->where('id', $questionId)->update(['order' => $index]);
}
```

---

## Testing Resource Scoping

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
        ->assertNotFound();

    expect(Question::find($otherQuestion->id))->not->toBeNull();
});
```

---

## Files to Reference

```
app/Http/Controllers/AssessmentController.php       # submitAttempt() - scoped validation
app/Http/Controllers/QuestionController.php         # bulkUpdate(), destroy(), reorder() - all patterns
plans/fixing/ROUND5_SECURITY_FIXES.md               # Full documentation of security issues
```
