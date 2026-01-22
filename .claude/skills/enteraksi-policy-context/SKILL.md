---
name: enteraksi-policy-context
description: Policy authorization with required context DTOs for Vue/Inertia apps. Use when writing policies that need enrollment/user state, passing context from controllers/FormRequests, or testing policy authorization.
triggers:
  - policy context
  - EnrollmentContext
  - Gate::authorize
  - policy authorization
  - N+1 in policy
  - policy query
  - FormRequest authorize
  - pre-fetch context
  - policy testing
  - required context
---

# Enteraksi Policy Context Patterns

## When to Use This Skill

- Writing policies that need user enrollment/invitation state
- Passing context from controllers to policies
- Passing context in FormRequest `authorize()` methods
- Testing policies with explicit context
- Eliminating N+1 queries hidden in authorization logic

---

## The Problem: N+1 Queries in Policies

Policies often need to check user state (enrolled? invited?). Without context DTOs, this causes hidden queries:

```php
// ❌ BAD: Hidden queries inside policy
class CoursePolicy
{
    public function view(User $user, Course $course): bool
    {
        if ($user->canManageCourses()) return true;

        // HIDDEN QUERY! Every policy check = database hit
        $isEnrolled = $user->enrollments()
            ->where('course_id', $course->id)
            ->exists();

        if ($isEnrolled) return true;

        // ANOTHER HIDDEN QUERY!
        $hasInvitation = $user->courseInvitations()
            ->where('course_id', $course->id)
            ->where('status', 'pending')
            ->exists();

        return $hasInvitation;
    }
}
```

**Problems:**
- Queries invisible in Debugbar/Telescope (shown as "policy" time)
- N+1 when checking authorization for lists
- Hard to test - need database state for every test
- Mixing data fetching with authorization logic

---

## The Solution: Required Context DTO

### Step 1: Create Context DTO

```php
// app/Domain/Enrollment/DTOs/EnrollmentContext.php
namespace App\Domain\Enrollment\DTOs;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

/**
 * Context for enrollment-related authorization decisions.
 *
 * Controllers pre-fetch this data, policies use pure logic.
 */
readonly class EnrollmentContext
{
    public function __construct(
        public bool $isActivelyEnrolled,
        public bool $hasPendingInvitation,
        public bool $hasAnyEnrollment = false,
    ) {}

    /**
     * Create context by querying the database.
     * Use in controllers before authorization.
     */
    public static function for(User $user, Course $course): self
    {
        /** @var Enrollment|null $enrollment */
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

    /**
     * Create from pre-loaded data (for testing or batch operations).
     */
    public static function fromData(
        bool $isActivelyEnrolled,
        bool $hasPendingInvitation,
        bool $hasAnyEnrollment = false,
    ): self {
        return new self(
            isActivelyEnrolled: $isActivelyEnrolled,
            hasPendingInvitation: $hasPendingInvitation,
            hasAnyEnrollment: $hasAnyEnrollment,
        );
    }
}
```

### Step 2: Update Policy to Require Context

```php
// app/Policies/CoursePolicy.php
use App\Domain\Enrollment\DTOs\EnrollmentContext;

class CoursePolicy
{
    /**
     * Requires EnrollmentContext - no hidden queries.
     * Controllers MUST pre-fetch context before calling.
     */
    public function view(User $user, Course $course, EnrollmentContext $context): bool
    {
        // Admin/managers bypass enrollment checks
        if ($user->canManageCourses()) {
            return true;
        }

        // Owner can view own course
        if ($course->user_id === $user->id) {
            return true;
        }

        // Enrolled learners can view (even draft courses under revision)
        if ($context->hasAnyEnrollment) {
            return true;
        }

        // Public published courses
        if ($course->isPublished() && $course->visibility === 'public') {
            return true;
        }

        // Restricted courses require invitation
        if ($course->isPublished() && $course->visibility === 'restricted') {
            return $context->hasPendingInvitation;
        }

        return false;
    }
}
```

### Step 3: Pass Context from Controller

```php
// app/Http/Controllers/CourseController.php
use App\Domain\Enrollment\DTOs\EnrollmentContext;

public function show(Request $request, Course $course): Response
{
    $user = $request->user();

    // Pre-fetch context BEFORE authorization
    $context = EnrollmentContext::for($user, $course);

    // Pass context to policy
    Gate::authorize('view', [$course, $context]);

    // Context can be reused in the view
    return Inertia::render('courses/Detail', [
        'course' => $course,
        'isEnrolled' => $context->isActivelyEnrolled,
        'canEnroll' => Gate::allows('enroll', [$course, $context]),
    ]);
}
```

---

## Why Required (Not Optional)?

### Old Pattern (Blade compatibility)

```php
// ❌ Optional with fallback - hides queries
public function view(User $user, Course $course, ?EnrollmentContext $context = null): bool
{
    // Fallback query when no context provided (for Blade @can)
    $isEnrolled = $context?->hasAnyEnrollment
        ?? $user->enrollments()->where('course_id', $course->id)->exists();
}
```

**Problems with optional context:**
- Blade `@can` doesn't pass context, so fallback queries run
- Developers forget to pass context, queries happen silently
- Inconsistent behavior between controller and Blade

### New Pattern (Vue/Inertia)

```php
// ✅ Required context - no fallback, no hidden queries
public function view(User $user, Course $course, EnrollmentContext $context): bool
{
    // Pure logic - context MUST be provided
    if ($context->hasAnyEnrollment) return true;
    // ...
}
```

**Why this works for Vue/Inertia:**
- No Blade `@can` usage - all authorization in controllers
- Controllers always know what context to pass
- Type system enforces context is provided
- Tests create explicit context - no database needed

---

## FormRequest Authorization with Context

For endpoints using FormRequest validation, pass context in `authorize()`:

```php
// app/Http/Requests/CourseRating/StoreRatingRequest.php
namespace App\Http\Requests\CourseRating;

use App\Domain\Enrollment\DTOs\EnrollmentContext;
use App\Models\CourseRating;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $course = $this->route('course');

        // Pre-fetch context for authorization
        $context = EnrollmentContext::for($user, $course);

        // Check if user already has a rating (policy needs this)
        $hasExistingRating = $user->courseRatings()
            ->where('course_id', $course->id)
            ->exists();

        return Gate::allows('create', [
            CourseRating::class,
            $course,
            $context,
            $hasExistingRating,
        ]);
    }
}
```

**Matching Policy:**

```php
// app/Policies/CourseRatingPolicy.php
class CourseRatingPolicy
{
    /**
     * User must be enrolled and not have existing rating.
     */
    public function create(
        User $user,
        Course $course,
        EnrollmentContext $context,
        bool $hasExistingRating,
    ): bool {
        // Must be enrolled
        if (! $context->hasAnyEnrollment) {
            return false;
        }

        // Cannot rate twice
        return ! $hasExistingRating;
    }
}
```

---

## Conditional Context (Role-Based)

For policies where only certain roles need context:

```php
// app/Http/Controllers/AssessmentController.php
public function index(Request $request, Course $course): Response
{
    $user = $request->user();

    // Only learners need enrollment context
    $context = $user->isLearner()
        ? EnrollmentContext::for($user, $course)
        : null;

    Gate::authorize('viewAny', [Assessment::class, $course, $context]);
    // ...
}
```

**Matching Policy:**

```php
// app/Policies/AssessmentPolicy.php
public function viewAny(User $user, Course $course, ?EnrollmentContext $context = null): bool
{
    // Admin bypasses - no context needed
    if ($user->isLmsAdmin()) {
        return true;
    }

    // Content manager owns course - no context needed
    if ($user->isContentManager() && $course->user_id === $user->id) {
        return true;
    }

    // Learner MUST provide context
    if ($user->isLearner() && $context !== null) {
        return $context->hasAnyEnrollment;
    }

    return false;
}
```

---

## Testing Policies with Context

Tests create explicit context - no database needed for authorization logic:

```php
// tests/Unit/Policies/CoursePolicyTest.php
use App\Domain\Enrollment\DTOs\EnrollmentContext;

public function test_enrolled_learner_can_view_draft_course(): void
{
    $course = Course::factory()->create(['status' => 'draft']);
    $learner = User::factory()->learner()->create();

    // Enrollment created for data setup
    Enrollment::factory()->active()->create([
        'user_id' => $learner->id,
        'course_id' => $course->id,
    ]);

    // Context created EXPLICITLY - tests policy logic
    $context = EnrollmentContext::fromData(
        isActivelyEnrolled: true,
        hasPendingInvitation: false,
        hasAnyEnrollment: true,
    );

    $this->assertTrue($this->policy->view($learner, $course, $context));
}

public function test_uninvited_learner_cannot_view_restricted_course(): void
{
    $course = Course::factory()->published()->create([
        'visibility' => 'restricted',
    ]);
    $learner = User::factory()->learner()->create();

    // No enrollment, no invitation
    $context = EnrollmentContext::fromData(
        isActivelyEnrolled: false,
        hasPendingInvitation: false,
        hasAnyEnrollment: false,
    );

    $this->assertFalse($this->policy->view($learner, $course, $context));
}
```

**Benefits of explicit context in tests:**
- Tests policy logic, not database queries
- Clear what state is being tested
- Fast - minimal database setup needed
- Documents expected behavior through context values

---

## Quick Reference

| Scenario | Where to Create Context | How |
|----------|-------------------------|-----|
| Controller action | Before `Gate::authorize()` | `EnrollmentContext::for($user, $course)` |
| FormRequest | In `authorize()` method | `EnrollmentContext::for($this->user(), $this->route('course'))` |
| Unit test | Before policy call | `EnrollmentContext::fromData(...)` |
| Batch operation | Loop before authorization | Pre-load all, filter per item |

---

## Anti-Patterns to Avoid

### 1. Optional Context with Fallback

```php
// ❌ BAD: Falls back to query when context not provided
public function view(User $user, Course $course, ?EnrollmentContext $context = null): bool
{
    $isEnrolled = $context?->hasAnyEnrollment
        ?? $user->enrollments()->where('course_id', $course->id)->exists();
}
```

### 2. Creating Context Inside Policy

```php
// ❌ BAD: Defeats the purpose
public function view(User $user, Course $course): bool
{
    $context = EnrollmentContext::for($user, $course);  // Query inside policy!
    // ...
}
```

### 3. Ignoring Context for "Simple" Checks

```php
// ❌ BAD: Still causes query when context exists
public function view(User $user, Course $course, EnrollmentContext $context): bool
{
    // Ignoring context, querying directly
    if ($user->enrollments()->where('course_id', $course->id)->exists()) {
        return true;
    }
}
```

---

## Files to Reference

```
app/Domain/Enrollment/DTOs/EnrollmentContext.php    # Context DTO
app/Policies/CoursePolicy.php                       # Required context pattern
app/Policies/AssessmentPolicy.php                   # Conditional context pattern
app/Policies/CourseRatingPolicy.php                 # Multi-param context pattern
app/Http/Controllers/CourseController.php           # Controller context passing
app/Http/Requests/CourseRating/StoreRatingRequest.php  # FormRequest context
tests/Unit/Policies/CoursePolicyTest.php            # Testing with fromData()
tests/Unit/Policies/AssessmentPolicyTest.php        # Testing with explicit context
```
