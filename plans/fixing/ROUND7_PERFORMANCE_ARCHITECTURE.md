# Round 7: Performance & Architecture Fixes

> N+1 queries, policy performance, controller bloat.

## Status: ✅ COMPLETED

| Priority | Issue | Status | Severity |
|----------|-------|--------|----------|
| 🔴 P1 | Nested N+1 query in duration calculation | ✅ Done | HIGH - Performance |
| 🔴 P1 | Database queries inside authorization policies | ✅ Done | HIGH - Performance |
| 🟡 P2 | Controller bloat with data transformation | ✅ Done | MEDIUM - Architecture |

---

## 🔴 P1: Nested N+1 Query in Duration Calculation

### Problem

`Course::calculateEstimatedDuration()` uses nested foreach loops that trigger N+1 queries.

### Files

- `app/Models/Course.php:242-253`
- `app/Models/CourseSection.php:66-68`

### Current Code (BAD)

```php
// app/Models/Course.php:242-253
public function calculateEstimatedDuration(): int
{
    $totalMinutes = 0;

    foreach ($this->sections as $section) {           // 1 query for N sections
        foreach ($section->lessons as $lesson) {       // N queries for M lessons each
            $totalMinutes += $lesson->estimated_duration_minutes ?? 0;
        }
    }

    return $totalMinutes;
}
```

### Query Count Analysis

| Course Size | Sections | Lessons/Section | Total Queries |
|-------------|----------|-----------------|---------------|
| Small | 5 | 3 | 6 queries |
| Medium | 10 | 5 | 11 queries |
| Large | 20 | 10 | 21 queries |

### Callers (9 locations)

```
app/Http/Controllers/LessonController.php:117-118    # store()
app/Http/Controllers/LessonController.php:148-149    # update()
app/Http/Controllers/LessonController.php:176-177    # destroy()
app/Http/Controllers/CourseSectionController.php:40  # store()
app/Http/Controllers/CourseSectionController.php:64  # destroy()
app/Http/Controllers/CourseDurationController.php:17 # recalculate()
```

### Proposed Fix

**Option A: Single SQL Query (Recommended)**

```php
// app/Models/Course.php
public function calculateEstimatedDuration(): int
{
    return (int) DB::table('lessons')
        ->join('course_sections', 'lessons.course_section_id', '=', 'course_sections.id')
        ->where('course_sections.course_id', $this->id)
        ->sum('lessons.estimated_duration_minutes');
}
```

**Also fix CourseSection:**

```php
// app/Models/CourseSection.php
public function calculateEstimatedDuration(): int
{
    return (int) DB::table('lessons')
        ->where('course_section_id', $this->id)
        ->sum('estimated_duration_minutes');
}
```

**Option B: Eager Load (Less Optimal)**

```php
public function calculateEstimatedDuration(): int
{
    // Force eager load if not already loaded
    if (!$this->relationLoaded('sections')) {
        $this->load('sections.lessons');
    }

    return $this->sections->sum(fn ($section) =>
        $section->lessons->sum('estimated_duration_minutes') ?? 0
    );
}
```

### Recommendation

**Option A** - Single SQL query. Let the database do the math. This is exactly what CLAUDE.md recommends: "Use DB::table() for aggregations".

### Testing

```php
it('calculates duration with single query', function () {
    $course = Course::factory()
        ->has(CourseSection::factory()
            ->has(Lesson::factory()->count(5)->state(['estimated_duration_minutes' => 10]))
            ->count(3)
        )
        ->create();

    DB::enableQueryLog();

    $duration = $course->calculateEstimatedDuration();

    expect($duration)->toBe(150); // 3 sections × 5 lessons × 10 min
    expect(DB::getQueryLog())->toHaveCount(1); // Single query!
});
```

---

## 🔴 P1: Database Queries Inside Authorization Policies

### Problem

`CoursePolicy::enroll()` runs database queries during authorization, which should be pure logic.

### File

`app/Policies/CoursePolicy.php:155-181`

### Current Code (BAD)

```php
public function enroll(User $user, Course $course): bool
{
    // Can only enroll in published courses
    if (! $course->isPublished()) {
        return false;
    }

    // ❌ Query #1: Check existing enrollment
    if ($user->enrollments()->where('course_id', $course->id)->where('status', 'active')->exists()) {
        return false;
    }

    // Public courses - anyone can enroll
    if ($course->visibility === 'public') {
        return true;
    }

    // ❌ Query #2: Check invitations (for restricted courses)
    if ($course->visibility === 'restricted') {
        return $user->courseInvitations()
            ->where('course_id', $course->id)
            ->where('status', 'pending')
            ->exists();
    }

    return false;
}
```

### Why It's Bad

1. **Policies run frequently** - Every request to protected routes
2. **Can't be eager loaded** - Queries are inside policy, not controller
3. **Testing requires database** - Can't unit test without DB setup
4. **Hidden query cost** - Not visible in controller query optimization
5. **Violates separation** - Authorization should be pure logic

### Proposed Fix

**Step 1: Create enrollment check DTO**

```php
// app/Domain/Enrollment/DTOs/EnrollmentContext.php
namespace App\Domain\Enrollment\DTOs;

readonly class EnrollmentContext
{
    public function __construct(
        public bool $isActivelyEnrolled,
        public bool $hasPendingInvitation,
    ) {}

    public static function for(User $user, Course $course): self
    {
        return new self(
            isActivelyEnrolled: $user->enrollments()
                ->where('course_id', $course->id)
                ->where('status', 'active')
                ->exists(),
            hasPendingInvitation: $user->courseInvitations()
                ->where('course_id', $course->id)
                ->where('status', 'pending')
                ->exists(),
        );
    }
}
```

**Step 2: Update controller to pass context**

```php
// app/Http/Controllers/EnrollmentController.php
public function store(Request $request, Course $course): RedirectResponse
{
    $context = EnrollmentContext::for($request->user(), $course);

    Gate::authorize('enroll', [$course, $context]);

    // ... rest of enrollment logic
}
```

**Step 3: Update policy to use pure logic**

```php
// app/Policies/CoursePolicy.php
public function enroll(User $user, Course $course, EnrollmentContext $context): bool
{
    // Can only enroll in published courses
    if (! $course->isPublished()) {
        return false;
    }

    // Can't enroll if already actively enrolled
    if ($context->isActivelyEnrolled) {
        return false;
    }

    // Public courses - anyone can enroll
    if ($course->visibility === 'public') {
        return true;
    }

    // Restricted courses - only if invited
    if ($course->visibility === 'restricted') {
        return $context->hasPendingInvitation;
    }

    return false;
}
```

### Benefits

- **Testable**: Policy is pure logic, test without DB
- **Visible**: Queries are in controller, visible in debugbar/telescope
- **Optimizable**: Can batch load context for multiple courses
- **Cacheable**: Context could be cached per request

### Testing

```php
it('denies enrollment when already enrolled', function () {
    $user = User::factory()->learner()->create();
    $course = Course::factory()->published()->create();

    $context = new EnrollmentContext(
        isActivelyEnrolled: true,
        hasPendingInvitation: false,
    );

    // No database queries needed!
    expect($user->can('enroll', [$course, $context]))->toBeFalse();
});
```

---

## 🟡 P2: Controller Bloat with Data Transformation

### Problem

`LearningPathEnrollmentController` has 168 lines of transformation methods that belong in dedicated classes.

### File

`app/Http/Controllers/LearningPathEnrollmentController.php:228-396`

### Current Code (BAD)

```php
class LearningPathEnrollmentController extends Controller
{
    // 6 controller actions (~230 lines)

    // ========================================================================
    // Data Transformation Methods (168 LINES!)
    // ========================================================================

    protected function transformPathForBrowse(LearningPath $path): array { /* 16 lines */ }
    protected function transformPathForShow(LearningPath $path): array { /* 36 lines */ }
    protected function transformEnrollmentForIndex(LearningPathEnrollment $enrollment): array { /* 17 lines */ }
    protected function transformEnrollmentBasic(LearningPathEnrollment $enrollment): array { /* 13 lines */ }
    protected function transformProgress($progress): array { /* 62 lines */ }
}
```

### Why It's Bad

1. **Controller is 397 lines** - Far exceeds SRP
2. **Untestable** - Can't test transformations without HTTP
3. **Duplicated** - Similar methods in CourseController
4. **Mixed concerns** - HTTP + business + presentation logic
5. **Inconsistent with DTOs** - Project uses DTOs in services, ignores for responses

### Proposed Fix: Laravel API Resources

**Step 1: Create resources directory structure**

```
app/Http/Resources/
├── LearningPath/
│   ├── LearningPathBrowseResource.php
│   ├── LearningPathShowResource.php
│   └── LearningPathCourseResource.php
├── Enrollment/
│   ├── PathEnrollmentIndexResource.php
│   └── PathEnrollmentBasicResource.php
└── Progress/
    └── PathProgressResource.php
```

**Step 2: Create browse resource**

```php
// app/Http/Resources/LearningPath/LearningPathBrowseResource.php
namespace App\Http\Resources\LearningPath;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LearningPathBrowseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'thumbnail_url' => $this->thumbnail_url,
            'difficulty_level' => $this->difficulty_level,
            'estimated_duration' => $this->estimated_duration ?? 0,
            'courses_count' => $this->courses_count ?? $this->courses->count(),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
        ];
    }
}
```

**Step 3: Create show resource with nested courses**

```php
// app/Http/Resources/LearningPath/LearningPathShowResource.php
namespace App\Http\Resources\LearningPath;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LearningPathShowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'thumbnail_url' => $this->thumbnail_url,
            'difficulty_level' => $this->difficulty_level,
            'estimated_duration' => $this->estimated_duration ?? 0,
            'learning_objectives' => $this->objectives ?? [],
            'prerequisites' => null,
            'courses_count' => $this->courses_count ?? $this->courses->count(),
            'enrollments_count' => $this->enrollments_count ?? 0,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'courses' => LearningPathCourseResource::collection($this->whenLoaded('courses')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

**Step 4: Update controller**

```php
// app/Http/Controllers/LearningPathEnrollmentController.php
use App\Http\Resources\LearningPath\LearningPathBrowseResource;
use App\Http\Resources\LearningPath\LearningPathShowResource;

public function browse(Request $request): Response
{
    // ... query logic ...

    return Inertia::render('learner/learning-paths/Browse', [
        'learningPaths' => LearningPathBrowseResource::collection($paginatedPaths),
        'enrolledPathIds' => $enrolledPathIds,
        'filters' => $request->only(['search', 'difficulty']),
    ]);
}

public function show(LearningPath $learningPath): Response
{
    // ... authorization and loading ...

    return Inertia::render('learner/learning-paths/Show', [
        'learningPath' => new LearningPathShowResource($learningPath),
        'enrollment' => $enrollment ? new PathEnrollmentBasicResource($enrollment) : null,
        'progress' => $progress ? new PathProgressResource($progress) : null,
        'canEnroll' => $this->enrollmentService->canEnroll($user, $learningPath),
    ]);
}
```

### Result

| Metric | Before | After |
|--------|--------|-------|
| Controller lines | 397 | ~230 |
| Transform methods | 5 (168 lines) | 0 |
| Testable transforms | No | Yes |
| Reusable resources | No | Yes |

---

## Implementation Order

1. **P1: Duration N+1** (2 hours) - Highest impact, simple fix
2. **P1: Policy queries** (4 hours) - Requires DTO + policy + controller changes
3. **P2: Controller bloat** (8 hours) - Largest change, can be incremental

---

## Files to Modify

### P1: Duration Calculation

| File | Changes |
|------|---------|
| `app/Models/Course.php` | Rewrite `calculateEstimatedDuration()` to use DB query |
| `app/Models/CourseSection.php` | Rewrite `calculateEstimatedDuration()` to use DB query |

### P1: Policy Queries

| File | Changes |
|------|---------|
| `app/Domain/Enrollment/DTOs/EnrollmentContext.php` | Create new DTO |
| `app/Policies/CoursePolicy.php` | Update `enroll()` signature and logic |
| `app/Http/Controllers/EnrollmentController.php` | Pass context to Gate::authorize |
| `tests/Unit/Policies/CoursePolicyTest.php` | Update tests to use context |

### P2: Controller Bloat

| File | Changes |
|------|---------|
| `app/Http/Resources/LearningPath/*.php` | Create 3 resource classes |
| `app/Http/Resources/Enrollment/*.php` | Create 2 resource classes |
| `app/Http/Resources/Progress/*.php` | Create 1 resource class |
| `app/Http/Controllers/LearningPathEnrollmentController.php` | Remove transforms, use resources |

---

## Testing Requirements

### P1 Tests

```php
// Duration calculation - verify single query
it('calculates course duration with single query', function () {
    // ... setup with multiple sections/lessons ...
    DB::enableQueryLog();
    $course->calculateEstimatedDuration();
    expect(DB::getQueryLog())->toHaveCount(1);
});

// Policy - verify no queries in authorization
it('authorizes enrollment without database queries', function () {
    $context = new EnrollmentContext(isActivelyEnrolled: false, hasPendingInvitation: false);

    DB::enableQueryLog();
    $user->can('enroll', [$course, $context]);
    expect(DB::getQueryLog())->toHaveCount(0);
});
```

### P2 Tests

```php
// Resource transformation
it('transforms learning path for browse', function () {
    $path = LearningPath::factory()->create();
    $resource = new LearningPathBrowseResource($path);

    $array = $resource->toArray(request());

    expect($array)->toHaveKeys(['id', 'title', 'slug', 'courses_count']);
});
```

---

## Related Documentation

- `plans/fixing/ROUND6_CODE_QUALITY.md` - Previous code quality fixes
- `.claude/skills/enteraksi-batch-loading/SKILL.md` - Batch loading patterns
- `.claude/skills/enteraksi-architecture/SKILL.md` - Service/DTO patterns
- `CLAUDE.md` - Database query strategy guidelines
