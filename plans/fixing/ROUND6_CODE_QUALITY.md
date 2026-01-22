# Round 6: Code Quality & Consistency Fixes

> DRY violations, database portability, and inconsistent patterns.

## Status: ✅ COMPLETED

| Priority | Issue | Status | Severity |
|----------|-------|--------|----------|
| 🔴 P1 | Duplicate grading logic with inconsistent scoping | ✅ Fixed | HIGH - DRY + Security |
| 🟡 P2 | Hardcoded MySQL error code 1062 | ✅ Fixed | MEDIUM - Portability |
| 🟢 P3 | Hardcoded state string instead of constant | ✅ Fixed | LOW - Code Quality |

### Summary of Changes

**P3 Fix:** Updated `PathEnrollmentService.php` to use `CompletedCourseState::$name` constant instead of hardcoded `'completed'` string.

**P2 Fix:** Created `app/Support/Helpers/DatabaseHelper.php` with database-agnostic `isDuplicateKeyException()` method. Updated `EnrollmentController` (2 locations) and `LearningPathEnrollmentController` (1 location) to use the helper.

**P1 Fix:** Updated `AssessmentController::submitGrade()` with scoped validation using `Rule::exists()->where('attempt_id', $attempt->id)`. Removed duplicate `grade()` and `submitGrade()` methods (~70 lines) from `QuestionController.php` as they were dead code with no routes pointing to them.

**Test Results:** All 1362 tests passing.

---

## 🔴 P1: Duplicate Grading Logic with Inconsistent Scoping

### Problem

Two separate `submitGrade` methods exist that do the same thing with different patterns:

1. **AssessmentController::submitGrade** (lines 386-432) - Uses unscoped `AttemptAnswer::find()`
2. **QuestionController::submitGrade** (lines 250-300) - Uses scoped `$attempt->answers()->find()`

### Files

- `app/Http/Controllers/AssessmentController.php:386-432`
- `app/Http/Controllers/QuestionController.php:250-300`

### Current Code Comparison

**AssessmentController (Weaker Pattern):**
```php
$validated = $request->validate([
    'grades' => 'required|array',
    'grades.*.answer_id' => 'required|exists:attempt_answers,id',  // ← Field name: answer_id
    'grades.*.score' => 'required|numeric|min:0',
    'grades.*.feedback' => 'nullable|string|max:1000',
]);

foreach ($validated['grades'] as $gradeData) {
    $answer = AttemptAnswer::find($gradeData['answer_id']);  // ← Unscoped!

    if ($answer && $answer->attempt_id === $attempt->id) {   // ← Manual check
        $answer->update([...]);
        $totalScore += $gradeData['score'];
    }
}
```

**QuestionController (Better Pattern):**
```php
$validated = $request->validate([
    'answers' => 'required|array',
    'answers.*.id' => 'required|exists:attempt_answers,id',  // ← Field name: id
    'answers.*.score' => 'required|integer|min:0',
    'answers.*.is_correct' => 'required|boolean',
    'answers.*.feedback' => 'nullable|string',
    'feedback' => 'nullable|string',
]);

foreach ($validated['answers'] as $answerData) {
    $answer = $attempt->answers()->find($answerData['id']);  // ← Scoped!

    if ($answer) {
        $answer->update([...]);
        $totalScore += $answerData['score'];
    }
}
```

### Issues

1. **DRY Violation** - Same business logic in two places
2. **Inconsistent field names** - `answer_id` vs `id`
3. **Inconsistent scoping** - One checks manually, other uses scoped query
4. **Different validation** - One has `is_correct`, other doesn't
5. **Different score type** - `numeric` vs `integer`
6. **One updates graded_by/graded_at per answer, other doesn't**

### Impact

- Bug fix in one place won't fix the other
- Developers confused about which endpoint to use
- Inconsistent API contracts

### Proposed Fix

**Option A: Delete QuestionController::submitGrade, keep AssessmentController version (Simpler)**

QuestionController already handles question CRUD. Grading is assessment-level functionality.

1. Remove `QuestionController::submitGrade()` and `grade()` methods
2. Update `AssessmentController::submitGrade()` to use scoped validation:

```php
public function submitGrade(Request $request, Course $course, Assessment $assessment, AssessmentAttempt $attempt): RedirectResponse
{
    Gate::authorize('grade', [$attempt, $assessment, $course]);

    $validated = $request->validate([
        'grades' => 'required|array',
        'grades.*.answer_id' => [
            'required',
            'integer',
            Rule::exists('attempt_answers', 'id')->where('attempt_id', $attempt->id),
        ],
        'grades.*.score' => 'required|integer|min:0',
        'grades.*.is_correct' => 'sometimes|boolean',
        'grades.*.feedback' => 'nullable|string|max:1000',
        'feedback' => 'nullable|string|max:2000',
    ]);

    $totalScore = 0;

    foreach ($validated['grades'] as $gradeData) {
        // Scoped query (validation already ensures ownership)
        $answer = $attempt->answers()->find($gradeData['answer_id']);

        if ($answer) {
            $answer->update([
                'score' => $gradeData['score'],
                'is_correct' => $gradeData['is_correct'] ?? ($gradeData['score'] > 0),
                'feedback' => $gradeData['feedback'] ?? null,
                'graded_by' => auth()->id(),
                'graded_at' => now(),
            ]);
            $totalScore += $gradeData['score'];
        }
    }

    // ... rest unchanged
}
```

3. Update routes to remove QuestionController grading routes
4. Update frontend to use single endpoint

**Option B: Extract to GradingService (Better for complex grading)**

If grading logic grows more complex, extract to a dedicated service.

```php
// app/Domain/Assessment/Services/GradingService.php
class GradingService
{
    public function gradeAttempt(
        AssessmentAttempt $attempt,
        array $grades,
        ?string $overallFeedback = null
    ): GradingResult {
        // Centralized grading logic
    }
}
```

### Recommendation

Option A for now - consolidate into AssessmentController. Extract to service later if needed.

---

## 🟡 P2: Hardcoded MySQL Error Code 1062

### Problem

Race condition fallback catches MySQL-specific error code, breaking database portability.

### Files

- `app/Http/Controllers/EnrollmentController.php:70-76, 203-209`
- `app/Http/Controllers/LearningPathEnrollmentController.php:150-158`

### Current Code (BAD)

```php
} catch (QueryException $e) {
    // Handle duplicate key violation (race condition fallback)
    if ($e->errorInfo[1] === 1062) {  // ← MySQL only!
        return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
    }
    throw $e;
}
```

### Why It's Bad

| Database | Duplicate Key Error Code |
|----------|-------------------------|
| MySQL | 1062 |
| PostgreSQL | 23505 |
| SQLite | 19 (SQLITE_CONSTRAINT) |
| SQL Server | 2627 |

The code ONLY works on MySQL. If you ever migrate to PostgreSQL, concurrent enrollment attempts will crash with 500 errors instead of friendly messages.

### Proposed Fix

**Option A: Helper Function (Recommended)**

Create a database-agnostic helper:

```php
// app/Support/DatabaseHelper.php
namespace App\Support;

use Illuminate\Database\QueryException;

class DatabaseHelper
{
    /**
     * Check if a QueryException is a unique constraint violation.
     * Works across MySQL, PostgreSQL, SQLite, and SQL Server.
     */
    public static function isDuplicateKeyException(QueryException $e): bool
    {
        $code = $e->errorInfo[1] ?? null;
        $sqlState = $e->errorInfo[0] ?? null;

        // MySQL: error code 1062
        if ($code === 1062) {
            return true;
        }

        // PostgreSQL: SQLSTATE 23505
        if ($sqlState === '23505') {
            return true;
        }

        // SQLite: error code 19 with "UNIQUE constraint failed"
        if ($code === 19 && str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
            return true;
        }

        // SQL Server: error code 2627
        if ($code === 2627) {
            return true;
        }

        // Fallback: check message for common patterns
        return str_contains($e->getMessage(), 'Duplicate entry')
            || str_contains($e->getMessage(), 'unique constraint')
            || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
```

**Usage:**

```php
use App\Support\DatabaseHelper;

} catch (QueryException $e) {
    if (DatabaseHelper::isDuplicateKeyException($e)) {
        return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
    }
    throw $e;
}
```

**Option B: Use Laravel's UniqueConstraintViolationException (Laravel 10+)**

Laravel 10+ provides `Illuminate\Database\UniqueConstraintViolationException`:

```php
use Illuminate\Database\UniqueConstraintViolationException;

} catch (UniqueConstraintViolationException $e) {
    return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
} catch (QueryException $e) {
    throw $e;
}
```

**Note:** This requires Laravel 10+. Check if the project uses this version.

### Recommendation

Option A (helper function) for maximum compatibility. Can be upgraded to Option B if on Laravel 10+.

---

## 🟢 P3: Hardcoded State String Instead of Constant

### Problem

One location uses hardcoded `'completed'` string instead of `CompletedCourseState::$name`.

### File

`app/Domain/LearningPath/Services/PathEnrollmentService.php:272`

### Current Code (BAD)

```php
$completedCourses = $enrollment->courseProgress()
    ->where('state', 'completed')  // ← Hardcoded string
    ->count();
```

### Elsewhere in Same File (GOOD)

```php
// Line 262
->where('state', CompletedCourseState::$name)  // ← Uses constant

// Line 270
->where('state', CompletedCourseState::$name)  // ← Uses constant
```

### Why It's Bad

1. **Inconsistency** - Same file uses both patterns
2. **Refactoring risk** - IDE "find usages" won't find the hardcoded string
3. **Typo risk** - `'complted'` would silently fail

### Proposed Fix

```php
// Before
$completedCourses = $enrollment->courseProgress()
    ->where('state', 'completed')
    ->count();

// After
$completedCourses = $enrollment->courseProgress()
    ->where('state', CompletedCourseState::$name)
    ->count();
```

### Additional Cleanup

Audit for similar issues:

```bash
# Find hardcoded state strings in services
grep -rn "where('state', '" app/Domain/
grep -rn "where('status', '" app/Domain/
```

---

## Implementation Order

1. **P3 First** (5 min) - Trivial fix, immediate improvement
2. **P2 Second** (30 min) - Create helper, update 3 catch blocks
3. **P1 Third** (1-2 hrs) - Consolidate grading endpoints, may need frontend updates

---

## Testing Requirements

### P1 Tests

```php
it('grades attempt answers with scoped validation', function () {
    $instructor = User::factory()->contentManager()->create();
    $course = Course::factory()->published()->create(['user_id' => $instructor->id]);
    $assessment = Assessment::factory()->published()->for($course)->create();
    $question = Question::factory()->for($assessment)->create(['points' => 10]);

    $learner = User::factory()->learner()->create();
    Enrollment::factory()->active()->for($learner)->for($course)->create();

    $attempt = AssessmentAttempt::factory()
        ->submitted()
        ->for($assessment)
        ->for($learner)
        ->create();
    $answer = AttemptAnswer::factory()->for($attempt)->for($question)->create();

    // Create answer for DIFFERENT attempt
    $otherAttempt = AssessmentAttempt::factory()->for($assessment)->create();
    $otherAnswer = AttemptAnswer::factory()->for($otherAttempt)->for($question)->create();

    $this->actingAs($instructor)
        ->post(route('assessments.grade.submit', [$course, $assessment, $attempt]), [
            'grades' => [
                ['answer_id' => $otherAnswer->id, 'score' => 10],  // Wrong attempt's answer
            ],
        ])
        ->assertSessionHasErrors('grades.0.answer_id');  // Should fail validation
});

it('rejects answers from other attempts', function () {
    // Similar test but checking that answer is not modified
});
```

### P2 Tests

```php
it('handles duplicate enrollment gracefully on any database', function () {
    // This test verifies the helper works, not actual database behavior
    $mysqlException = new QueryException(
        'mysql',
        'INSERT INTO enrollments...',
        [],
        new \PDOException('Duplicate entry', 1062)
    );

    expect(DatabaseHelper::isDuplicateKeyException($mysqlException))->toBeTrue();
});
```

### P3 Tests

No new tests needed - existing tests cover state queries.

---

## Files to Modify

| File | Changes |
|------|---------|
| `app/Http/Controllers/AssessmentController.php` | Update submitGrade with scoped validation |
| `app/Http/Controllers/QuestionController.php` | Remove duplicate grade/submitGrade methods |
| `app/Support/DatabaseHelper.php` | Create new helper class |
| `app/Http/Controllers/EnrollmentController.php` | Use DatabaseHelper |
| `app/Http/Controllers/LearningPathEnrollmentController.php` | Use DatabaseHelper |
| `app/Domain/LearningPath/Services/PathEnrollmentService.php` | Use CompletedCourseState::$name |
| `routes/web.php` or `routes/assessments.php` | Remove QuestionController grading routes |

---

## Related Documentation

- `plans/fixing/ROUND5_SECURITY_FIXES.md` - Previous scoping fixes
- `.claude/skills/enteraksi-resource-scoping/SKILL.md` - Scoping patterns
- `.claude/skills/enteraksi-concurrency/SKILL.md` - Race condition handling
