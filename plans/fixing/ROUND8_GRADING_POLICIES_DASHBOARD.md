# Round 8: Grading Strategies, Policy Performance, Dashboard Cleanup

> Controller bypasses domain strategies, policy N+1 queries, transformation duplication.

## Status: ✅ COMPLETED

| Priority | Issue | Status | Severity |
|----------|-------|--------|----------|
| 🔴 P1 | Controller bypasses domain grading strategies | ✅ Fixed | HIGH - Bug + Architecture |
| 🔴 P1 | Policy N+1 queries in view/attempt methods | ✅ Fixed | HIGH - Performance |
| 🟡 P2 | Dashboard transformation duplication | ✅ Fixed | MEDIUM - Architecture |

---

## 🔴 P1: Controller Bypasses Domain Grading Strategies

### Problem

`AssessmentController::autoGradeQuestion()` is a broken implementation that ignores the proper domain grading strategies. Multiple choice and short answer questions are NEVER auto-graded correctly.

### Files

- `app/Http/Controllers/AssessmentController.php:443-462` (broken method)
- `app/Domain/Assessment/Strategies/*.php` (ignored strategies)
- `app/Domain/Assessment/Services/GradingStrategyResolver.php` (not used)

### Current Code (BROKEN)

```php
// AssessmentController.php:443-462
protected function autoGradeQuestion(Question $question, string $answerText): bool
{
    if ($question->isTrueFalse()) {
        return strtolower(trim($answerText)) === 'true' || strtolower(trim($answerText)) === 'benar';
    }

    if ($question->isMultipleChoice()) {
        // This would be handled differently in the actual implementation
        return false;  // ← BUG: ALWAYS RETURNS FALSE!
    }

    if ($question->isShortAnswer()) {
        return false;  // ← BUG: ALWAYS RETURNS FALSE!
    }

    return false;
}
```

### Existing Domain Strategies (IGNORED)

```
app/Domain/Assessment/Strategies/
├── MultipleChoiceGradingStrategy.php  # 68 lines, partial credit support
├── TrueFalseGradingStrategy.php       # Proper implementation
├── ShortAnswerGradingStrategy.php     # Proper implementation
└── ManualGradingStrategy.php          # For essay questions
```

### Proposed Fix

**Step 1: Inject GradingStrategyResolver into controller**

```php
// AssessmentController.php
use App\Domain\Assessment\Contracts\GradingStrategyResolverContract;

class AssessmentController extends Controller
{
    public function __construct(
        protected GradingStrategyResolverContract $gradingResolver
    ) {}
}
```

**Step 2: Replace broken method with strategy delegation**

```php
// Replace autoGradeQuestion() entirely
protected function gradeQuestion(Question $question, mixed $answer): GradingResult
{
    $strategy = $this->gradingResolver->resolve($question);
    return $strategy->grade($question, $answer);
}
```

**Step 3: Update submitAttempt() to use GradingResult**

```php
// In submitAttempt() - lines 313-324
foreach ($validated['answers'] as $answerData) {
    $question = $assessment->questions()->find($answerData['question_id']);
    if (! $question) continue;

    // Create answer record
    $answer = $attempt->answers()->create([
        'question_id' => $question->id,
        'answer_text' => $answerData['answer_text'] ?? null,
        'selected_option_ids' => $answerData['selected_options'] ?? null, // Add for MC
        'file_path' => $filePath,
    ]);

    // Use strategy pattern instead of broken autoGradeQuestion()
    if (! $question->requiresManualGrading()) {
        $result = $this->gradeQuestion($question, $answerData['answer_text'] ?? $answerData['selected_options'] ?? '');

        $answer->update([
            'is_correct' => $result->isCorrect,
            'score' => $result->score,
            'feedback' => $result->feedback,
        ]);

        $totalScore += $result->score;
    }
}
```

**Step 4: Update validation to accept selected_options for MC**

```php
// In submitAttempt() validation
$validated = $request->validate([
    'answers' => 'required|array',
    'answers.*.question_id' => [
        'required',
        'integer',
        Rule::exists('questions', 'id')->where('assessment_id', $assessment->id),
    ],
    'answers.*.answer_text' => 'nullable|string',
    'answers.*.selected_options' => 'nullable|array',  // Add for multiple choice
    'answers.*.selected_options.*' => 'integer',
    'answers.*.file' => 'nullable|file|max:10240',
]);
```

### Testing

```php
it('auto-grades multiple choice questions using strategy', function () {
    $assessment = Assessment::factory()->published()->create();
    $question = Question::factory()
        ->for($assessment)
        ->multipleChoice()
        ->withCorrectOption('Option A')
        ->create();

    $attempt = AssessmentAttempt::factory()
        ->for($assessment)
        ->inProgress()
        ->create();

    $this->actingAs($attempt->user)
        ->post(route('assessments.submit', [$assessment->course, $assessment, $attempt]), [
            'answers' => [[
                'question_id' => $question->id,
                'selected_options' => [$question->options->first()->id],
            ]],
        ]);

    expect($attempt->fresh()->answers->first()->is_correct)->toBeTrue();
});
```

---

## 🔴 P1: Policy N+1 Queries in View/Attempt Methods

### Problem

We fixed `CoursePolicy::enroll()` in Round 7, but `view()` and other policy methods still run database queries.

### Files

| File | Method | Lines | Queries |
|------|--------|-------|---------|
| `CoursePolicy.php` | `view()` | 36, 47 | 2 |
| `AssessmentPolicy.php` | `view()` | 27 | 1 |
| `AssessmentPolicy.php` | `attempt()` | 56 | 1 |
| `CourseRatingPolicy.php` | `create()` | 18, 26 | 2 |

### Current Code (BAD)

```php
// CoursePolicy::view() - Lines 23-51
public function view(User $user, Course $course): bool
{
    if ($user->canManageCourses()) return true;
    if ($course->user_id === $user->id) return true;

    // QUERY #1
    if ($user->enrollments()->where('course_id', $course->id)->exists()) {
        return true;
    }

    if ($course->isPublished() && $course->visibility === 'public') {
        return true;
    }

    // QUERY #2
    if ($course->isPublished() && $course->visibility === 'restricted') {
        return $user->courseInvitations()
            ->where('course_id', $course->id)
            ->where('status', 'pending')
            ->exists();
    }

    return false;
}
```

### Proposed Fix

**Step 1: Extend EnrollmentContext to support view()**

```php
// app/Domain/Enrollment/DTOs/EnrollmentContext.php
readonly class EnrollmentContext
{
    public function __construct(
        public bool $isActivelyEnrolled,
        public bool $hasPendingInvitation,
        public bool $hasAnyEnrollment = false,  // Add for view()
    ) {}

    public static function for(User $user, Course $course): self
    {
        $enrollment = $user->enrollments()
            ->where('course_id', $course->id)
            ->first(['id', 'status']);

        return new self(
            isActivelyEnrolled: $enrollment?->status === 'active',
            hasPendingInvitation: $user->courseInvitations()
                ->where('course_id', $course->id)
                ->where('status', 'pending')
                ->exists(),
            hasAnyEnrollment: $enrollment !== null,
        );
    }
}
```

**Step 2: Update CoursePolicy::view() to accept optional context**

```php
// CoursePolicy.php
public function view(User $user, Course $course, ?EnrollmentContext $context = null): bool
{
    if ($user->canManageCourses()) return true;
    if ($course->user_id === $user->id) return true;

    // Use context if provided, otherwise query (fallback for Blade @can)
    $hasEnrollment = $context?->hasAnyEnrollment
        ?? $user->enrollments()->where('course_id', $course->id)->exists();

    if ($hasEnrollment) {
        return true;
    }

    if ($course->isPublished() && $course->visibility === 'public') {
        return true;
    }

    if ($course->isPublished() && $course->visibility === 'restricted') {
        $hasInvitation = $context?->hasPendingInvitation
            ?? $user->courseInvitations()
                ->where('course_id', $course->id)
                ->where('status', 'pending')
                ->exists();
        return $hasInvitation;
    }

    return false;
}
```

**Step 3: Update controllers to pass context**

```php
// CourseController::show()
$context = EnrollmentContext::for($user, $course);
Gate::authorize('view', [$course, $context]);
```

**Step 4: Apply same pattern to AssessmentPolicy and CourseRatingPolicy**

Create `AssessmentContext` DTO:

```php
// app/Domain/Assessment/DTOs/AssessmentContext.php
readonly class AssessmentContext
{
    public function __construct(
        public bool $isEnrolledInCourse,
    ) {}

    public static function for(User $user, Course $course): self
    {
        return new self(
            isEnrolledInCourse: $user->enrollments()
                ->where('course_id', $course->id)
                ->exists(),
        );
    }
}
```

### Testing

```php
it('view policy uses context without extra queries', function () {
    $course = Course::factory()->published()->create();
    $user = User::factory()->learner()->create();

    $context = EnrollmentContext::fromData(
        isActivelyEnrolled: false,
        hasPendingInvitation: false,
        hasAnyEnrollment: true,
    );

    DB::enableQueryLog();
    $result = $user->can('view', [$course, $context]);
    expect(DB::getQueryLog())->toHaveCount(0);
    expect($result)->toBeTrue();
});
```

---

## 🟡 P2: Dashboard Transformation Duplication

### Problem

`LearnerDashboardController` has 4 identical `.map()` transformations across 124 lines.

### File

`app/Http/Controllers/LearnerDashboardController.php`

### Current Code (DUPLICATED)

```php
// Lines 29-40: Featured courses
->map(fn($course) => [
    'id' => $course->id,
    'title' => $course->title,
    'slug' => $course->slug,
    'short_description' => $course->short_description,
    'thumbnail_path' => $course->thumbnail_url,
    'difficulty_level' => $course->difficulty_level,
    'duration' => $course->duration,
    'instructor' => $course->user->name,
    'category' => $course->category?->name,
    'enrollments_count' => $course->enrollments_count,
]);

// Lines 48-64: My learning (SAME TRANSFORMATION)
// Lines 73-89: Invited courses (SAME TRANSFORMATION)
// Lines 105-116: Browse courses (SAME TRANSFORMATION)
```

### Proposed Fix

**Step 1: Create API Resources**

```php
// app/Http/Resources/Dashboard/DashboardCourseResource.php
namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardCourseResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'thumbnail_path' => $this->thumbnail_url,
            'difficulty_level' => $this->difficulty_level,
            'duration' => $this->duration,
            'instructor' => $this->whenLoaded('user', fn () => $this->user->name),
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'enrollments_count' => $this->enrollments_count ?? null,
        ];
    }
}
```

```php
// app/Http/Resources/Dashboard/DashboardEnrollmentResource.php
namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardEnrollmentResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course->id,
            'title' => $this->course->title,
            'slug' => $this->course->slug,
            'short_description' => $this->course->short_description,
            'thumbnail_path' => $this->course->thumbnail_url,
            'difficulty_level' => $this->course->difficulty_level,
            'duration' => $this->course->duration,
            'instructor' => $this->course->user->name,
            'category' => $this->course->category?->name,
            'progress_percentage' => $this->progress_percentage,
            'enrolled_at' => $this->enrolled_at->toDateTimeString(),
            'last_lesson_id' => $this->last_lesson_id,
            'lessons_count' => $this->course->lessons_count,
            'status' => $this->status,
        ];
    }
}
```

```php
// app/Http/Resources/Dashboard/DashboardInvitationResource.php
namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardInvitationResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course->id,
            'title' => $this->course->title,
            'slug' => $this->course->slug,
            'short_description' => $this->course->short_description,
            'thumbnail_path' => $this->course->thumbnail_url,
            'difficulty_level' => $this->course->difficulty_level,
            'duration' => $this->course->duration,
            'instructor' => $this->course->user->name,
            'category' => $this->course->category?->name,
            'lessons_count' => $this->course->lessons_count,
            'invited_by' => $this->inviter->name,
            'message' => $this->message,
            'invited_at' => $this->created_at->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
        ];
    }
}
```

**Step 2: Update controller to use resources**

```php
// LearnerDashboardController.php
use App\Http\Resources\Dashboard\DashboardCourseResource;
use App\Http\Resources\Dashboard\DashboardEnrollmentResource;
use App\Http\Resources\Dashboard\DashboardInvitationResource;

public function __invoke(): Response
{
    $user = Auth::user();

    if ($user->role !== 'learner') {
        abort(403);
    }

    // Featured courses
    $featuredCourses = Course::query()
        ->published()
        ->visible()
        ->with(['user:id,name', 'category:id,name'])
        ->withCount('enrollments')
        ->orderByDesc('enrollments_count')
        ->limit(5)
        ->get();

    // My learning
    $myLearning = $user->enrollments()
        ->with(['course' => fn($q) => $q->with(['user:id,name', 'category:id,name'])->withCount('lessons')])
        ->whereIn('status', ['active', 'completed'])
        ->orderByDesc('updated_at')
        ->get();

    // Invited courses
    $invitedCourses = $user->pendingInvitations()
        ->with([
            'course' => fn($q) => $q->with(['user:id,name', 'category:id,name'])->withCount('lessons'),
            'inviter:id,name',
        ])
        ->get();

    // Browse courses
    $enrolledCourseIds = $user->enrollments()->pluck('course_id')->toArray();
    $invitedCourseIds = $user->pendingInvitations()->pluck('course_id')->toArray();
    $excludeIds = array_merge($enrolledCourseIds, $invitedCourseIds);

    $browseCourses = Course::query()
        ->published()
        ->visible()
        ->when(count($excludeIds) > 0, fn($q) => $q->whereNotIn('id', $excludeIds))
        ->with(['user:id,name', 'category:id,name'])
        ->withCount('enrollments')
        ->orderByDesc('created_at')
        ->limit(12)
        ->get();

    return Inertia::render('learner/Dashboard', [
        'featuredCourses' => DashboardCourseResource::collection($featuredCourses),
        'myLearning' => DashboardEnrollmentResource::collection($myLearning),
        'invitedCourses' => DashboardInvitationResource::collection($invitedCourses),
        'browseCourses' => DashboardCourseResource::collection($browseCourses),
    ]);
}
```

### Result

| Metric | Before | After |
|--------|--------|-------|
| Controller lines | 124 | ~60 |
| Transform duplications | 4 | 0 |
| Reusable resources | 0 | 3 |

---

## Implementation Order

1. **P1: Grading Strategies** (4 hours) - Bug fix, critical for assessment functionality
2. **P1: Policy Queries** (3 hours) - Extend EnrollmentContext, update 4 policies
3. **P2: Dashboard Resources** (2 hours) - Create 3 resources, simplify controller

---

## Files to Modify

### P1: Grading Strategies

| File | Changes |
|------|---------|
| `app/Http/Controllers/AssessmentController.php` | Inject resolver, replace autoGradeQuestion() |
| `app/Http/Requests/Assessment/SubmitAttemptRequest.php` | Add selected_options validation (if separate) |

### P1: Policy Queries

| File | Changes |
|------|---------|
| `app/Domain/Enrollment/DTOs/EnrollmentContext.php` | Add hasAnyEnrollment property |
| `app/Domain/Assessment/DTOs/AssessmentContext.php` | Create new DTO |
| `app/Policies/CoursePolicy.php` | Update view() to accept optional context |
| `app/Policies/AssessmentPolicy.php` | Update view(), attempt() |
| `app/Policies/CourseRatingPolicy.php` | Update create() |
| `app/Http/Controllers/CourseController.php` | Pass context to view authorization |
| `app/Http/Controllers/AssessmentController.php` | Pass context to policies |

### P2: Dashboard Resources

| File | Changes |
|------|---------|
| `app/Http/Resources/Dashboard/DashboardCourseResource.php` | Create |
| `app/Http/Resources/Dashboard/DashboardEnrollmentResource.php` | Create |
| `app/Http/Resources/Dashboard/DashboardInvitationResource.php` | Create |
| `app/Http/Controllers/LearnerDashboardController.php` | Use resources |

---

## Testing Requirements

### P1: Grading Strategies

```php
it('uses MultipleChoiceGradingStrategy for MC questions', function () {
    // Verify strategy is used, not broken autoGradeQuestion
});

it('gives partial credit for partially correct MC answers', function () {
    // Verify partial credit from strategy works
});

it('grades true/false questions correctly', function () {
    // Verify TrueFalseGradingStrategy is used
});
```

### P1: Policy Queries

```php
it('view policy works with context (no queries)', function () {
    $context = EnrollmentContext::fromData(...);
    DB::enableQueryLog();
    $user->can('view', [$course, $context]);
    expect(DB::getQueryLog())->toHaveCount(0);
});

it('view policy falls back to query without context', function () {
    // For Blade @can compatibility
    expect($user->can('view', $course))->toBeTrue();
});
```

### P2: Dashboard Resources

```php
it('transforms course for dashboard', function () {
    $course = Course::factory()->create();
    $resource = new DashboardCourseResource($course->load(['user', 'category']));

    expect($resource->toArray(request()))->toHaveKeys([
        'id', 'title', 'slug', 'instructor', 'category'
    ]);
});
```

---

## Related Documentation

- `plans/fixing/ROUND7_PERFORMANCE_ARCHITECTURE.md` - Previous policy fix (enroll only)
- `.claude/skills/enteraksi-architecture/SKILL.md` - Policy Context DTO pattern
- `.claude/skills/enteraksi-strategies/SKILL.md` - Strategy pattern documentation
