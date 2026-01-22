---
name: enteraksi-architecture
description: Domain-Driven Design patterns, service layer, contracts, DTOs, Spatie Data classes for Enteraksi LMS. Use when creating services, Data classes, or working with domain layer code.
triggers:
  - create service
  - new service
  - domain service
  - create dto
  - data transfer object
  - spatie data
  - domain layer
  - bounded context
  - service contract
  - interface
  - DomainServiceProvider
  - model behavior
  - rich model
---

# Enteraksi DDD Architecture

## ⚠️ ARCHITECTURAL CHANGES (ROUND10 - Jan 2026)

### What Changed

| Before | After |
|--------|-------|
| Custom ValueObjects + DTOs | **Spatie Laravel Data** (`app/Data/`) |
| Services return DTOs | **Services return Models** |
| `EnrollmentResult`, `PathEnrollmentResult` | **DELETED** - use Model directly |
| Thin models | **Rich models** with behavior methods |

### New Patterns

1. **Services return Eloquent models** - controllers transform using Spatie Data when needed
2. **Models own state transitions** - `$enrollment->drop()`, `$enrollment->complete()`
3. **Spatie Data for API/Inertia** - TypeScript types auto-generated
4. **Input DTOs still valid** - `CreateEnrollmentDTO` remains for service input

## ⚠️ CRITICAL RULES

1. **Services return Models** - not DTOs or value objects
2. **Models own behavior** - state transitions, events are in model methods
3. **Use Spatie Data** for API responses (with `#[TypeScript]` attribute)
4. **Input DTOs are fine** - `CreateEnrollmentDTO` pattern still valid for service inputs
5. **NEVER wrap models in DTOs** - controllers use `XxxData::fromModel()` only when needed for API

## When to Use This Skill

- Creating a new domain service with contract interface
- Adding behavior methods to models (drop, complete, reactivate)
- Creating Spatie Data classes for API responses
- Registering services in DomainServiceProvider

## Directory Structure

```
app/
├── Data/                      # Spatie Data classes (NEW)
│   ├── Enrollment/
│   │   └── EnrollmentData.php
│   ├── Course/
│   │   └── CourseData.php
│   ├── LearningPath/
│   │   ├── PathEnrollmentData.php
│   │   └── CourseProgressData.php
│   ├── Progress/
│   │   └── LessonProgressData.php
│   └── User/
│       └── UserData.php
├── Domain/
│   └── {BoundedContext}/
│       ├── Contracts/         # Service interfaces
│       ├── DTOs/              # Input DTOs only (CreateXxxDTO)
│       ├── Events/            # Domain events
│       ├── Exceptions/        # Domain-specific exceptions
│       ├── Listeners/         # Event listeners
│       ├── Services/          # Service implementations
│       ├── States/            # State machine classes
│       └── Strategies/        # Strategy pattern (when justified)
├── Models/                    # Rich models with behavior
└── Providers/
    └── DomainServiceProvider.php
```

## Bounded Contexts

| Context | Purpose |
|---------|---------|
| Assessment | Quizzes, grading, attempts |
| Course | Course content, states |
| Enrollment | User enrollments, lifecycle |
| LearningPath | Learning paths, prerequisites |
| Progress | Lesson progress, completion |
| Shared | Cross-cutting concerns |

## Key Patterns

### 1. Rich Models with Behavior

**Models own their state transitions:**

```php
// app/Models/Enrollment.php
class Enrollment extends Model
{
    use HasStates;

    /**
     * Drop this enrollment.
     *
     * @throws InvalidStateTransitionException if not currently active
     */
    public function drop(?string $reason = null): self
    {
        if (! $this->isActive()) {
            throw new InvalidStateTransitionException(
                from: (string) $this->status,
                to: DroppedState::$name,
                modelType: 'Enrollment',
                modelId: $this->id,
                reason: 'Only active enrollments can be dropped'
            );
        }

        DB::transaction(function () use ($reason) {
            $this->update(['status' => DroppedState::$name]);
            UserDropped::dispatch($this, $reason);
        });

        return $this;
    }

    /**
     * Complete this enrollment.
     * Idempotent - calling on already completed enrollment is a no-op.
     */
    public function complete(): self
    {
        if ($this->isCompleted()) {
            return $this; // Idempotent
        }

        DB::transaction(function () {
            $this->update([
                'status' => CompletedState::$name,
                'completed_at' => now(),
            ]);
            EnrollmentCompleted::dispatch($this);
        });

        return $this;
    }

    /**
     * Reactivate a dropped enrollment.
     */
    public function reactivate(bool $preserveProgress = true, ?int $invitedBy = null): self
    {
        if (! $this->isDropped()) {
            throw new InvalidStateTransitionException(/* ... */);
        }

        DB::transaction(function () use ($preserveProgress, $invitedBy) {
            $updateData = [
                'status' => ActiveState::$name,
                'enrolled_at' => now(),
                'completed_at' => null,
            ];

            if (! $preserveProgress) {
                $updateData['progress_percentage'] = 0;
                $updateData['started_at'] = null;
            }

            if ($invitedBy) {
                $updateData['invited_by'] = $invitedBy;
            }

            $this->update($updateData);
            UserReenrolled::dispatch($this, $preserveProgress);
        });

        return $this;
    }
}
```

### 2. Services Return Models

**Services orchestrate, return models:**

```php
// app/Domain/Enrollment/Contracts/EnrollmentServiceContract.php
interface EnrollmentServiceContract
{
    /**
     * Enroll a user in a course.
     * Returns the Enrollment model. Controllers transform using Data classes.
     */
    public function enroll(CreateEnrollmentDTO $dto): Enrollment;

    public function canEnroll(User $user, Course $course): bool;

    public function getActiveEnrollment(User $user, Course $course): ?Enrollment;

    // Note: drop() and complete() are now on the Enrollment model!
}
```

```php
// app/Domain/Enrollment/Services/EnrollmentService.php
class EnrollmentService implements EnrollmentServiceContract
{
    public function enroll(CreateEnrollmentDTO $dto): Enrollment
    {
        $user = User::findOrFail($dto->userId);
        $course = Course::findOrFail($dto->courseId);

        $this->validateEnrollment($user, $course);

        // Check for dropped enrollment (re-enrollment case)
        $droppedEnrollment = $this->getDroppedEnrollment($user, $course);
        if ($droppedEnrollment) {
            // Use model method directly
            return $droppedEnrollment->reactivate(
                preserveProgress: true,
                invitedBy: $dto->invitedBy
            );
        }

        return DB::transaction(function () use ($dto) {
            $enrollment = Enrollment::create([
                'user_id' => $dto->userId,
                'course_id' => $dto->courseId,
                'status' => ActiveState::$name,
                'progress_percentage' => 0,
                'enrolled_at' => $dto->enrolledAt ?? now(),
                'invited_by' => $dto->invitedBy,
            ]);

            UserEnrolled::dispatch($enrollment);

            return $enrollment;  // Return model directly
        });
    }
}
```

### 3. Input DTOs (Standalone Readonly Classes)

**For service inputs - no base class needed:**

```php
// app/Domain/Enrollment/DTOs/CreateEnrollmentDTO.php
final readonly class CreateEnrollmentDTO
{
    public function __construct(
        public int $userId,
        public int $courseId,
        public ?int $invitedBy = null,
        public ?DateTimeInterface $enrolledAt = null,
    ) {}

    /**
     * @param array{
     *     user_id: int,
     *     course_id: int,
     *     invited_by?: int|null,
     *     enrolled_at?: string|null
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            courseId: $data['course_id'],
            invitedBy: $data['invited_by'] ?? null,
            enrolledAt: isset($data['enrolled_at'])
                ? new DateTimeImmutable($data['enrolled_at'])
                : null,
        );
    }
}
```

### 4. Spatie Data Classes (API Responses)

**See dedicated skill: `enteraksi-spatie-data`**

```php
// app/Data/Enrollment/EnrollmentData.php
#[TypeScript]
class EnrollmentData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public int $course_id,
        public string $status,
        public int $progress_percentage,
        // ...
    ) {}

    public static function fromModel(Enrollment $enrollment): self
    {
        return new self(
            id: $enrollment->id,
            user_id: $enrollment->user_id,
            // ...
        );
    }
}
```

### 5. Controllers Use Model Methods + Data Classes

```php
// app/Http/Controllers/EnrollmentController.php
class EnrollmentController extends Controller
{
    public function store(Request $request, Course $course): RedirectResponse
    {
        // Service creates enrollment, returns model
        $enrollment = $this->enrollmentService->enroll(
            new CreateEnrollmentDTO(
                userId: $request->user()->id,
                courseId: $course->id,
            )
        );

        // Redirect (Inertia) - no need for Data class
        return redirect()
            ->route('courses.show', $course)
            ->with('success', 'Berhasil mendaftar ke kursus.');
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $enrollment = $request->user()->enrollments()
            ->where('course_id', $course->id)
            ->firstOrFail();

        // Use model method directly
        $enrollment->drop();

        return redirect()
            ->route('learner.dashboard')
            ->with('success', 'Pendaftaran kursus dibatalkan.');
    }

    // For API endpoints that need typed response
    public function apiShow(Enrollment $enrollment)
    {
        return EnrollmentData::fromModel($enrollment);
    }
}
```

### 6. DomainServiceProvider Registration

```php
// app/Providers/DomainServiceProvider.php
class DomainServiceProvider extends ServiceProvider
{
    public array $bindings = [
        EnrollmentServiceContract::class => EnrollmentService::class,
        PathEnrollmentServiceContract::class => PathEnrollmentService::class,
        ProgressTrackingServiceContract::class => ProgressTrackingService::class,
    ];
}
```

## ❌ Deprecated Patterns (DON'T USE)

### Old: Result DTOs wrapping models

```php
// ❌ DEPRECATED - Don't create these anymore
final readonly class EnrollmentResult {
    public function __construct(
        public EnrollmentData $enrollment,  // Value object wrapper
        public bool $isNewEnrollment,
    ) {}
}

// In service:
return EnrollmentResult::fromEnrollment($enrollment, true);  // ❌ DON'T
```

### Old: ValueObjects for data extraction

```php
// ❌ DEPRECATED - Now use Spatie Data
namespace App\Domain\Enrollment\ValueObjects;

final readonly class EnrollmentData {
    // This pattern is replaced by app/Data/Enrollment/EnrollmentData.php
}
```

### Old: Service methods for state transitions

```php
// ❌ DEPRECATED - Use model methods
$enrollmentService->drop($enrollment);     // ❌ DON'T
$enrollmentService->complete($enrollment); // ❌ DON'T

// ✅ NEW - Model owns behavior
$enrollment->drop($reason);
$enrollment->complete();
$enrollment->reactivate($preserveProgress);
```

## Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Contract | `{Name}Contract` | `EnrollmentServiceContract` |
| Service | `{Name}Service` | `EnrollmentService` |
| Input DTO | `{Action}{Entity}DTO` | `CreateEnrollmentDTO` |
| Spatie Data | `{Entity}Data` | `EnrollmentData` (in `app/Data/`) |
| Model Behavior | verb method | `drop()`, `complete()`, `reactivate()` |

## What Goes Where

| Need | Where |
|------|-------|
| Service input validation | Input DTO (`CreateEnrollmentDTO`) |
| API/Inertia response | Spatie Data (`app/Data/XxxData.php`) |
| State transitions | Model methods (`$model->drop()`) |
| Business validation | Service (`validateEnrollment()`) |
| Events | Model methods dispatch after state change |
| Query helpers | Service (`getActiveEnrollment()`) |

## Quick Reference

```bash
# Create Spatie Data class
# See enteraksi-spatie-data skill

# Model behavior methods
$enrollment->drop(?string $reason = null): self
$enrollment->complete(): self
$enrollment->reactivate(bool $preserveProgress = true, ?int $invitedBy = null): self

# Service methods (simplified)
$enrollmentService->enroll(CreateEnrollmentDTO $dto): Enrollment
$enrollmentService->canEnroll(User $user, Course $course): bool
$enrollmentService->getActiveEnrollment(User $user, Course $course): ?Enrollment

# Generate TypeScript types
php artisan typescript:transform
```

## Files to Reference

```
app/Data/Enrollment/EnrollmentData.php           # Spatie Data example
app/Models/Enrollment.php                         # Rich model with behavior
app/Domain/Enrollment/Services/EnrollmentService.php  # Simplified service
app/Domain/Enrollment/DTOs/CreateEnrollmentDTO.php    # Input DTO
app/Providers/DomainServiceProvider.php           # Bindings
resources/js/types/generated.d.ts                 # Auto-generated TypeScript
```
