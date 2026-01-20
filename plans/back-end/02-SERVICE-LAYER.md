# Phase 2: Service Layer Extraction

**Duration**: Week 3-5
**Dependencies**: Phase 1 complete
**Priority**: Critical - Core architectural change

---

## Objectives

1. Extract business logic from models into services
2. Extract complex operations from controllers
3. Create single-responsibility services
4. Establish service-to-service communication patterns
5. Maintain backward compatibility during transition

---

## 2.1 Current Problems

### God Models Analysis

**`Enrollment` Model** (app/Models/Enrollment.php)
```php
// Lines 67-78: Creates LessonProgress - should be in ProgressService
public function getOrCreateProgressForLesson(Lesson $lesson): LessonProgress

// Lines 80-104: Complex calculation + state transition - should be in ProgressService
public function recalculateCourseProgress(): void
```

**`LessonProgress` Model** (app/Models/LessonProgress.php)
```php
// Lines 55-81: Progress update logic - should be in ProgressService
public function updateProgress(int $page, ?int $totalPages = null, ?array $metadata = null): self

// Lines 96-118: Media progress + auto-complete - should be in ProgressService
public function updateMediaProgress(int $positionSeconds, int $durationSeconds): self

// Lines 136-146: Completion + course recalc trigger - should be in ProgressService
public function markCompleted(): self
```

**`Assessment` Model** (app/Models/Assessment.php)
```php
// Lines 110-131: Eligibility check - should be in AssessmentEligibilityService
public function canBeAttemptedBy(User $user): bool
```

### Fat Controller Analysis

**`AssessmentController`** (app/Http/Controllers/AssessmentController.php)
```php
// Lines 263-334: 72 lines of submission logic
// - Answer validation
// - File upload handling
// - Auto-grading logic
// - Score calculation
// - Status determination
// Should be: GradingService + SubmissionService

// Lines 355-374: Grading logic embedded in controller
protected function autoGradeQuestion(Question $question, string $answerText): bool
// Should be: GradingStrategy implementations
```

---

## 2.2 Service Architecture

### Service Hierarchy

```
Domain Services (Business Logic)
├── EnrollmentService         # Enrollment lifecycle
├── ProgressTrackingService   # Progress calculation and updates
├── GradingService            # Assessment grading orchestration
├── CoursePublishingService   # Course state management
└── AssessmentAttemptService  # Attempt lifecycle

Application Services (Orchestration)
├── CourseManagementService   # High-level course operations
└── LearnerDashboardService   # Learner-specific aggregations
```

### Service Responsibilities

| Service | Responsibility | NOT Responsible For |
|---------|---------------|---------------------|
| EnrollmentService | Create, modify enrollments | Progress tracking |
| ProgressTrackingService | Update/calculate progress | Enrollment rules |
| GradingService | Grade assessments | Creating attempts |
| AssessmentAttemptService | Attempt lifecycle | Grading logic |
| CoursePublishingService | Publish/unpublish | Course CRUD |

---

## 2.3 EnrollmentService

### Contract

```php
<?php
// app/Domain/Enrollment/Contracts/EnrollmentServiceContract.php

namespace App\Domain\Enrollment\Contracts;

use App\Domain\Enrollment\DTOs\CreateEnrollmentDTO;
use App\Domain\Enrollment\DTOs\EnrollmentResult;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

interface EnrollmentServiceContract
{
    /**
     * Enroll a user in a course.
     *
     * @throws \App\Domain\Enrollment\Exceptions\AlreadyEnrolledException
     * @throws \App\Domain\Enrollment\Exceptions\CourseNotPublishedException
     * @throws \App\Domain\Enrollment\Exceptions\CourseCapacityReachedException
     */
    public function enroll(CreateEnrollmentDTO $dto): EnrollmentResult;

    /**
     * Check if a user can enroll in a course.
     */
    public function canEnroll(User $user, Course $course): bool;

    /**
     * Get enrollment for user and course.
     */
    public function getEnrollment(User $user, Course $course): ?Enrollment;

    /**
     * Drop a user from a course.
     *
     * @throws \App\Domain\Enrollment\Exceptions\EnrollmentNotFoundException
     */
    public function drop(Enrollment $enrollment, ?string $reason = null): void;

    /**
     * Mark enrollment as completed.
     * Usually called by ProgressTrackingService.
     */
    public function complete(Enrollment $enrollment): void;
}
```

### Implementation

```php
<?php
// app/Domain/Enrollment/Services/EnrollmentService.php

namespace App\Domain\Enrollment\Services;

use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;
use App\Domain\Enrollment\DTOs\CreateEnrollmentDTO;
use App\Domain\Enrollment\DTOs\EnrollmentResult;
use App\Domain\Enrollment\Events\UserEnrolled;
use App\Domain\Enrollment\Events\UserDropped;
use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\Enrollment\Exceptions\AlreadyEnrolledException;
use App\Domain\Enrollment\Exceptions\CourseNotPublishedException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnrollmentService implements EnrollmentServiceContract
{
    public function enroll(CreateEnrollmentDTO $dto): EnrollmentResult
    {
        $user = User::findOrFail($dto->userId);
        $course = Course::findOrFail($dto->courseId);

        // Validation
        $this->validateEnrollment($user, $course);

        return DB::transaction(function () use ($dto, $user, $course) {
            $enrollment = Enrollment::create([
                'user_id' => $dto->userId,
                'course_id' => $dto->courseId,
                'status' => 'active',
                'progress_percentage' => 0,
                'enrolled_at' => $dto->enrolledAt ?? now(),
                'invited_by' => $dto->invitedBy,
            ]);

            // Dispatch event (Phase 4)
            UserEnrolled::dispatch($enrollment);

            return new EnrollmentResult(
                enrollment: $enrollment,
                isNewEnrollment: true,
            );
        });
    }

    public function canEnroll(User $user, Course $course): bool
    {
        // Already enrolled?
        if ($this->getEnrollment($user, $course)) {
            return false;
        }

        // Course published?
        if ($course->status !== 'published') {
            return false;
        }

        // Course visible to user?
        if ($course->visibility === 'hidden') {
            return false;
        }

        // Restricted course - check invitation
        if ($course->visibility === 'restricted') {
            return $this->hasValidInvitation($user, $course);
        }

        return true;
    }

    public function getEnrollment(User $user, Course $course): ?Enrollment
    {
        return Enrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();
    }

    public function drop(Enrollment $enrollment, ?string $reason = null): void
    {
        DB::transaction(function () use ($enrollment, $reason) {
            $enrollment->update([
                'status' => 'dropped',
            ]);

            UserDropped::dispatch($enrollment, $reason);
        });
    }

    public function complete(Enrollment $enrollment): void
    {
        if ($enrollment->status === 'completed') {
            return; // Already complete, idempotent
        }

        DB::transaction(function () use ($enrollment) {
            $enrollment->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            EnrollmentCompleted::dispatch($enrollment);
        });
    }

    protected function validateEnrollment(User $user, Course $course): void
    {
        if ($this->getEnrollment($user, $course)) {
            throw new AlreadyEnrolledException($user->id, $course->id);
        }

        if ($course->status !== 'published') {
            throw new CourseNotPublishedException($course->id);
        }
    }

    protected function hasValidInvitation(User $user, Course $course): bool
    {
        return $course->invitations()
            ->where('email', $user->email)
            ->where('status', 'accepted')
            ->exists();
    }
}
```

### DTOs

```php
<?php
// app/Domain/Enrollment/DTOs/EnrollmentResult.php

namespace App\Domain\Enrollment\DTOs;

use App\Domain\Shared\DTOs\DataTransferObject;
use App\Models\Enrollment;

final readonly class EnrollmentResult extends DataTransferObject
{
    public function __construct(
        public Enrollment $enrollment,
        public bool $isNewEnrollment,
        public ?string $message = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            enrollment: $data['enrollment'],
            isNewEnrollment: $data['is_new_enrollment'],
            message: $data['message'] ?? null,
        );
    }
}
```

---

## 2.4 ProgressTrackingService

### Contract

```php
<?php
// app/Domain/Progress/Contracts/ProgressTrackingServiceContract.php

namespace App\Domain\Progress\Contracts;

use App\Domain\Progress\DTOs\ProgressUpdateDTO;
use App\Domain\Progress\DTOs\ProgressResult;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;

interface ProgressTrackingServiceContract
{
    /**
     * Update progress for a lesson.
     */
    public function updateProgress(ProgressUpdateDTO $dto): ProgressResult;

    /**
     * Mark a lesson as completed.
     */
    public function completeLession(Enrollment $enrollment, Lesson $lesson): ProgressResult;

    /**
     * Get or create progress record for enrollment and lesson.
     */
    public function getOrCreateProgress(Enrollment $enrollment, Lesson $lesson): LessonProgress;

    /**
     * Recalculate course progress for an enrollment.
     */
    public function recalculateCourseProgress(Enrollment $enrollment): float;

    /**
     * Check if all lessons are completed.
     */
    public function isEnrollmentComplete(Enrollment $enrollment): bool;
}
```

### Implementation

```php
<?php
// app/Domain/Progress/Services/ProgressTrackingService.php

namespace App\Domain\Progress\Services;

use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;
use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Domain\Progress\Contracts\ProgressTrackingServiceContract;
use App\Domain\Progress\DTOs\ProgressUpdateDTO;
use App\Domain\Progress\DTOs\ProgressResult;
use App\Domain\Progress\Events\LessonCompleted;
use App\Domain\Progress\Events\ProgressUpdated;
use App\Domain\Shared\ValueObjects\Percentage;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\DB;

class ProgressTrackingService implements ProgressTrackingServiceContract
{
    public function __construct(
        protected ProgressCalculatorContract $calculator,
        protected EnrollmentServiceContract $enrollmentService,
    ) {}

    public function updateProgress(ProgressUpdateDTO $dto): ProgressResult
    {
        $enrollment = Enrollment::findOrFail($dto->enrollmentId);
        $lesson = Lesson::findOrFail($dto->lessonId);
        $progress = $this->getOrCreateProgress($enrollment, $lesson);

        return DB::transaction(function () use ($dto, $enrollment, $progress) {
            $wasCompleted = $progress->is_completed;

            // Update based on progress type
            if ($dto->isMediaProgress()) {
                $this->updateMediaProgress($progress, $dto);
            } elseif ($dto->isPageProgress()) {
                $this->updatePageProgress($progress, $dto);
            }

            // Add time spent if provided
            if ($dto->timeSpentSeconds !== null) {
                $progress->time_spent_seconds += $dto->timeSpentSeconds;
            }

            $progress->last_viewed_at = now();
            $progress->save();

            // Update last lesson for resume feature
            $enrollment->update(['last_lesson_id' => $dto->lessonId]);

            // Check if lesson was just completed
            $justCompleted = !$wasCompleted && $progress->is_completed;

            if ($justCompleted) {
                $this->handleLessonCompletion($enrollment, $progress);
            }

            ProgressUpdated::dispatch($enrollment, $progress);

            return new ProgressResult(
                progress: $progress->fresh(),
                coursePercentage: new Percentage($enrollment->progress_percentage),
                lessonCompleted: $justCompleted,
                courseCompleted: $enrollment->status === 'completed',
            );
        });
    }

    public function completeLession(Enrollment $enrollment, Lesson $lesson): ProgressResult
    {
        $progress = $this->getOrCreateProgress($enrollment, $lesson);

        if ($progress->is_completed) {
            // Already complete - return current state
            return new ProgressResult(
                progress: $progress,
                coursePercentage: new Percentage($enrollment->progress_percentage),
                lessonCompleted: false,
                courseCompleted: $enrollment->status === 'completed',
            );
        }

        return DB::transaction(function () use ($enrollment, $progress) {
            $progress->update([
                'is_completed' => true,
                'completed_at' => now(),
            ]);

            $this->handleLessonCompletion($enrollment, $progress);

            return new ProgressResult(
                progress: $progress->fresh(),
                coursePercentage: new Percentage($enrollment->fresh()->progress_percentage),
                lessonCompleted: true,
                courseCompleted: $enrollment->fresh()->status === 'completed',
            );
        });
    }

    public function getOrCreateProgress(Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        return LessonProgress::firstOrCreate(
            [
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'current_page' => 1,
                'highest_page_reached' => 1,
                'time_spent_seconds' => 0,
                'is_completed' => false,
            ]
        );
    }

    public function recalculateCourseProgress(Enrollment $enrollment): float
    {
        $percentage = $this->calculator->calculate($enrollment);

        $enrollment->update([
            'progress_percentage' => $percentage,
        ]);

        // Check if enrollment should be marked complete
        if ($this->isEnrollmentComplete($enrollment) && $enrollment->status !== 'completed') {
            $this->enrollmentService->complete($enrollment);
        }

        return $percentage;
    }

    public function isEnrollmentComplete(Enrollment $enrollment): bool
    {
        return $this->calculator->isComplete($enrollment);
    }

    protected function updateMediaProgress(LessonProgress $progress, ProgressUpdateDTO $dto): void
    {
        $progress->media_position_seconds = $dto->mediaPositionSeconds;
        $progress->media_duration_seconds = $dto->mediaDurationSeconds;

        if ($dto->mediaDurationSeconds > 0) {
            $percentage = ($dto->mediaPositionSeconds / $dto->mediaDurationSeconds) * 100;
            $progress->media_progress_percentage = min(100, round($percentage, 2));

            // Auto-complete at 90% watched
            if ($percentage >= 90 && !$progress->is_completed) {
                $progress->is_completed = true;
                $progress->completed_at = now();
            }
        }
    }

    protected function updatePageProgress(LessonProgress $progress, ProgressUpdateDTO $dto): void
    {
        $progress->current_page = $dto->currentPage;

        if ($dto->totalPages !== null) {
            $progress->total_pages = $dto->totalPages;
        }

        if ($dto->metadata !== null) {
            $progress->pagination_metadata = $dto->metadata;
        }

        // Update highest page reached
        if ($dto->currentPage > $progress->highest_page_reached) {
            $progress->highest_page_reached = $dto->currentPage;
        }

        // Auto-complete when reaching last page
        if ($progress->total_pages !== null &&
            $progress->highest_page_reached >= $progress->total_pages &&
            !$progress->is_completed) {
            $progress->is_completed = true;
            $progress->completed_at = now();
        }
    }

    protected function handleLessonCompletion(Enrollment $enrollment, LessonProgress $progress): void
    {
        // Dispatch lesson completed event
        LessonCompleted::dispatch($enrollment, $progress->lesson);

        // Recalculate course progress
        $this->recalculateCourseProgress($enrollment);
    }
}
```

### Progress Calculator (Strategy Implementation)

```php
<?php
// app/Domain/Progress/Services/LessonBasedProgressCalculator.php

namespace App\Domain\Progress\Services;

use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Models\Enrollment;

class LessonBasedProgressCalculator implements ProgressCalculatorContract
{
    public function calculate(Enrollment $enrollment): float
    {
        $totalLessons = $enrollment->course->lessons()->count();

        if ($totalLessons === 0) {
            return 0;
        }

        $completedLessons = $enrollment->lessonProgress()
            ->where('is_completed', true)
            ->count();

        return round(($completedLessons / $totalLessons) * 100, 1);
    }

    public function isComplete(Enrollment $enrollment): bool
    {
        $totalLessons = $enrollment->course->lessons()->count();

        if ($totalLessons === 0) {
            return false;
        }

        $completedLessons = $enrollment->lessonProgress()
            ->where('is_completed', true)
            ->count();

        return $completedLessons >= $totalLessons;
    }
}
```

### ProgressResult DTO

```php
<?php
// app/Domain/Progress/DTOs/ProgressResult.php

namespace App\Domain\Progress\DTOs;

use App\Domain\Shared\DTOs\DataTransferObject;
use App\Domain\Shared\ValueObjects\Percentage;
use App\Models\LessonProgress;

final readonly class ProgressResult extends DataTransferObject
{
    public function __construct(
        public LessonProgress $progress,
        public Percentage $coursePercentage,
        public bool $lessonCompleted,
        public bool $courseCompleted,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            progress: $data['progress'],
            coursePercentage: new Percentage($data['course_percentage']),
            lessonCompleted: $data['lesson_completed'],
            courseCompleted: $data['course_completed'],
        );
    }

    public function toResponse(): array
    {
        return [
            'progress' => $this->progress->toArray(),
            'course_percentage' => $this->coursePercentage->value,
            'lesson_completed' => $this->lessonCompleted,
            'course_completed' => $this->courseCompleted,
        ];
    }
}
```

---

## 2.5 GradingService

### Contract

```php
<?php
// app/Domain/Assessment/Contracts/GradingServiceContract.php

namespace App\Domain\Assessment\Contracts;

use App\Domain\Assessment\DTOs\GradingResult;
use App\Domain\Assessment\DTOs\SubmissionDTO;
use App\Domain\Assessment\DTOs\AttemptResult;
use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\Question;

interface GradingServiceContract
{
    /**
     * Process a complete assessment submission.
     */
    public function processSubmission(AssessmentAttempt $attempt, SubmissionDTO $submission): AttemptResult;

    /**
     * Grade a single answer.
     */
    public function gradeAnswer(Question $question, mixed $answer): GradingResult;

    /**
     * Manually grade an answer (for essay/upload types).
     */
    public function manualGrade(AttemptAnswer $answer, float $score, ?string $feedback = null): GradingResult;

    /**
     * Calculate final score for an attempt.
     */
    public function calculateFinalScore(AssessmentAttempt $attempt): AttemptResult;

    /**
     * Check if attempt requires manual grading.
     */
    public function requiresManualGrading(AssessmentAttempt $attempt): bool;
}
```

### Implementation

```php
<?php
// app/Domain/Assessment/Services/GradingService.php

namespace App\Domain\Assessment\Services;

use App\Domain\Assessment\Contracts\GradingServiceContract;
use App\Domain\Assessment\Contracts\GradingStrategyResolverContract;
use App\Domain\Assessment\DTOs\GradingResult;
use App\Domain\Assessment\DTOs\SubmissionDTO;
use App\Domain\Assessment\DTOs\AttemptResult;
use App\Domain\Assessment\DTOs\AnswerSubmission;
use App\Domain\Assessment\Events\AssessmentSubmitted;
use App\Domain\Assessment\Events\AssessmentGraded;
use App\Domain\Assessment\Events\AnswerGraded;
use App\Domain\Assessment\ValueObjects\Score;
use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GradingService implements GradingServiceContract
{
    public function __construct(
        protected GradingStrategyResolverContract $strategyResolver,
    ) {}

    public function processSubmission(AssessmentAttempt $attempt, SubmissionDTO $submission): AttemptResult
    {
        return DB::transaction(function () use ($attempt, $submission) {
            $totalScore = 0;
            $requiresManualGrading = false;

            foreach ($submission->answers as $answerSubmission) {
                $result = $this->processAnswer($attempt, $answerSubmission);

                if ($result->answer->score !== null) {
                    $totalScore += $result->answer->score;
                } else {
                    $requiresManualGrading = true;
                }
            }

            // Determine status based on grading requirements
            $status = $requiresManualGrading ? 'submitted' : 'graded';
            $maxScore = $attempt->assessment->total_points;
            $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;
            $passed = $percentage >= $attempt->assessment->passing_score;

            $attempt->update([
                'status' => $status,
                'score' => $requiresManualGrading ? null : $totalScore,
                'max_score' => $maxScore,
                'percentage' => $requiresManualGrading ? null : $percentage,
                'passed' => $requiresManualGrading ? null : $passed,
                'submitted_at' => now(),
            ]);

            AssessmentSubmitted::dispatch($attempt);

            if (!$requiresManualGrading) {
                AssessmentGraded::dispatch($attempt);
            }

            return new AttemptResult(
                attempt: $attempt->fresh(),
                score: $requiresManualGrading ? null : new Score($totalScore, $maxScore),
                requiresManualGrading: $requiresManualGrading,
                passed: $requiresManualGrading ? null : $passed,
            );
        });
    }

    public function gradeAnswer(Question $question, mixed $answer): GradingResult
    {
        $strategy = $this->strategyResolver->resolve($question);

        if ($strategy === null) {
            // No auto-grading strategy available
            return GradingResult::partial(
                score: 0,
                maxScore: $question->points,
                feedback: 'Memerlukan penilaian manual',
            );
        }

        return $strategy->grade($question, $answer);
    }

    public function manualGrade(AttemptAnswer $answer, float $score, ?string $feedback = null): GradingResult
    {
        $maxScore = $answer->question->points;
        $isCorrect = $score >= $maxScore;

        $answer->update([
            'score' => $score,
            'is_correct' => $isCorrect,
            'feedback' => $feedback,
            'graded_at' => now(),
            'graded_by' => auth()->id(),
        ]);

        AnswerGraded::dispatch($answer);

        // Check if all answers are now graded
        $attempt = $answer->attempt;
        if (!$this->requiresManualGrading($attempt)) {
            $this->calculateFinalScore($attempt);
        }

        return new GradingResult(
            isCorrect: $isCorrect,
            score: $score,
            maxScore: $maxScore,
            feedback: $feedback,
        );
    }

    public function calculateFinalScore(AssessmentAttempt $attempt): AttemptResult
    {
        $totalScore = $attempt->answers()->sum('score');
        $maxScore = $attempt->assessment->total_points;
        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;
        $passed = $percentage >= $attempt->assessment->passing_score;

        $attempt->update([
            'status' => 'graded',
            'score' => $totalScore,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'passed' => $passed,
            'graded_at' => now(),
        ]);

        AssessmentGraded::dispatch($attempt);

        return new AttemptResult(
            attempt: $attempt->fresh(),
            score: new Score($totalScore, $maxScore),
            requiresManualGrading: false,
            passed: $passed,
        );
    }

    public function requiresManualGrading(AssessmentAttempt $attempt): bool
    {
        return $attempt->answers()
            ->whereNull('score')
            ->exists();
    }

    protected function processAnswer(AssessmentAttempt $attempt, AnswerSubmission $answerSubmission): object
    {
        $question = Question::findOrFail($answerSubmission->questionId);

        // Handle file upload
        $filePath = null;
        if ($answerSubmission->file !== null) {
            $filePath = $answerSubmission->file->store('assessment_answers', 'public');
        }

        // Attempt auto-grading
        $gradingResult = null;
        if (!$question->requiresManualGrading()) {
            $gradingResult = $this->gradeAnswer($question, $answerSubmission->answerText);
        }

        // Create answer record
        $answer = $attempt->answers()->create([
            'question_id' => $question->id,
            'answer_text' => $answerSubmission->answerText,
            'file_path' => $filePath,
            'is_correct' => $gradingResult?->isCorrect,
            'score' => $gradingResult?->score,
        ]);

        return (object) [
            'answer' => $answer,
            'gradingResult' => $gradingResult,
        ];
    }
}
```

### Submission DTOs

```php
<?php
// app/Domain/Assessment/DTOs/SubmissionDTO.php

namespace App\Domain\Assessment\DTOs;

use App\Domain\Shared\DTOs\DataTransferObject;
use Illuminate\Http\Request;

final readonly class SubmissionDTO extends DataTransferObject
{
    /**
     * @param array<AnswerSubmission> $answers
     */
    public function __construct(
        public array $answers,
    ) {}

    public static function fromArray(array $data): static
    {
        $answers = array_map(
            fn($answer) => AnswerSubmission::fromArray($answer),
            $data['answers'] ?? []
        );

        return new static(answers: $answers);
    }

    public static function fromRequest(Request $request): static
    {
        $validated = $request->validated();
        $answers = [];

        foreach ($validated['answers'] ?? [] as $index => $answerData) {
            $file = $request->file("answers.{$index}.file");
            $answers[] = new AnswerSubmission(
                questionId: $answerData['question_id'],
                answerText: $answerData['answer_text'] ?? null,
                file: $file,
            );
        }

        return new static(answers: $answers);
    }
}
```

```php
<?php
// app/Domain/Assessment/DTOs/AnswerSubmission.php

namespace App\Domain\Assessment\DTOs;

use App\Domain\Shared\DTOs\DataTransferObject;
use Illuminate\Http\UploadedFile;

final readonly class AnswerSubmission extends DataTransferObject
{
    public function __construct(
        public int $questionId,
        public ?string $answerText = null,
        public ?UploadedFile $file = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            questionId: $data['question_id'],
            answerText: $data['answer_text'] ?? null,
            file: $data['file'] ?? null,
        );
    }
}
```

```php
<?php
// app/Domain/Assessment/DTOs/AttemptResult.php

namespace App\Domain\Assessment\DTOs;

use App\Domain\Assessment\ValueObjects\Score;
use App\Domain\Shared\DTOs\DataTransferObject;
use App\Models\AssessmentAttempt;

final readonly class AttemptResult extends DataTransferObject
{
    public function __construct(
        public AssessmentAttempt $attempt,
        public ?Score $score,
        public bool $requiresManualGrading,
        public ?bool $passed,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            attempt: $data['attempt'],
            score: isset($data['score']) ? new Score($data['score']['earned'], $data['score']['max']) : null,
            requiresManualGrading: $data['requires_manual_grading'],
            passed: $data['passed'] ?? null,
        );
    }

    public function toResponse(): array
    {
        return [
            'attempt_id' => $this->attempt->id,
            'status' => $this->attempt->status,
            'score' => $this->score?->jsonSerialize(),
            'requires_manual_grading' => $this->requiresManualGrading,
            'passed' => $this->passed,
        ];
    }
}
```

---

## 2.6 AssessmentAttemptService

### Contract

```php
<?php
// app/Domain/Assessment/Contracts/AssessmentAttemptServiceContract.php

namespace App\Domain\Assessment\Contracts;

use App\Domain\Assessment\DTOs\StartAttemptResult;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\User;

interface AssessmentAttemptServiceContract
{
    /**
     * Check if user can attempt the assessment.
     *
     * @throws \App\Domain\Assessment\Exceptions\AssessmentNotPublishedException
     * @throws \App\Domain\Assessment\Exceptions\NotEnrolledException
     * @throws \App\Domain\Assessment\Exceptions\MaxAttemptsReachedException
     */
    public function canAttempt(User $user, Assessment $assessment): bool;

    /**
     * Start a new assessment attempt.
     *
     * @throws \App\Domain\Assessment\Exceptions\CannotStartAttemptException
     */
    public function startAttempt(User $user, Assessment $assessment): StartAttemptResult;

    /**
     * Get the current in-progress attempt for a user.
     */
    public function getCurrentAttempt(User $user, Assessment $assessment): ?AssessmentAttempt;

    /**
     * Cancel an in-progress attempt.
     */
    public function cancelAttempt(AssessmentAttempt $attempt): void;
}
```

### Implementation

```php
<?php
// app/Domain/Assessment/Services/AssessmentAttemptService.php

namespace App\Domain\Assessment\Services;

use App\Domain\Assessment\Contracts\AssessmentAttemptServiceContract;
use App\Domain\Assessment\DTOs\StartAttemptResult;
use App\Domain\Assessment\Events\AttemptStarted;
use App\Domain\Assessment\Events\AttemptCancelled;
use App\Domain\Assessment\Exceptions\AssessmentNotPublishedException;
use App\Domain\Assessment\Exceptions\NotEnrolledException;
use App\Domain\Assessment\Exceptions\MaxAttemptsReachedException;
use App\Domain\Assessment\Exceptions\CannotStartAttemptException;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssessmentAttemptService implements AssessmentAttemptServiceContract
{
    public function canAttempt(User $user, Assessment $assessment): bool
    {
        try {
            $this->validateCanAttempt($user, $assessment);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function startAttempt(User $user, Assessment $assessment): StartAttemptResult
    {
        $this->validateCanAttempt($user, $assessment);

        // Check for existing in-progress attempt
        $existingAttempt = $this->getCurrentAttempt($user, $assessment);
        if ($existingAttempt) {
            return new StartAttemptResult(
                attempt: $existingAttempt,
                isResumed: true,
            );
        }

        return DB::transaction(function () use ($user, $assessment) {
            $attemptNumber = $this->getNextAttemptNumber($user, $assessment);

            $attempt = AssessmentAttempt::create([
                'assessment_id' => $assessment->id,
                'user_id' => $user->id,
                'attempt_number' => $attemptNumber,
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            AttemptStarted::dispatch($attempt);

            return new StartAttemptResult(
                attempt: $attempt,
                isResumed: false,
            );
        });
    }

    public function getCurrentAttempt(User $user, Assessment $assessment): ?AssessmentAttempt
    {
        return AssessmentAttempt::query()
            ->where('user_id', $user->id)
            ->where('assessment_id', $assessment->id)
            ->where('status', 'in_progress')
            ->first();
    }

    public function cancelAttempt(AssessmentAttempt $attempt): void
    {
        if (!$attempt->isInProgress()) {
            throw new CannotStartAttemptException(
                'Cannot cancel an attempt that is not in progress'
            );
        }

        $attempt->update([
            'status' => 'cancelled',
        ]);

        AttemptCancelled::dispatch($attempt);
    }

    protected function validateCanAttempt(User $user, Assessment $assessment): void
    {
        // Check if assessment is published
        if ($assessment->status !== 'published') {
            throw new AssessmentNotPublishedException($assessment->id);
        }

        // Check if user is enrolled in the course
        $isEnrolled = $user->enrollments()
            ->where('course_id', $assessment->course_id)
            ->where('status', 'active')
            ->exists();

        if (!$isEnrolled) {
            throw new NotEnrolledException($user->id, $assessment->course_id);
        }

        // Check attempt limits
        if ($assessment->max_attempts > 0) {
            $completedAttempts = $this->countCompletedAttempts($user, $assessment);

            if ($completedAttempts >= $assessment->max_attempts) {
                throw new MaxAttemptsReachedException(
                    $user->id,
                    $assessment->id,
                    $assessment->max_attempts,
                    $completedAttempts
                );
            }
        }
    }

    protected function getNextAttemptNumber(User $user, Assessment $assessment): int
    {
        return AssessmentAttempt::query()
            ->where('user_id', $user->id)
            ->where('assessment_id', $assessment->id)
            ->max('attempt_number') + 1;
    }

    protected function countCompletedAttempts(User $user, Assessment $assessment): int
    {
        return AssessmentAttempt::query()
            ->where('user_id', $user->id)
            ->where('assessment_id', $assessment->id)
            ->whereIn('status', ['submitted', 'graded', 'completed'])
            ->count();
    }
}
```

---

## 2.7 CoursePublishingService

### Contract

```php
<?php
// app/Domain/Course/Contracts/CoursePublishingServiceContract.php

namespace App\Domain\Course\Contracts;

use App\Domain\Course\DTOs\PublishResult;
use App\Models\Course;
use App\Models\User;

interface CoursePublishingServiceContract
{
    /**
     * Publish a course.
     *
     * @throws \App\Domain\Course\Exceptions\CannotPublishException
     */
    public function publish(Course $course, User $publisher): PublishResult;

    /**
     * Unpublish a course (return to draft).
     *
     * @throws \App\Domain\Course\Exceptions\CannotUnpublishException
     */
    public function unpublish(Course $course): PublishResult;

    /**
     * Archive a course.
     *
     * @throws \App\Domain\Course\Exceptions\CannotArchiveException
     */
    public function archive(Course $course): PublishResult;

    /**
     * Check if a course can be published.
     */
    public function canPublish(Course $course): bool;

    /**
     * Get validation errors preventing publication.
     *
     * @return array<string>
     */
    public function getPublishValidationErrors(Course $course): array;
}
```

### Implementation

```php
<?php
// app/Domain/Course/Services/CoursePublishingService.php

namespace App\Domain\Course\Services;

use App\Domain\Course\Contracts\CoursePublishingServiceContract;
use App\Domain\Course\DTOs\PublishResult;
use App\Domain\Course\Events\CoursePublished;
use App\Domain\Course\Events\CourseUnpublished;
use App\Domain\Course\Events\CourseArchived;
use App\Domain\Course\Exceptions\CannotPublishException;
use App\Domain\Course\Exceptions\CannotUnpublishException;
use App\Domain\Course\Exceptions\CannotArchiveException;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CoursePublishingService implements CoursePublishingServiceContract
{
    public function publish(Course $course, User $publisher): PublishResult
    {
        $errors = $this->getPublishValidationErrors($course);

        if (!empty($errors)) {
            throw new CannotPublishException($course->id, $errors);
        }

        return DB::transaction(function () use ($course, $publisher) {
            $previousStatus = $course->status;

            $course->update([
                'status' => 'published',
                'published_at' => now(),
                'published_by' => $publisher->id,
            ]);

            CoursePublished::dispatch($course, $publisher, $previousStatus);

            return new PublishResult(
                course: $course->fresh(),
                previousStatus: $previousStatus,
                newStatus: 'published',
            );
        });
    }

    public function unpublish(Course $course): PublishResult
    {
        if ($course->status !== 'published') {
            throw new CannotUnpublishException(
                $course->id,
                "Course is not published (current status: {$course->status})"
            );
        }

        return DB::transaction(function () use ($course) {
            $previousStatus = $course->status;

            $course->update([
                'status' => 'draft',
                'published_at' => null,
                'published_by' => null,
            ]);

            CourseUnpublished::dispatch($course, $previousStatus);

            return new PublishResult(
                course: $course->fresh(),
                previousStatus: $previousStatus,
                newStatus: 'draft',
            );
        });
    }

    public function archive(Course $course): PublishResult
    {
        if ($course->status === 'archived') {
            throw new CannotArchiveException(
                $course->id,
                'Course is already archived'
            );
        }

        return DB::transaction(function () use ($course) {
            $previousStatus = $course->status;

            $course->update([
                'status' => 'archived',
            ]);

            CourseArchived::dispatch($course, $previousStatus);

            return new PublishResult(
                course: $course->fresh(),
                previousStatus: $previousStatus,
                newStatus: 'archived',
            );
        });
    }

    public function canPublish(Course $course): bool
    {
        return empty($this->getPublishValidationErrors($course));
    }

    public function getPublishValidationErrors(Course $course): array
    {
        $errors = [];

        // Must have title
        if (empty($course->title)) {
            $errors[] = 'Kursus harus memiliki judul';
        }

        // Must have at least one section
        if ($course->sections()->count() === 0) {
            $errors[] = 'Kursus harus memiliki minimal satu bagian';
        }

        // Must have at least one lesson
        if ($course->lessons()->count() === 0) {
            $errors[] = 'Kursus harus memiliki minimal satu materi';
        }

        // All lessons must have content
        $emptyLessons = $course->lessons()
            ->whereNull('content')
            ->where('content_type', 'text')
            ->count();

        if ($emptyLessons > 0) {
            $errors[] = "Ada {$emptyLessons} materi yang belum memiliki konten";
        }

        // Must have category
        if ($course->category_id === null) {
            $errors[] = 'Kursus harus memiliki kategori';
        }

        return $errors;
    }
}
```

---

## 2.8 Controller Refactoring

### Before: Fat AssessmentController::submitAttempt

```php
// Lines 263-334: 72 lines of mixed concerns
public function submitAttempt(Request $request, Course $course, Assessment $assessment, AssessmentAttempt $attempt): RedirectResponse
{
    // Authorization, validation, file handling, grading, scoring all mixed
}
```

### After: Slim Controller

```php
<?php
// app/Http/Controllers/AssessmentController.php (refactored)

namespace App\Http\Controllers;

use App\Domain\Assessment\Contracts\GradingServiceContract;
use App\Domain\Assessment\Contracts\AssessmentAttemptServiceContract;
use App\Domain\Assessment\DTOs\SubmissionDTO;
use App\Http\Requests\Assessment\SubmitAttemptRequest;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AssessmentController extends Controller
{
    public function __construct(
        protected GradingServiceContract $gradingService,
        protected AssessmentAttemptServiceContract $attemptService,
    ) {}

    /**
     * Start a new assessment attempt.
     */
    public function startAttempt(Course $course, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('attempt', [$assessment, $course]);

        $result = $this->attemptService->startAttempt(auth()->user(), $assessment);

        $message = $result->isResumed
            ? 'Melanjutkan penilaian sebelumnya.'
            : 'Penilaian dimulai. Silakan jawab semua pertanyaan.';

        return redirect()
            ->route('assessments.attempt', [$course, $assessment, $result->attempt])
            ->with('success', $message);
    }

    /**
     * Submit assessment attempt.
     */
    public function submitAttempt(
        SubmitAttemptRequest $request,
        Course $course,
        Assessment $assessment,
        AssessmentAttempt $attempt
    ): RedirectResponse {
        Gate::authorize('submitAttempt', [$attempt, $assessment, $course]);

        // DTO handles validation and file extraction
        $submission = SubmissionDTO::fromRequest($request);

        // Service handles all business logic
        $result = $this->gradingService->processSubmission($attempt, $submission);

        $message = $result->requiresManualGrading
            ? 'Penilaian berhasil diserahkan. Menunggu penilaian manual.'
            : 'Penilaian berhasil diserahkan dan dinilai.';

        return redirect()
            ->route('assessments.attempt.complete', [$course, $assessment, $attempt])
            ->with('success', $message);
    }

    // ... other methods remain similar but slim
}
```

**Improvement:**
- Controller: 15 lines → focused on HTTP concerns
- Grading logic: Moved to GradingService
- File handling: Handled in DTO/Service
- Validation: Moved to Form Request
- Testable: Services can be unit tested

---

## 2.9 Service Provider Bindings

```php
<?php
// app/Providers/DomainServiceProvider.php (updated)

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Contracts
use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;
use App\Domain\Progress\Contracts\ProgressTrackingServiceContract;
use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Domain\Assessment\Contracts\GradingServiceContract;
use App\Domain\Assessment\Contracts\AssessmentAttemptServiceContract;
use App\Domain\Course\Contracts\CoursePublishingServiceContract;

// Implementations
use App\Domain\Enrollment\Services\EnrollmentService;
use App\Domain\Progress\Services\ProgressTrackingService;
use App\Domain\Progress\Services\LessonBasedProgressCalculator;
use App\Domain\Assessment\Services\GradingService;
use App\Domain\Assessment\Services\AssessmentAttemptService;
use App\Domain\Course\Services\CoursePublishingService;

class DomainServiceProvider extends ServiceProvider
{
    public array $bindings = [
        EnrollmentServiceContract::class => EnrollmentService::class,
        ProgressTrackingServiceContract::class => ProgressTrackingService::class,
        ProgressCalculatorContract::class => LessonBasedProgressCalculator::class,
        GradingServiceContract::class => GradingService::class,
        AssessmentAttemptServiceContract::class => AssessmentAttemptService::class,
        CoursePublishingServiceContract::class => CoursePublishingService::class,
    ];

    public function register(): void
    {
        // Additional complex bindings if needed
    }

    public function boot(): void
    {
        // Event listener registration will be added in Phase 4
    }
}
```

---

## 2.10 Implementation Checklist

### Week 3: Core Services

- [ ] EnrollmentService
  - [ ] Contract definition
  - [ ] Implementation
  - [ ] DTOs (CreateEnrollmentDTO, EnrollmentResult)
  - [ ] Exceptions (AlreadyEnrolledException, etc.)
  - [ ] Unit tests

- [ ] ProgressTrackingService
  - [ ] Contract definition
  - [ ] Implementation
  - [ ] ProgressCalculator implementation
  - [ ] DTOs (ProgressUpdateDTO, ProgressResult)
  - [ ] Unit tests

### Week 4: Assessment Services

- [ ] GradingService
  - [ ] Contract definition
  - [ ] Implementation
  - [ ] DTOs (SubmissionDTO, GradingResult, AttemptResult)
  - [ ] Unit tests

- [ ] AssessmentAttemptService
  - [ ] Contract definition
  - [ ] Implementation
  - [ ] DTOs (StartAttemptResult)
  - [ ] Exceptions (MaxAttemptsReachedException, etc.)
  - [ ] Unit tests

### Week 5: Publishing & Integration

- [ ] CoursePublishingService
  - [ ] Contract definition
  - [ ] Implementation
  - [ ] DTOs (PublishResult)
  - [ ] Exceptions (CannotPublishException, etc.)
  - [ ] Unit tests

- [ ] Controller refactoring
  - [ ] AssessmentController
  - [ ] LessonProgressController
  - [ ] EnrollmentController
  - [ ] CoursePublishController

- [ ] Service Provider setup
  - [ ] Register all bindings
  - [ ] Test application boots

---

## 2.11 Model Cleanup

### After Service Extraction

**Enrollment Model - Remove:**
```php
// DELETE these methods (moved to ProgressTrackingService)
public function getOrCreateProgressForLesson(Lesson $lesson): LessonProgress
public function recalculateCourseProgress(): void
```

**LessonProgress Model - Remove:**
```php
// DELETE these methods (moved to ProgressTrackingService)
public function updateProgress(int $page, ?int $totalPages = null, ?array $metadata = null): self
public function updateMediaProgress(int $positionSeconds, int $durationSeconds): self
public function markCompleted(): self
```

**Assessment Model - Remove:**
```php
// DELETE this method (moved to AssessmentAttemptService)
public function canBeAttemptedBy(User $user): bool
```

### Models Should Only Contain:
- Relationships
- Scopes
- Accessors/Mutators (simple ones)
- Casts

---

## Next Phase

Once Phase 2 is complete, proceed to [Phase 3: State Machine Implementation](./03-STATE-MACHINES.md).

Services are now ready to have their state transitions formalized with proper state machines.
