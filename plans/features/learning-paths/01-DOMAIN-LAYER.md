# Phase 1: Domain Layer

> **Phase**: 1 of 6
> **Estimated Effort**: Medium
> **Prerequisites**: None

---

## Objectives

- Create domain contracts for path enrollment and progress
- Implement DTOs for data transfer
- Create domain events for path lifecycle
- Implement core services
- Set up event listeners for automation

---

## 1.1 Directory Structure

```
app/Domain/LearningPath/
├── Contracts/
│   ├── PathEnrollmentServiceContract.php
│   └── PathProgressServiceContract.php
├── DTOs/
│   ├── PathEnrollmentDTO.php
│   ├── PathProgressDTO.php
│   ├── CourseProgressDTO.php
│   └── PrerequisiteCheckResult.php
├── Events/
│   ├── PathEnrollmentCreated.php
│   ├── PathCompleted.php
│   ├── PathDropped.php
│   └── CourseUnlockedInPath.php
├── Listeners/
│   ├── EnrollInFirstCourse.php
│   ├── CheckPathPrerequisites.php
│   └── UpdatePathProgress.php
├── Exceptions/
│   ├── AlreadyEnrolledInPathException.php
│   ├── PathNotPublishedException.php
│   ├── PrerequisiteNotMetException.php
│   └── CourseNotInPathException.php
└── Services/
    ├── PathEnrollmentService.php
    └── PathProgressService.php
```

---

## 1.2 Contracts

### PathEnrollmentServiceContract

```php
<?php

namespace App\Domain\LearningPath\Contracts;

use App\Domain\LearningPath\DTOs\PathEnrollmentDTO;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;

interface PathEnrollmentServiceContract
{
    /**
     * Enroll a user in a learning path.
     *
     * @throws \App\Domain\LearningPath\Exceptions\AlreadyEnrolledInPathException
     * @throws \App\Domain\LearningPath\Exceptions\PathNotPublishedException
     */
    public function enroll(User $user, LearningPath $path): LearningPathEnrollment;

    /**
     * Check if user can enroll in a path.
     */
    public function canEnroll(User $user, LearningPath $path): bool;

    /**
     * Get user's enrollment for a path.
     */
    public function getEnrollment(User $user, LearningPath $path): ?LearningPathEnrollment;

    /**
     * Check if user is enrolled in a path.
     */
    public function isEnrolled(User $user, LearningPath $path): bool;

    /**
     * Drop user from a learning path.
     */
    public function drop(LearningPathEnrollment $enrollment, ?string $reason = null): void;

    /**
     * Get all active path enrollments for a user.
     *
     * @return Collection<int, LearningPathEnrollment>
     */
    public function getActiveEnrollments(User $user): Collection;

    /**
     * Get all enrollments for a path (admin).
     *
     * @return Collection<int, LearningPathEnrollment>
     */
    public function getPathEnrollments(LearningPath $path): Collection;

    /**
     * Mark path enrollment as completed.
     */
    public function complete(LearningPathEnrollment $enrollment): void;
}
```

### PathProgressServiceContract

```php
<?php

namespace App\Domain\LearningPath\Contracts;

use App\Domain\LearningPath\DTOs\PathProgressDTO;
use App\Domain\LearningPath\DTOs\PrerequisiteCheckResult;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPathEnrollment;

interface PathProgressServiceContract
{
    /**
     * Get complete progress for a path enrollment.
     */
    public function getProgress(LearningPathEnrollment $enrollment): PathProgressDTO;

    /**
     * Calculate overall progress percentage.
     */
    public function calculateProgressPercentage(LearningPathEnrollment $enrollment): int;

    /**
     * Check if prerequisites are met for a course in a path.
     */
    public function checkPrerequisites(
        LearningPathEnrollment $enrollment,
        Course $course
    ): PrerequisiteCheckResult;

    /**
     * Check if a course is unlocked in a path.
     */
    public function isCourseUnlocked(
        LearningPathEnrollment $enrollment,
        Course $course
    ): bool;

    /**
     * Unlock the next available course(s) in the path.
     * Called when a course is completed.
     */
    public function unlockNextCourses(LearningPathEnrollment $enrollment): array;

    /**
     * Update progress when course enrollment completes.
     */
    public function onCourseCompleted(
        LearningPathEnrollment $pathEnrollment,
        Enrollment $courseEnrollment
    ): void;

    /**
     * Check if path is completed.
     */
    public function isPathCompleted(LearningPathEnrollment $enrollment): bool;

    /**
     * Get available courses (unlocked but not started).
     */
    public function getAvailableCourses(LearningPathEnrollment $enrollment): array;

    /**
     * Get locked courses with reasons.
     */
    public function getLockedCourses(LearningPathEnrollment $enrollment): array;
}
```

---

## 1.3 DTOs

### PathEnrollmentDTO

```php
<?php

namespace App\Domain\LearningPath\DTOs;

readonly class PathEnrollmentDTO
{
    public function __construct(
        public int $pathId,
        public int $userId,
        public string $status,
        public int $progressPercentage,
        public ?\DateTimeInterface $enrolledAt,
        public ?\DateTimeInterface $completedAt,
        public array $courseProgress = [],
    ) {}

    public static function fromEnrollment(\App\Models\LearningPathEnrollment $enrollment): self
    {
        return new self(
            pathId: $enrollment->learning_path_id,
            userId: $enrollment->user_id,
            status: $enrollment->status,
            progressPercentage: $enrollment->progress_percentage,
            enrolledAt: $enrollment->enrolled_at,
            completedAt: $enrollment->completed_at,
            courseProgress: $enrollment->courseProgress->map(
                fn ($cp) => CourseProgressDTO::fromModel($cp)
            )->all(),
        );
    }
}
```

### PathProgressDTO

```php
<?php

namespace App\Domain\LearningPath\DTOs;

readonly class PathProgressDTO
{
    public function __construct(
        public int $pathEnrollmentId,
        public int $overallPercentage,
        public int $totalCourses,
        public int $completedCourses,
        public int $inProgressCourses,
        public int $lockedCourses,
        public int $availableCourses,
        /** @var CourseProgressDTO[] */
        public array $courses,
        public bool $isCompleted,
        public ?\DateTimeInterface $estimatedCompletionDate = null,
    ) {}
}
```

### CourseProgressDTO

```php
<?php

namespace App\Domain\LearningPath\DTOs;

readonly class CourseProgressDTO
{
    public function __construct(
        public int $courseId,
        public string $courseTitle,
        public string $status, // 'locked', 'available', 'in_progress', 'completed'
        public int $position,
        public bool $isRequired,
        public int $completionPercentage,
        public ?int $minRequiredPercentage,
        public ?array $prerequisites,
        public ?string $lockReason = null,
        public ?\DateTimeInterface $unlockedAt = null,
        public ?\DateTimeInterface $startedAt = null,
        public ?\DateTimeInterface $completedAt = null,
    ) {}

    public static function fromModel(\App\Models\LearningPathCourseProgress $progress): self
    {
        return new self(
            courseId: $progress->course_id,
            courseTitle: $progress->course->title,
            status: $progress->status,
            position: $progress->pivot_position ?? 0,
            isRequired: $progress->pivot_is_required ?? true,
            completionPercentage: $progress->completion_percentage,
            minRequiredPercentage: $progress->pivot_min_completion_percentage,
            prerequisites: json_decode($progress->pivot_prerequisites ?? '[]', true),
            lockReason: $progress->lock_reason,
            unlockedAt: $progress->unlocked_at,
            startedAt: $progress->started_at,
            completedAt: $progress->completed_at,
        );
    }
}
```

### PrerequisiteCheckResult

```php
<?php

namespace App\Domain\LearningPath\DTOs;

readonly class PrerequisiteCheckResult
{
    public function __construct(
        public bool $isMet,
        public array $missingPrerequisites = [],
        public ?string $reason = null,
    ) {}

    public static function met(): self
    {
        return new self(isMet: true);
    }

    public static function notMet(array $missing, string $reason): self
    {
        return new self(
            isMet: false,
            missingPrerequisites: $missing,
            reason: $reason,
        );
    }
}
```

---

## 1.4 Events

### PathEnrollmentCreated

```php
<?php

namespace App\Domain\LearningPath\Events;

use App\Models\LearningPathEnrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PathEnrollmentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public LearningPathEnrollment $enrollment,
    ) {}
}
```

### PathCompleted

```php
<?php

namespace App\Domain\LearningPath\Events;

use App\Models\LearningPathEnrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PathCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public LearningPathEnrollment $enrollment,
    ) {}
}
```

### PathDropped

```php
<?php

namespace App\Domain\LearningPath\Events;

use App\Models\LearningPathEnrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PathDropped
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public LearningPathEnrollment $enrollment,
        public ?string $reason = null,
    ) {}
}
```

### CourseUnlockedInPath

```php
<?php

namespace App\Domain\LearningPath\Events;

use App\Models\Course;
use App\Models\LearningPathEnrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourseUnlockedInPath
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public LearningPathEnrollment $pathEnrollment,
        public Course $course,
    ) {}
}
```

---

## 1.5 Exceptions

### AlreadyEnrolledInPathException

```php
<?php

namespace App\Domain\LearningPath\Exceptions;

use Exception;

class AlreadyEnrolledInPathException extends Exception
{
    public function __construct(int $pathId, int $userId)
    {
        parent::__construct(
            "User {$userId} is already enrolled in learning path {$pathId}."
        );
    }
}
```

### PathNotPublishedException

```php
<?php

namespace App\Domain\LearningPath\Exceptions;

use Exception;

class PathNotPublishedException extends Exception
{
    public function __construct(int $pathId)
    {
        parent::__construct(
            "Learning path {$pathId} is not published and cannot accept enrollments."
        );
    }
}
```

### PrerequisiteNotMetException

```php
<?php

namespace App\Domain\LearningPath\Exceptions;

use Exception;

class PrerequisiteNotMetException extends Exception
{
    public function __construct(
        public readonly int $courseId,
        public readonly array $missingPrerequisites,
    ) {
        $missing = implode(', ', array_column($missingPrerequisites, 'title'));
        parent::__construct(
            "Prerequisites not met for course {$courseId}. Missing: {$missing}"
        );
    }
}
```

### CourseNotInPathException

```php
<?php

namespace App\Domain\LearningPath\Exceptions;

use Exception;

class CourseNotInPathException extends Exception
{
    public function __construct(int $courseId, int $pathId)
    {
        parent::__construct(
            "Course {$courseId} is not part of learning path {$pathId}."
        );
    }
}
```

---

## 1.6 Services

### PathEnrollmentService

```php
<?php

namespace App\Domain\LearningPath\Services;

use App\Domain\LearningPath\Contracts\PathEnrollmentServiceContract;
use App\Domain\LearningPath\Contracts\PathProgressServiceContract;
use App\Domain\LearningPath\Events\PathCompleted;
use App\Domain\LearningPath\Events\PathDropped;
use App\Domain\LearningPath\Events\PathEnrollmentCreated;
use App\Domain\LearningPath\Exceptions\AlreadyEnrolledInPathException;
use App\Domain\LearningPath\Exceptions\PathNotPublishedException;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PathEnrollmentService implements PathEnrollmentServiceContract
{
    public function __construct(
        private PathProgressServiceContract $progressService,
    ) {}

    public function enroll(User $user, LearningPath $path): LearningPathEnrollment
    {
        // Validate path is published
        if (!$path->is_published) {
            throw new PathNotPublishedException($path->id);
        }

        // Check not already enrolled
        if ($this->isEnrolled($user, $path)) {
            throw new AlreadyEnrolledInPathException($path->id, $user->id);
        }

        return DB::transaction(function () use ($user, $path) {
            // Create path enrollment
            $enrollment = LearningPathEnrollment::create([
                'learning_path_id' => $path->id,
                'user_id' => $user->id,
                'status' => 'active',
                'enrolled_at' => now(),
                'progress_percentage' => 0,
            ]);

            // Initialize course progress records
            $this->initializeCourseProgress($enrollment, $path);

            // Dispatch event (will trigger auto-enrollment in first course)
            PathEnrollmentCreated::dispatch($enrollment);

            return $enrollment;
        });
    }

    public function canEnroll(User $user, LearningPath $path): bool
    {
        if (!$path->is_published) {
            return false;
        }

        if ($this->isEnrolled($user, $path)) {
            return false;
        }

        return true;
    }

    public function getEnrollment(User $user, LearningPath $path): ?LearningPathEnrollment
    {
        return LearningPathEnrollment::where('user_id', $user->id)
            ->where('learning_path_id', $path->id)
            ->first();
    }

    public function isEnrolled(User $user, LearningPath $path): bool
    {
        return LearningPathEnrollment::where('user_id', $user->id)
            ->where('learning_path_id', $path->id)
            ->whereIn('status', ['active', 'completed'])
            ->exists();
    }

    public function drop(LearningPathEnrollment $enrollment, ?string $reason = null): void
    {
        $enrollment->update([
            'status' => 'dropped',
            'dropped_at' => now(),
            'metadata' => array_merge($enrollment->metadata ?? [], [
                'drop_reason' => $reason,
            ]),
        ]);

        PathDropped::dispatch($enrollment, $reason);
    }

    public function getActiveEnrollments(User $user): Collection
    {
        return LearningPathEnrollment::with(['learningPath', 'courseProgress'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('enrolled_at', 'desc')
            ->get();
    }

    public function getPathEnrollments(LearningPath $path): Collection
    {
        return LearningPathEnrollment::with(['user', 'courseProgress'])
            ->where('learning_path_id', $path->id)
            ->orderBy('enrolled_at', 'desc')
            ->get();
    }

    public function complete(LearningPathEnrollment $enrollment): void
    {
        $enrollment->update([
            'status' => 'completed',
            'completed_at' => now(),
            'progress_percentage' => 100,
        ]);

        PathCompleted::dispatch($enrollment);
    }

    /**
     * Initialize course progress records for all courses in the path.
     */
    private function initializeCourseProgress(
        LearningPathEnrollment $enrollment,
        LearningPath $path
    ): void {
        $courses = $path->courses()->orderBy('pivot_position')->get();

        foreach ($courses as $index => $course) {
            // First course is always unlocked, rest are locked
            $isFirst = $index === 0;

            $enrollment->courseProgress()->create([
                'course_id' => $course->id,
                'status' => $isFirst ? 'available' : 'locked',
                'unlocked_at' => $isFirst ? now() : null,
                'completion_percentage' => 0,
            ]);
        }
    }
}
```

### PathProgressService

```php
<?php

namespace App\Domain\LearningPath\Services;

use App\Domain\LearningPath\Contracts\PathProgressServiceContract;
use App\Domain\LearningPath\DTOs\CourseProgressDTO;
use App\Domain\LearningPath\DTOs\PathProgressDTO;
use App\Domain\LearningPath\DTOs\PrerequisiteCheckResult;
use App\Domain\LearningPath\Events\CourseUnlockedInPath;
use App\Domain\LearningPath\Exceptions\CourseNotInPathException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPathCourseProgress;
use App\Models\LearningPathEnrollment;
use Illuminate\Support\Facades\DB;

class PathProgressService implements PathProgressServiceContract
{
    public function getProgress(LearningPathEnrollment $enrollment): PathProgressDTO
    {
        $enrollment->load(['learningPath.courses', 'courseProgress.course']);

        $courseProgressDTOs = $enrollment->courseProgress
            ->map(fn ($cp) => $this->buildCourseProgressDTO($cp, $enrollment))
            ->sortBy('position')
            ->values()
            ->all();

        $statusCounts = collect($courseProgressDTOs)->countBy('status');

        return new PathProgressDTO(
            pathEnrollmentId: $enrollment->id,
            overallPercentage: $this->calculateProgressPercentage($enrollment),
            totalCourses: count($courseProgressDTOs),
            completedCourses: $statusCounts['completed'] ?? 0,
            inProgressCourses: $statusCounts['in_progress'] ?? 0,
            lockedCourses: $statusCounts['locked'] ?? 0,
            availableCourses: $statusCounts['available'] ?? 0,
            courses: $courseProgressDTOs,
            isCompleted: $enrollment->status === 'completed',
        );
    }

    public function calculateProgressPercentage(LearningPathEnrollment $enrollment): int
    {
        $courses = $enrollment->learningPath->courses;

        if ($courses->isEmpty()) {
            return 0;
        }

        $totalWeight = 0;
        $completedWeight = 0;

        foreach ($courses as $course) {
            $weight = $course->pivot->is_required ? 1 : 0.5;
            $totalWeight += $weight;

            $courseProgress = $enrollment->courseProgress
                ->firstWhere('course_id', $course->id);

            if ($courseProgress && $courseProgress->status === 'completed') {
                $completedWeight += $weight;
            } elseif ($courseProgress && $courseProgress->status === 'in_progress') {
                // Add partial credit for in-progress courses
                $partialCredit = ($courseProgress->completion_percentage / 100) * $weight;
                $completedWeight += $partialCredit;
            }
        }

        return $totalWeight > 0
            ? (int) round(($completedWeight / $totalWeight) * 100)
            : 0;
    }

    public function checkPrerequisites(
        LearningPathEnrollment $enrollment,
        Course $course
    ): PrerequisiteCheckResult {
        $pathCourse = $enrollment->learningPath->courses()
            ->where('course_id', $course->id)
            ->first();

        if (!$pathCourse) {
            throw new CourseNotInPathException($course->id, $enrollment->learning_path_id);
        }

        // Get prerequisites from pivot
        $prerequisites = json_decode($pathCourse->pivot->prerequisites ?? '[]', true);

        // If no explicit prerequisites, check if previous course is completed
        if (empty($prerequisites)) {
            return $this->checkSequentialPrerequisite($enrollment, $course);
        }

        // Check explicit prerequisites
        $missingPrereqs = [];
        foreach ($prerequisites as $prereqCourseId) {
            $prereqProgress = $enrollment->courseProgress
                ->firstWhere('course_id', $prereqCourseId);

            if (!$prereqProgress || $prereqProgress->status !== 'completed') {
                $missingPrereqs[] = [
                    'id' => $prereqCourseId,
                    'title' => Course::find($prereqCourseId)?->title ?? 'Unknown',
                ];
            }
        }

        if (!empty($missingPrereqs)) {
            return PrerequisiteCheckResult::notMet(
                $missingPrereqs,
                'Selesaikan kursus prasyarat terlebih dahulu.'
            );
        }

        // Also check minimum completion percentage if set
        return $this->checkMinCompletionPercentage($enrollment, $pathCourse);
    }

    public function isCourseUnlocked(
        LearningPathEnrollment $enrollment,
        Course $course
    ): bool {
        $progress = $enrollment->courseProgress
            ->firstWhere('course_id', $course->id);

        if (!$progress) {
            return false;
        }

        return in_array($progress->status, ['available', 'in_progress', 'completed']);
    }

    public function unlockNextCourses(LearningPathEnrollment $enrollment): array
    {
        $unlockedCourses = [];
        $enrollment->load(['learningPath.courses', 'courseProgress']);

        $lockedCourses = $enrollment->courseProgress
            ->where('status', 'locked');

        foreach ($lockedCourses as $courseProgress) {
            $course = $enrollment->learningPath->courses
                ->firstWhere('id', $courseProgress->course_id);

            if (!$course) {
                continue;
            }

            $prereqResult = $this->checkPrerequisites($enrollment, $course);

            if ($prereqResult->isMet) {
                $courseProgress->update([
                    'status' => 'available',
                    'unlocked_at' => now(),
                ]);

                CourseUnlockedInPath::dispatch($enrollment, $course);
                $unlockedCourses[] = $course;
            }
        }

        return $unlockedCourses;
    }

    public function onCourseCompleted(
        LearningPathEnrollment $pathEnrollment,
        Enrollment $courseEnrollment
    ): void {
        DB::transaction(function () use ($pathEnrollment, $courseEnrollment) {
            // Update course progress
            $courseProgress = $pathEnrollment->courseProgress
                ->firstWhere('course_id', $courseEnrollment->course_id);

            if ($courseProgress) {
                $courseProgress->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'completion_percentage' => 100,
                    'enrollment_id' => $courseEnrollment->id,
                ]);
            }

            // Unlock next courses
            $this->unlockNextCourses($pathEnrollment);

            // Update overall progress
            $newPercentage = $this->calculateProgressPercentage($pathEnrollment);
            $pathEnrollment->update(['progress_percentage' => $newPercentage]);

            // Check if path is completed
            if ($this->isPathCompleted($pathEnrollment)) {
                app(PathEnrollmentService::class)->complete($pathEnrollment);
            }
        });
    }

    public function isPathCompleted(LearningPathEnrollment $enrollment): bool
    {
        $requiredCourses = $enrollment->learningPath->courses()
            ->wherePivot('is_required', true)
            ->get();

        foreach ($requiredCourses as $course) {
            $progress = $enrollment->courseProgress
                ->firstWhere('course_id', $course->id);

            if (!$progress || $progress->status !== 'completed') {
                return false;
            }

            // Check minimum completion percentage
            $minRequired = $course->pivot->min_completion_percentage;
            if ($minRequired && $progress->completion_percentage < $minRequired) {
                return false;
            }
        }

        return true;
    }

    public function getAvailableCourses(LearningPathEnrollment $enrollment): array
    {
        return $enrollment->courseProgress
            ->where('status', 'available')
            ->map(fn ($cp) => $cp->course)
            ->values()
            ->all();
    }

    public function getLockedCourses(LearningPathEnrollment $enrollment): array
    {
        $lockedCourses = [];

        foreach ($enrollment->courseProgress->where('status', 'locked') as $progress) {
            $course = $enrollment->learningPath->courses
                ->firstWhere('id', $progress->course_id);

            if ($course) {
                $prereqResult = $this->checkPrerequisites($enrollment, $course);
                $lockedCourses[] = [
                    'course' => $course,
                    'reason' => $prereqResult->reason ?? 'Kursus sebelumnya belum selesai.',
                    'missing_prerequisites' => $prereqResult->missingPrerequisites,
                ];
            }
        }

        return $lockedCourses;
    }

    /**
     * Check sequential prerequisite (previous course must be completed).
     */
    private function checkSequentialPrerequisite(
        LearningPathEnrollment $enrollment,
        Course $course
    ): PrerequisiteCheckResult {
        $courses = $enrollment->learningPath->courses()
            ->orderBy('pivot_position')
            ->get();

        $courseIndex = $courses->search(fn ($c) => $c->id === $course->id);

        // First course has no prerequisites
        if ($courseIndex === 0 || $courseIndex === false) {
            return PrerequisiteCheckResult::met();
        }

        // Check previous course is completed
        $previousCourse = $courses[$courseIndex - 1];
        $previousProgress = $enrollment->courseProgress
            ->firstWhere('course_id', $previousCourse->id);

        if (!$previousProgress || $previousProgress->status !== 'completed') {
            return PrerequisiteCheckResult::notMet(
                [['id' => $previousCourse->id, 'title' => $previousCourse->title]],
                "Selesaikan kursus \"{$previousCourse->title}\" terlebih dahulu."
            );
        }

        return PrerequisiteCheckResult::met();
    }

    /**
     * Check if minimum completion percentage is met.
     */
    private function checkMinCompletionPercentage(
        LearningPathEnrollment $enrollment,
        $pathCourse
    ): PrerequisiteCheckResult {
        $minRequired = $pathCourse->pivot->min_completion_percentage;

        if (!$minRequired) {
            return PrerequisiteCheckResult::met();
        }

        // Get the course progress for prerequisites
        $prerequisites = json_decode($pathCourse->pivot->prerequisites ?? '[]', true);

        foreach ($prerequisites as $prereqCourseId) {
            $progress = $enrollment->courseProgress
                ->firstWhere('course_id', $prereqCourseId);

            if ($progress && $progress->completion_percentage < $minRequired) {
                $prereqCourse = Course::find($prereqCourseId);
                return PrerequisiteCheckResult::notMet(
                    [['id' => $prereqCourseId, 'title' => $prereqCourse?->title ?? 'Unknown']],
                    "Diperlukan minimal {$minRequired}% penyelesaian pada kursus prasyarat."
                );
            }
        }

        return PrerequisiteCheckResult::met();
    }

    /**
     * Build CourseProgressDTO with lock reason.
     */
    private function buildCourseProgressDTO(
        LearningPathCourseProgress $progress,
        LearningPathEnrollment $enrollment
    ): CourseProgressDTO {
        $course = $progress->course;
        $pathCourse = $enrollment->learningPath->courses
            ->firstWhere('id', $course->id);

        $lockReason = null;
        if ($progress->status === 'locked') {
            $prereqResult = $this->checkPrerequisites($enrollment, $course);
            $lockReason = $prereqResult->reason;
        }

        return new CourseProgressDTO(
            courseId: $course->id,
            courseTitle: $course->title,
            status: $progress->status,
            position: $pathCourse?->pivot->position ?? 0,
            isRequired: $pathCourse?->pivot->is_required ?? true,
            completionPercentage: $progress->completion_percentage,
            minRequiredPercentage: $pathCourse?->pivot->min_completion_percentage,
            prerequisites: json_decode($pathCourse?->pivot->prerequisites ?? '[]', true),
            lockReason: $lockReason,
            unlockedAt: $progress->unlocked_at,
            startedAt: $progress->started_at,
            completedAt: $progress->completed_at,
        );
    }
}
```

---

## 1.7 Event Listeners

### EnrollInFirstCourse

```php
<?php

namespace App\Domain\LearningPath\Listeners;

use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;
use App\Domain\Enrollment\DTOs\CreateEnrollmentDTO;
use App\Domain\LearningPath\Events\PathEnrollmentCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

class EnrollInFirstCourse implements ShouldQueue
{
    public function __construct(
        private EnrollmentServiceContract $enrollmentService,
    ) {}

    public function handle(PathEnrollmentCreated $event): void
    {
        $enrollment = $event->enrollment;
        $path = $enrollment->learningPath;

        // Get first course in path
        $firstCourse = $path->courses()
            ->orderBy('pivot_position')
            ->first();

        if (!$firstCourse) {
            return;
        }

        // Check if user can enroll
        $user = $enrollment->user;
        if (!$this->enrollmentService->canEnroll($user, $firstCourse)) {
            return; // Already enrolled or course not available
        }

        // Enroll user in first course
        $result = $this->enrollmentService->enroll(new CreateEnrollmentDTO(
            userId: $user->id,
            courseId: $firstCourse->id,
        ));

        // Link course enrollment to path course progress
        $courseProgress = $enrollment->courseProgress
            ->firstWhere('course_id', $firstCourse->id);

        if ($courseProgress) {
            $courseProgress->update([
                'enrollment_id' => $result->enrollment->id,
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
        }
    }
}
```

### CheckPathPrerequisites

```php
<?php

namespace App\Domain\LearningPath\Listeners;

use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\LearningPath\Contracts\PathProgressServiceContract;
use App\Models\LearningPathCourseProgress;
use App\Models\LearningPathEnrollment;
use Illuminate\Contracts\Queue\ShouldQueue;

class CheckPathPrerequisites implements ShouldQueue
{
    public function __construct(
        private PathProgressServiceContract $progressService,
    ) {}

    public function handle(EnrollmentCompleted $event): void
    {
        $courseEnrollment = $event->enrollment;

        // Find all path enrollments that include this course
        $pathCourseProgress = LearningPathCourseProgress::with('pathEnrollment')
            ->where('course_id', $courseEnrollment->course_id)
            ->where('enrollment_id', $courseEnrollment->id)
            ->get();

        foreach ($pathCourseProgress as $progress) {
            $pathEnrollment = $progress->pathEnrollment;

            if ($pathEnrollment && $pathEnrollment->status === 'active') {
                $this->progressService->onCourseCompleted(
                    $pathEnrollment,
                    $courseEnrollment
                );
            }
        }
    }
}
```

### UpdatePathProgress

```php
<?php

namespace App\Domain\LearningPath\Listeners;

use App\Domain\Progress\Events\ProgressUpdated;
use App\Models\LearningPathCourseProgress;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdatePathProgress implements ShouldQueue
{
    public function handle(ProgressUpdated $event): void
    {
        // Find path course progress for this enrollment
        $pathProgress = LearningPathCourseProgress::where(
            'enrollment_id',
            $event->enrollmentId
        )->first();

        if ($pathProgress) {
            $pathProgress->update([
                'completion_percentage' => $event->progressPercentage,
            ]);
        }
    }
}
```

---

## 1.8 Service Provider Registration

### Add to `AppServiceProvider`

```php
// In app/Providers/AppServiceProvider.php

use App\Domain\LearningPath\Contracts\PathEnrollmentServiceContract;
use App\Domain\LearningPath\Contracts\PathProgressServiceContract;
use App\Domain\LearningPath\Services\PathEnrollmentService;
use App\Domain\LearningPath\Services\PathProgressService;

public function register(): void
{
    // ... existing bindings

    $this->app->bind(PathEnrollmentServiceContract::class, PathEnrollmentService::class);
    $this->app->bind(PathProgressServiceContract::class, PathProgressService::class);
}
```

### Register Event Listeners in `EventServiceProvider`

```php
// In app/Providers/EventServiceProvider.php

use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\LearningPath\Events\PathEnrollmentCreated;
use App\Domain\LearningPath\Listeners\CheckPathPrerequisites;
use App\Domain\LearningPath\Listeners\EnrollInFirstCourse;
use App\Domain\Progress\Events\ProgressUpdated;
use App\Domain\LearningPath\Listeners\UpdatePathProgress;

protected $listen = [
    // ... existing listeners

    PathEnrollmentCreated::class => [
        EnrollInFirstCourse::class,
    ],

    EnrollmentCompleted::class => [
        // ... existing listeners
        CheckPathPrerequisites::class,
    ],

    ProgressUpdated::class => [
        // ... existing listeners
        UpdatePathProgress::class,
    ],
];
```

---

## Implementation Checklist

- [ ] Create directory structure
- [ ] Create `PathEnrollmentServiceContract`
- [ ] Create `PathProgressServiceContract`
- [ ] Create DTOs (PathEnrollmentDTO, PathProgressDTO, CourseProgressDTO, PrerequisiteCheckResult)
- [ ] Create Events (PathEnrollmentCreated, PathCompleted, PathDropped, CourseUnlockedInPath)
- [ ] Create Exceptions
- [ ] Implement `PathEnrollmentService`
- [ ] Implement `PathProgressService`
- [ ] Create Listeners (EnrollInFirstCourse, CheckPathPrerequisites, UpdatePathProgress)
- [ ] Register services in AppServiceProvider
- [ ] Register listeners in EventServiceProvider
- [ ] Write unit tests for services

---

## Next Phase

Continue to [Phase 2: Database Enhancement](./02-DATABASE-ENHANCEMENT.md)
