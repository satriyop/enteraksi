# Phase 3: Prerequisites and Branching Logic

> **Phase**: 3 of 6
> **Estimated Effort**: Medium
> **Prerequisites**: Phase 2 (Database Enhancement)

---

## Objectives

- Implement prerequisite checking logic
- Create unlock condition evaluator
- Support different prerequisite types
- Implement simple branching (future: assessment-based)
- Create controllers for path enrollment actions

---

## 3.1 Prerequisite Types

The system supports several prerequisite types, stored as JSON in the `prerequisites` column of `learning_path_course` pivot table.

### Prerequisite Schema

```json
{
  "type": "sequential | courses | assessment",
  "courses": [1, 2, 3],
  "min_score": 70,
  "operator": "all | any"
}
```

### Supported Types

| Type | Description | Example |
|------|-------------|---------|
| `sequential` | Previous course in path must be completed | Default behavior |
| `courses` | Specific courses must be completed | `{"type": "courses", "courses": [1, 2]}` |
| `assessment` | Minimum score on assessment required | `{"type": "assessment", "min_score": 70}` |

---

## 3.2 Prerequisite Evaluator Service

### File: `app/Domain/LearningPath/Services/PrerequisiteEvaluator.php`

```php
<?php

namespace App\Domain\LearningPath\Services;

use App\Domain\LearningPath\DTOs\PrerequisiteCheckResult;
use App\Models\Course;
use App\Models\LearningPathEnrollment;

class PrerequisiteEvaluator
{
    /**
     * Evaluate prerequisites for a course in a learning path.
     */
    public function evaluate(
        LearningPathEnrollment $enrollment,
        Course $course,
        ?array $prerequisites = null
    ): PrerequisiteCheckResult {
        // Get prerequisites from pivot if not provided
        if ($prerequisites === null) {
            $pathCourse = $enrollment->learningPath->courses()
                ->where('course_id', $course->id)
                ->first();

            $prerequisites = json_decode($pathCourse?->pivot->prerequisites ?? '[]', true);
        }

        // No explicit prerequisites - use sequential logic
        if (empty($prerequisites)) {
            return $this->evaluateSequential($enrollment, $course);
        }

        // Evaluate based on type
        $type = $prerequisites['type'] ?? 'sequential';

        return match ($type) {
            'sequential' => $this->evaluateSequential($enrollment, $course),
            'courses' => $this->evaluateCourses($enrollment, $prerequisites),
            'assessment' => $this->evaluateAssessment($enrollment, $prerequisites, $course),
            default => $this->evaluateSequential($enrollment, $course),
        };
    }

    /**
     * Evaluate sequential prerequisite (previous course must be completed).
     */
    private function evaluateSequential(
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

        // Check min completion percentage if set
        $pathCourse = $enrollment->learningPath->courses()
            ->where('course_id', $previousCourse->id)
            ->first();

        $minRequired = $pathCourse?->pivot->min_completion_percentage;

        if ($minRequired && $previousProgress->completion_percentage < $minRequired) {
            return PrerequisiteCheckResult::notMet(
                [['id' => $previousCourse->id, 'title' => $previousCourse->title]],
                "Diperlukan minimal {$minRequired}% penyelesaian pada \"{$previousCourse->title}\"."
            );
        }

        return PrerequisiteCheckResult::met();
    }

    /**
     * Evaluate specific courses prerequisite.
     */
    private function evaluateCourses(
        LearningPathEnrollment $enrollment,
        array $prerequisites
    ): PrerequisiteCheckResult {
        $requiredCourseIds = $prerequisites['courses'] ?? [];
        $operator = $prerequisites['operator'] ?? 'all';

        if (empty($requiredCourseIds)) {
            return PrerequisiteCheckResult::met();
        }

        $missing = [];
        $completed = 0;

        foreach ($requiredCourseIds as $courseId) {
            $progress = $enrollment->courseProgress
                ->firstWhere('course_id', $courseId);

            if (!$progress || $progress->status !== 'completed') {
                $course = Course::find($courseId);
                $missing[] = [
                    'id' => $courseId,
                    'title' => $course?->title ?? 'Unknown',
                ];
            } else {
                $completed++;
            }
        }

        // For "any" operator, only one needs to be completed
        if ($operator === 'any' && $completed > 0) {
            return PrerequisiteCheckResult::met();
        }

        // For "all" operator, all must be completed
        if ($operator === 'all' && empty($missing)) {
            return PrerequisiteCheckResult::met();
        }

        $missingTitles = implode(', ', array_column($missing, 'title'));
        $message = $operator === 'all'
            ? "Selesaikan semua kursus prasyarat: {$missingTitles}"
            : "Selesaikan minimal satu kursus prasyarat: {$missingTitles}";

        return PrerequisiteCheckResult::notMet($missing, $message);
    }

    /**
     * Evaluate assessment-based prerequisite.
     */
    private function evaluateAssessment(
        LearningPathEnrollment $enrollment,
        array $prerequisites,
        Course $course
    ): PrerequisiteCheckResult {
        $minScore = $prerequisites['min_score'] ?? 70;
        $assessmentCourseId = $prerequisites['assessment_course_id'] ?? null;

        // If no specific assessment course, check previous course
        if (!$assessmentCourseId) {
            $courses = $enrollment->learningPath->courses()
                ->orderBy('pivot_position')
                ->get();

            $courseIndex = $courses->search(fn ($c) => $c->id === $course->id);

            if ($courseIndex > 0) {
                $assessmentCourseId = $courses[$courseIndex - 1]->id;
            }
        }

        if (!$assessmentCourseId) {
            return PrerequisiteCheckResult::met();
        }

        // Get the course enrollment to check assessment score
        $courseProgress = $enrollment->courseProgress
            ->firstWhere('course_id', $assessmentCourseId);

        if (!$courseProgress || !$courseProgress->enrollment_id) {
            $assessmentCourse = Course::find($assessmentCourseId);
            return PrerequisiteCheckResult::notMet(
                [['id' => $assessmentCourseId, 'title' => $assessmentCourse?->title ?? 'Unknown']],
                "Selesaikan kursus prasyarat terlebih dahulu."
            );
        }

        // Get the highest assessment score from course enrollment
        $courseEnrollment = $courseProgress->enrollment;
        $highestScore = $this->getHighestAssessmentScore($courseEnrollment);

        if ($highestScore === null || $highestScore < $minScore) {
            $assessmentCourse = Course::find($assessmentCourseId);
            return PrerequisiteCheckResult::notMet(
                [['id' => $assessmentCourseId, 'title' => $assessmentCourse?->title ?? 'Unknown']],
                "Diperlukan nilai minimal {$minScore}% pada asesmen. Nilai Anda: " . ($highestScore ?? 0) . "%"
            );
        }

        return PrerequisiteCheckResult::met();
    }

    /**
     * Get highest assessment score from a course enrollment.
     */
    private function getHighestAssessmentScore($courseEnrollment): ?int
    {
        if (!$courseEnrollment) {
            return null;
        }

        // Check if assessment attempts exist
        if (!method_exists($courseEnrollment, 'assessmentAttempts')) {
            // Fallback: use completion percentage as proxy
            return $courseEnrollment->progress_percentage ?? null;
        }

        $attempts = $courseEnrollment->assessmentAttempts()
            ->where('status', 'completed')
            ->get();

        if ($attempts->isEmpty()) {
            return null;
        }

        return $attempts->max('score');
    }
}
```

---

## 3.3 Path Enrollment Controller

### File: `app/Http/Controllers/LearnerPathController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Domain\LearningPath\Contracts\PathEnrollmentServiceContract;
use App\Domain\LearningPath\Contracts\PathProgressServiceContract;
use App\Domain\LearningPath\Exceptions\AlreadyEnrolledInPathException;
use App\Domain\LearningPath\Exceptions\PathNotPublishedException;
use App\Models\LearningPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LearnerPathController extends Controller
{
    public function __construct(
        private PathEnrollmentServiceContract $enrollmentService,
        private PathProgressServiceContract $progressService,
    ) {}

    /**
     * Display learner's enrolled paths ("My Learning Paths").
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $enrollments = $this->enrollmentService->getActiveEnrollments($user)
            ->load(['learningPath.courses', 'courseProgress']);

        // Transform for frontend
        $paths = $enrollments->map(function ($enrollment) {
            $progress = $this->progressService->getProgress($enrollment);

            return [
                'id' => $enrollment->id,
                'learning_path' => [
                    'id' => $enrollment->learningPath->id,
                    'title' => $enrollment->learningPath->title,
                    'description' => $enrollment->learningPath->description,
                    'slug' => $enrollment->learningPath->slug,
                    'thumbnail_url' => $enrollment->learningPath->thumbnail_url,
                    'difficulty_level' => $enrollment->learningPath->difficulty_level,
                    'estimated_duration' => $enrollment->learningPath->estimated_duration,
                    'courses_count' => $enrollment->learningPath->courses->count(),
                ],
                'status' => $enrollment->status,
                'enrolled_at' => $enrollment->enrolled_at->toISOString(),
                'progress_percentage' => $progress->overallPercentage,
                'completed_courses' => $progress->completedCourses,
                'total_courses' => $progress->totalCourses,
                'current_course' => $this->getCurrentCourse($progress),
            ];
        });

        // Get completed paths
        $completedEnrollments = $user->completedLearningPathEnrollments()
            ->with('learningPath')
            ->latest('completed_at')
            ->get();

        return Inertia::render('learner/paths/Index', [
            'activePaths' => $paths,
            'completedPaths' => $completedEnrollments->map(fn ($e) => [
                'id' => $e->id,
                'learning_path' => [
                    'id' => $e->learningPath->id,
                    'title' => $e->learningPath->title,
                    'slug' => $e->learningPath->slug,
                ],
                'completed_at' => $e->completed_at->toISOString(),
            ]),
        ]);
    }

    /**
     * Display path details with progress.
     */
    public function show(LearningPath $learningPath): Response
    {
        $user = Auth::user();
        $enrollment = $this->enrollmentService->getEnrollment($user, $learningPath);

        if (!$enrollment) {
            // Not enrolled - show path overview with enroll option
            return $this->showPathOverview($learningPath);
        }

        // Enrolled - show progress
        $progress = $this->progressService->getProgress($enrollment);

        return Inertia::render('learner/paths/Show', [
            'learningPath' => [
                'id' => $learningPath->id,
                'title' => $learningPath->title,
                'description' => $learningPath->description,
                'objectives' => $learningPath->objectives,
                'slug' => $learningPath->slug,
                'thumbnail_url' => $learningPath->thumbnail_url,
                'difficulty_level' => $learningPath->difficulty_level,
                'estimated_duration' => $learningPath->estimated_duration,
            ],
            'enrollment' => [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'enrolled_at' => $enrollment->enrolled_at->toISOString(),
                'completed_at' => $enrollment->completed_at?->toISOString(),
            ],
            'progress' => [
                'overall_percentage' => $progress->overallPercentage,
                'total_courses' => $progress->totalCourses,
                'completed_courses' => $progress->completedCourses,
                'in_progress_courses' => $progress->inProgressCourses,
                'available_courses' => $progress->availableCourses,
                'locked_courses' => $progress->lockedCourses,
                'is_completed' => $progress->isCompleted,
                'courses' => $progress->courses,
            ],
        ]);
    }

    /**
     * Enroll current user in a learning path.
     */
    public function enroll(LearningPath $learningPath): RedirectResponse
    {
        $user = Auth::user();

        try {
            $this->enrollmentService->enroll($user, $learningPath);

            return redirect()->route('learner.paths.show', $learningPath)
                ->with('success', "Berhasil mendaftar di jalur pembelajaran \"{$learningPath->title}\".");

        } catch (AlreadyEnrolledInPathException $e) {
            return redirect()->route('learner.paths.show', $learningPath)
                ->with('info', 'Anda sudah terdaftar di jalur pembelajaran ini.');

        } catch (PathNotPublishedException $e) {
            return redirect()->route('learning-paths.index')
                ->with('error', 'Jalur pembelajaran ini belum dipublikasikan.');
        }
    }

    /**
     * Drop current user from a learning path.
     */
    public function drop(LearningPath $learningPath, Request $request): RedirectResponse
    {
        $user = Auth::user();
        $enrollment = $this->enrollmentService->getEnrollment($user, $learningPath);

        if (!$enrollment) {
            return redirect()->route('learner.paths.index')
                ->with('error', 'Anda tidak terdaftar di jalur pembelajaran ini.');
        }

        $reason = $request->input('reason');
        $this->enrollmentService->drop($enrollment, $reason);

        return redirect()->route('learner.paths.index')
            ->with('success', "Anda telah keluar dari jalur pembelajaran \"{$learningPath->title}\".");
    }

    /**
     * Show path overview for non-enrolled users.
     */
    private function showPathOverview(LearningPath $learningPath): Response
    {
        $learningPath->load(['courses' => function ($query) {
            $query->orderBy('pivot_position');
        }, 'creator']);

        $user = Auth::user();
        $canEnroll = $this->enrollmentService->canEnroll($user, $learningPath);

        return Inertia::render('learner/paths/Overview', [
            'learningPath' => [
                'id' => $learningPath->id,
                'title' => $learningPath->title,
                'description' => $learningPath->description,
                'objectives' => $learningPath->objectives,
                'slug' => $learningPath->slug,
                'thumbnail_url' => $learningPath->thumbnail_url,
                'difficulty_level' => $learningPath->difficulty_level,
                'estimated_duration' => $learningPath->estimated_duration,
                'is_published' => $learningPath->is_published,
                'creator' => $learningPath->creator?->name,
                'courses_count' => $learningPath->courses->count(),
                'courses' => $learningPath->courses->map(fn ($course) => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'description' => $course->description,
                    'estimated_duration' => $course->estimated_duration,
                    'is_required' => $course->pivot->is_required,
                    'position' => $course->pivot->position,
                ]),
            ],
            'can_enroll' => $canEnroll,
            'enrolled_count' => $learningPath->pathEnrollments()->count(),
        ]);
    }

    /**
     * Get current course (first in-progress or available).
     */
    private function getCurrentCourse($progress): ?array
    {
        $inProgress = collect($progress->courses)
            ->firstWhere('status', 'in_progress');

        if ($inProgress) {
            return [
                'id' => $inProgress->courseId,
                'title' => $inProgress->courseTitle,
                'status' => 'in_progress',
            ];
        }

        $available = collect($progress->courses)
            ->firstWhere('status', 'available');

        if ($available) {
            return [
                'id' => $available->courseId,
                'title' => $available->courseTitle,
                'status' => 'available',
            ];
        }

        return null;
    }
}
```

---

## 3.4 Routes

### File: `routes/learner_paths.php`

```php
<?php

use App\Http\Controllers\LearnerPathController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Learner's enrolled paths
    Route::get('/my-learning-paths', [LearnerPathController::class, 'index'])
        ->name('learner.paths.index');

    // View path (enrolled: show progress, not enrolled: show overview)
    Route::get('/my-learning-paths/{learningPath}', [LearnerPathController::class, 'show'])
        ->name('learner.paths.show');

    // Enroll in path
    Route::post('/learning-paths/{learningPath}/enroll', [LearnerPathController::class, 'enroll'])
        ->name('learner.paths.enroll');

    // Drop from path
    Route::delete('/my-learning-paths/{learningPath}/drop', [LearnerPathController::class, 'drop'])
        ->name('learner.paths.drop');
});
```

### Register in `bootstrap/app.php` or `routes/web.php`

```php
// In routes/web.php
require __DIR__.'/learner_paths.php';
```

---

## 3.5 Unlock Logic Flow

```
┌─────────────────────────────────────────────────────────────┐
│                  Course Completion Flow                      │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  1. User completes course enrollment                         │
│     ↓                                                        │
│  2. EnrollmentCompleted event fired                         │
│     ↓                                                        │
│  3. CheckPathPrerequisites listener triggered               │
│     ↓                                                        │
│  4. Find all LearningPathCourseProgress for this course     │
│     ↓                                                        │
│  5. For each path enrollment:                               │
│     a. Mark course as completed                              │
│     b. Call unlockNextCourses()                             │
│        ↓                                                     │
│        For each locked course:                              │
│        - Evaluate prerequisites using PrerequisiteEvaluator │
│        - If met: status → 'available', fire event          │
│     c. Update path progress percentage                       │
│     d. Check if path completed → complete enrollment         │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 3.6 Branching Logic (Future Enhancement)

For assessment-based branching (e.g., "if score < 70%, take remedial course"):

### Prerequisites JSON Schema for Branching

```json
{
  "type": "branching",
  "conditions": [
    {
      "if": {
        "course_id": 5,
        "operator": "score_less_than",
        "value": 70
      },
      "then": {
        "unlock": [6],
        "lock": [7]
      }
    },
    {
      "if": {
        "course_id": 5,
        "operator": "score_greater_or_equal",
        "value": 70
      },
      "then": {
        "unlock": [7],
        "skip": [6]
      }
    }
  ]
}
```

### Branching Evaluator (Future Implementation)

```php
<?php

namespace App\Domain\LearningPath\Services;

use App\Models\LearningPathEnrollment;

class BranchingEvaluator
{
    /**
     * Evaluate branching conditions and determine which courses to unlock/lock.
     */
    public function evaluate(
        LearningPathEnrollment $enrollment,
        array $branchingConfig
    ): array {
        $result = [
            'unlock' => [],
            'lock' => [],
            'skip' => [],
        ];

        foreach ($branchingConfig['conditions'] as $condition) {
            if ($this->evaluateCondition($enrollment, $condition['if'])) {
                $result['unlock'] = array_merge($result['unlock'], $condition['then']['unlock'] ?? []);
                $result['lock'] = array_merge($result['lock'], $condition['then']['lock'] ?? []);
                $result['skip'] = array_merge($result['skip'], $condition['then']['skip'] ?? []);
                break; // First matching condition wins
            }
        }

        return $result;
    }

    private function evaluateCondition(LearningPathEnrollment $enrollment, array $condition): bool
    {
        $courseId = $condition['course_id'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        $courseProgress = $enrollment->courseProgress
            ->firstWhere('course_id', $courseId);

        if (!$courseProgress || !$courseProgress->enrollment_id) {
            return false;
        }

        // Get assessment score
        $score = $this->getAssessmentScore($courseProgress->enrollment);

        return match ($operator) {
            'score_less_than' => $score < $value,
            'score_greater_or_equal' => $score >= $value,
            'score_equals' => $score === $value,
            'completed' => $courseProgress->status === 'completed',
            default => false,
        };
    }

    private function getAssessmentScore($enrollment): ?int
    {
        // Implementation depends on assessment module
        return $enrollment->progress_percentage ?? null;
    }
}
```

---

## 3.7 Admin UI Enhancement for Prerequisites

### Update `LearningPathCourseList.vue` Component

Add prerequisite configuration UI:

```vue
<!-- In components/learning_paths/LearningPathCourseList.vue -->
<template>
    <!-- Existing course list item -->
    <div class="space-y-2">
        <!-- ... existing fields ... -->

        <!-- Prerequisites Configuration -->
        <div class="mt-2 p-3 bg-muted rounded-md">
            <Label class="text-sm font-medium">Prasyarat</Label>
            <Select v-model="course.prerequisite_type">
                <SelectTrigger>
                    <SelectValue placeholder="Pilih tipe prasyarat" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="sequential">Kursus sebelumnya (default)</SelectItem>
                    <SelectItem value="courses">Kursus tertentu</SelectItem>
                    <SelectItem value="assessment">Nilai minimal</SelectItem>
                </SelectContent>
            </Select>

            <!-- Course selection for 'courses' type -->
            <div v-if="course.prerequisite_type === 'courses'" class="mt-2">
                <Label class="text-sm">Pilih kursus prasyarat</Label>
                <!-- Multi-select for courses -->
            </div>

            <!-- Min score for 'assessment' type -->
            <div v-if="course.prerequisite_type === 'assessment'" class="mt-2">
                <Label class="text-sm">Nilai minimal (%)</Label>
                <Input
                    type="number"
                    v-model="course.min_assessment_score"
                    min="0"
                    max="100"
                    placeholder="70"
                />
            </div>
        </div>
    </div>
</template>
```

---

## Implementation Checklist

- [ ] Create `PrerequisiteEvaluator` service
- [ ] Create `LearnerPathController`
- [ ] Create routes file `routes/learner_paths.php`
- [ ] Register routes in web.php
- [ ] Update `PathProgressService` to use `PrerequisiteEvaluator`
- [ ] Add prerequisite configuration UI to admin (optional)
- [ ] Write unit tests for `PrerequisiteEvaluator`
- [ ] Write feature tests for enrollment flow

---

## Test Cases for This Phase

1. **Sequential Prerequisites**
   - First course is always unlocked
   - Second course unlocks when first is completed
   - Course stays locked if previous not completed

2. **Course-based Prerequisites**
   - Unlock when all required courses completed (operator: all)
   - Unlock when any required course completed (operator: any)

3. **Assessment Prerequisites**
   - Unlock when score >= minimum
   - Stay locked when score < minimum

4. **Enrollment Flow**
   - Enroll creates path enrollment
   - Enroll initializes course progress
   - First course is marked available
   - Other courses are locked

---

## Next Phase

Continue to [Phase 4: Learner Experience](./04-LEARNER-EXPERIENCE.md)
