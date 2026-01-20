# Phase 5: Dependency Injection & Strategy Patterns

**Duration**: Week 10-11
**Dependencies**: Phase 4 complete
**Priority**: Medium - Enables flexibility and testability

---

## Objectives

1. Implement strategy pattern for grading different question types
2. Create notification strategy for multi-channel delivery
3. Build progress calculator strategies
4. Configure service container with proper bindings
5. Enable easy swapping of implementations

---

## 5.1 Why Strategy Pattern?

### Current Problem

```php
// Current: Hard-coded logic in controller
protected function autoGradeQuestion(Question $question, string $answerText): bool
{
    if ($question->isTrueFalse()) {
        return strtolower(trim($answerText)) === 'true' || strtolower(trim($answerText)) === 'benar';
    }

    if ($question->isMultipleChoice()) {
        // Handle multiple choice...
        return false;
    }

    if ($question->isShortAnswer()) {
        // Handle short answer...
        return false;
    }

    return false;
}

// Problems:
// 1. Hard to add new question types
// 2. Hard to test individual grading logic
// 3. Violates Open/Closed Principle
// 4. All logic in one place
```

### With Strategy Pattern

```php
// After: Each grading type is its own strategy
$strategy = $this->strategyResolver->resolve($question);
$result = $strategy->grade($question, $answer);

// Benefits:
// 1. Add new strategies without modifying existing code
// 2. Test each strategy in isolation
// 3. Runtime strategy selection
// 4. Clear separation of concerns
```

---

## 5.2 Grading Strategies

### Strategy Contract (from Phase 1)

```php
<?php
// app/Domain/Assessment/Contracts/GradingStrategyContract.php

namespace App\Domain\Assessment\Contracts;

use App\Domain\Assessment\DTOs\GradingResult;
use App\Models\Question;

interface GradingStrategyContract
{
    /**
     * Check if this strategy can grade the given question type.
     */
    public function supports(Question $question): bool;

    /**
     * Grade the answer and return a result.
     */
    public function grade(Question $question, mixed $answer): GradingResult;

    /**
     * Get the question types this strategy handles.
     *
     * @return array<string>
     */
    public function getHandledTypes(): array;
}
```

### Multiple Choice Strategy

```php
<?php
// app/Domain/Assessment/Strategies/MultipleChoiceGradingStrategy.php

namespace App\Domain\Assessment\Strategies;

use App\Domain\Assessment\Contracts\GradingStrategyContract;
use App\Domain\Assessment\DTOs\GradingResult;
use App\Models\Question;

class MultipleChoiceGradingStrategy implements GradingStrategyContract
{
    public function supports(Question $question): bool
    {
        return in_array($question->type, $this->getHandledTypes());
    }

    public function getHandledTypes(): array
    {
        return ['multiple_choice', 'single_choice'];
    }

    public function grade(Question $question, mixed $answer): GradingResult
    {
        // $answer can be a single ID or array of IDs
        $selectedIds = is_array($answer) ? $answer : [$answer];
        $correctOptionIds = $question->options()
            ->where('is_correct', true)
            ->pluck('id')
            ->toArray();

        // Sort for comparison
        sort($selectedIds);
        sort($correctOptionIds);

        $isCorrect = $selectedIds === $correctOptionIds;

        if ($isCorrect) {
            return GradingResult::correct(
                points: $question->points,
                feedback: 'Jawaban benar!'
            );
        }

        // Partial credit for multiple choice
        if ($question->type === 'multiple_choice' && count($selectedIds) > 0) {
            $correctSelected = count(array_intersect($selectedIds, $correctOptionIds));
            $totalCorrect = count($correctOptionIds);
            $incorrectSelected = count($selectedIds) - $correctSelected;

            // Partial score: correct answers minus penalties for wrong answers
            $partialScore = max(0, ($correctSelected - $incorrectSelected * 0.5) / $totalCorrect * $question->points);

            if ($partialScore > 0) {
                return GradingResult::partial(
                    score: round($partialScore, 2),
                    maxScore: $question->points,
                    feedback: "Sebagian benar. {$correctSelected} dari {$totalCorrect} jawaban benar."
                );
            }
        }

        return GradingResult::incorrect(
            maxPoints: $question->points,
            feedback: 'Jawaban salah.'
        );
    }
}
```

### True/False Strategy

```php
<?php
// app/Domain/Assessment/Strategies/TrueFalseGradingStrategy.php

namespace App\Domain\Assessment\Strategies;

use App\Domain\Assessment\Contracts\GradingStrategyContract;
use App\Domain\Assessment\DTOs\GradingResult;
use App\Models\Question;

class TrueFalseGradingStrategy implements GradingStrategyContract
{
    protected array $trueValues = ['true', 'benar', '1', 'ya', 'yes'];
    protected array $falseValues = ['false', 'salah', '0', 'tidak', 'no'];

    public function supports(Question $question): bool
    {
        return in_array($question->type, $this->getHandledTypes());
    }

    public function getHandledTypes(): array
    {
        return ['true_false', 'boolean'];
    }

    public function grade(Question $question, mixed $answer): GradingResult
    {
        $normalizedAnswer = $this->normalizeAnswer($answer);
        $correctAnswer = $this->getCorrectAnswer($question);

        if ($normalizedAnswer === null) {
            return GradingResult::incorrect(
                maxPoints: $question->points,
                feedback: 'Jawaban tidak valid.'
            );
        }

        $isCorrect = $normalizedAnswer === $correctAnswer;

        if ($isCorrect) {
            return GradingResult::correct(
                points: $question->points,
                feedback: $correctAnswer ? 'Benar! Pernyataan ini benar.' : 'Benar! Pernyataan ini salah.'
            );
        }

        return GradingResult::incorrect(
            maxPoints: $question->points,
            feedback: $correctAnswer ? 'Jawaban salah. Pernyataan ini sebenarnya benar.' : 'Jawaban salah. Pernyataan ini sebenarnya salah.'
        );
    }

    protected function normalizeAnswer(mixed $answer): ?bool
    {
        if (is_bool($answer)) {
            return $answer;
        }

        $normalized = strtolower(trim((string) $answer));

        if (in_array($normalized, $this->trueValues)) {
            return true;
        }

        if (in_array($normalized, $this->falseValues)) {
            return false;
        }

        return null;
    }

    protected function getCorrectAnswer(Question $question): bool
    {
        // Check options or use correct_answer field
        $correctOption = $question->options()->where('is_correct', true)->first();

        if ($correctOption) {
            return $this->normalizeAnswer($correctOption->content) ?? true;
        }

        // Fallback to correct_answer field
        return (bool) $question->correct_answer;
    }
}
```

### Short Answer Strategy

```php
<?php
// app/Domain/Assessment/Strategies/ShortAnswerGradingStrategy.php

namespace App\Domain\Assessment\Strategies;

use App\Domain\Assessment\Contracts\GradingStrategyContract;
use App\Domain\Assessment\DTOs\GradingResult;
use App\Models\Question;

class ShortAnswerGradingStrategy implements GradingStrategyContract
{
    public function supports(Question $question): bool
    {
        return in_array($question->type, $this->getHandledTypes());
    }

    public function getHandledTypes(): array
    {
        return ['short_answer', 'fill_blank'];
    }

    public function grade(Question $question, mixed $answer): GradingResult
    {
        $answer = trim((string) $answer);

        if (empty($answer)) {
            return GradingResult::incorrect(
                maxPoints: $question->points,
                feedback: 'Tidak ada jawaban.'
            );
        }

        $acceptableAnswers = $this->getAcceptableAnswers($question);

        if (empty($acceptableAnswers)) {
            // No acceptable answers defined - requires manual grading
            return GradingResult::partial(
                score: 0,
                maxScore: $question->points,
                feedback: 'Memerlukan penilaian manual.',
                metadata: ['requires_manual_grading' => true]
            );
        }

        // Check against acceptable answers
        foreach ($acceptableAnswers as $acceptable) {
            if ($this->matchesAnswer($answer, $acceptable, $question->case_sensitive ?? false)) {
                return GradingResult::correct(
                    points: $question->points,
                    feedback: 'Jawaban benar!'
                );
            }
        }

        // Check for partial matches (fuzzy matching)
        $bestMatch = $this->findBestMatch($answer, $acceptableAnswers);
        if ($bestMatch !== null && $bestMatch['similarity'] >= 0.8) {
            return GradingResult::partial(
                score: $question->points * $bestMatch['similarity'],
                maxScore: $question->points,
                feedback: "Hampir benar. Jawaban yang diharapkan: \"{$bestMatch['answer']}\""
            );
        }

        return GradingResult::incorrect(
            maxPoints: $question->points,
            feedback: 'Jawaban salah.'
        );
    }

    protected function getAcceptableAnswers(Question $question): array
    {
        // Can come from correct_answer field (comma-separated) or options
        $answers = [];

        if (!empty($question->correct_answer)) {
            $answers = array_map('trim', explode(',', $question->correct_answer));
        }

        // Also check options marked as correct
        $correctOptions = $question->options()
            ->where('is_correct', true)
            ->pluck('content')
            ->toArray();

        return array_unique(array_merge($answers, $correctOptions));
    }

    protected function matchesAnswer(string $answer, string $acceptable, bool $caseSensitive): bool
    {
        if ($caseSensitive) {
            return $answer === $acceptable;
        }

        return strtolower($answer) === strtolower($acceptable);
    }

    protected function findBestMatch(string $answer, array $acceptableAnswers): ?array
    {
        $bestSimilarity = 0;
        $bestAnswer = null;

        foreach ($acceptableAnswers as $acceptable) {
            similar_text(
                strtolower($answer),
                strtolower($acceptable),
                $percent
            );
            $similarity = $percent / 100;

            if ($similarity > $bestSimilarity) {
                $bestSimilarity = $similarity;
                $bestAnswer = $acceptable;
            }
        }

        if ($bestAnswer !== null) {
            return [
                'answer' => $bestAnswer,
                'similarity' => $bestSimilarity,
            ];
        }

        return null;
    }
}
```

### Manual Grading Strategy (Essay, Upload)

```php
<?php
// app/Domain/Assessment/Strategies/ManualGradingStrategy.php

namespace App\Domain\Assessment\Strategies;

use App\Domain\Assessment\Contracts\GradingStrategyContract;
use App\Domain\Assessment\DTOs\GradingResult;
use App\Models\Question;

class ManualGradingStrategy implements GradingStrategyContract
{
    public function supports(Question $question): bool
    {
        return in_array($question->type, $this->getHandledTypes());
    }

    public function getHandledTypes(): array
    {
        return ['essay', 'long_answer', 'file_upload', 'code'];
    }

    public function grade(Question $question, mixed $answer): GradingResult
    {
        // Manual grading types always return "pending" result
        return new GradingResult(
            isCorrect: false, // Unknown until manually graded
            score: 0, // Will be set by grader
            maxScore: $question->points,
            feedback: 'Menunggu penilaian instruktur.',
            metadata: [
                'requires_manual_grading' => true,
                'grading_rubric' => $question->grading_rubric ?? null,
            ]
        );
    }
}
```

### Strategy Resolver

```php
<?php
// app/Domain/Assessment/Services/GradingStrategyResolver.php

namespace App\Domain\Assessment\Services;

use App\Domain\Assessment\Contracts\GradingStrategyContract;
use App\Domain\Assessment\Contracts\GradingStrategyResolverContract;
use App\Models\Question;
use Illuminate\Support\Collection;

class GradingStrategyResolver implements GradingStrategyResolverContract
{
    protected Collection $strategies;

    public function __construct(iterable $strategies)
    {
        $this->strategies = collect($strategies);
    }

    public function resolve(Question $question): ?GradingStrategyContract
    {
        return $this->strategies->first(
            fn(GradingStrategyContract $strategy) => $strategy->supports($question)
        );
    }

    public function getAllStrategies(): Collection
    {
        return $this->strategies;
    }

    public function getSupportedTypes(): array
    {
        return $this->strategies
            ->flatMap(fn($strategy) => $strategy->getHandledTypes())
            ->unique()
            ->values()
            ->toArray();
    }
}
```

---

## 5.3 Progress Calculator Strategies

### Contract

```php
<?php
// app/Domain/Progress/Contracts/ProgressCalculatorContract.php

namespace App\Domain\Progress\Contracts;

use App\Models\Enrollment;

interface ProgressCalculatorContract
{
    /**
     * Calculate progress for an enrollment.
     *
     * @return float Progress percentage (0-100)
     */
    public function calculate(Enrollment $enrollment): float;

    /**
     * Determine if the enrollment is complete.
     */
    public function isComplete(Enrollment $enrollment): bool;

    /**
     * Get the name of this calculator strategy.
     */
    public function getName(): string;
}
```

### Lesson-Based Calculator (Default)

```php
<?php
// app/Domain/Progress/Strategies/LessonBasedProgressCalculator.php

namespace App\Domain\Progress\Strategies;

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

    public function getName(): string
    {
        return 'lesson_based';
    }
}
```

### Weighted Progress Calculator (Considers lesson duration)

```php
<?php
// app/Domain/Progress/Strategies/WeightedProgressCalculator.php

namespace App\Domain\Progress\Strategies;

use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

class WeightedProgressCalculator implements ProgressCalculatorContract
{
    public function calculate(Enrollment $enrollment): float
    {
        $courseId = $enrollment->course_id;

        // Get total weighted duration
        $totalWeight = DB::table('lessons')
            ->join('course_sections', 'lessons.course_section_id', '=', 'course_sections.id')
            ->where('course_sections.course_id', $courseId)
            ->sum('lessons.estimated_duration_minutes') ?: 1;

        // Get completed weighted duration
        $completedWeight = DB::table('lesson_progress')
            ->join('lessons', 'lesson_progress.lesson_id', '=', 'lessons.id')
            ->join('course_sections', 'lessons.course_section_id', '=', 'course_sections.id')
            ->where('course_sections.course_id', $courseId)
            ->where('lesson_progress.enrollment_id', $enrollment->id)
            ->where('lesson_progress.is_completed', true)
            ->sum('lessons.estimated_duration_minutes');

        return round(($completedWeight / $totalWeight) * 100, 1);
    }

    public function isComplete(Enrollment $enrollment): bool
    {
        return $this->calculate($enrollment) >= 100;
    }

    public function getName(): string
    {
        return 'weighted';
    }
}
```

### Assessment-Inclusive Calculator (Lessons + Assessments)

```php
<?php
// app/Domain/Progress/Strategies/AssessmentInclusiveProgressCalculator.php

namespace App\Domain\Progress\Strategies;

use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Models\Enrollment;

class AssessmentInclusiveProgressCalculator implements ProgressCalculatorContract
{
    protected float $lessonWeight = 0.7;
    protected float $assessmentWeight = 0.3;

    public function calculate(Enrollment $enrollment): float
    {
        $lessonProgress = $this->calculateLessonProgress($enrollment);
        $assessmentProgress = $this->calculateAssessmentProgress($enrollment);

        return round(
            ($lessonProgress * $this->lessonWeight) +
            ($assessmentProgress * $this->assessmentWeight),
            1
        );
    }

    public function isComplete(Enrollment $enrollment): bool
    {
        // All lessons completed
        $totalLessons = $enrollment->course->lessons()->count();
        $completedLessons = $enrollment->lessonProgress()
            ->where('is_completed', true)
            ->count();

        if ($totalLessons > 0 && $completedLessons < $totalLessons) {
            return false;
        }

        // All required assessments passed
        $requiredAssessments = $enrollment->course->assessments()
            ->published()
            ->where('is_required', true)
            ->get();

        foreach ($requiredAssessments as $assessment) {
            $hasPassed = $assessment->attempts()
                ->where('user_id', $enrollment->user_id)
                ->where('passed', true)
                ->exists();

            if (!$hasPassed) {
                return false;
            }
        }

        return true;
    }

    public function getName(): string
    {
        return 'assessment_inclusive';
    }

    protected function calculateLessonProgress(Enrollment $enrollment): float
    {
        $totalLessons = $enrollment->course->lessons()->count();

        if ($totalLessons === 0) {
            return 100; // No lessons = 100% lesson progress
        }

        $completedLessons = $enrollment->lessonProgress()
            ->where('is_completed', true)
            ->count();

        return ($completedLessons / $totalLessons) * 100;
    }

    protected function calculateAssessmentProgress(Enrollment $enrollment): float
    {
        $assessments = $enrollment->course->assessments()->published()->get();

        if ($assessments->isEmpty()) {
            return 100; // No assessments = 100% assessment progress
        }

        $passedCount = 0;

        foreach ($assessments as $assessment) {
            $hasPassed = $assessment->attempts()
                ->where('user_id', $enrollment->user_id)
                ->where('passed', true)
                ->exists();

            if ($hasPassed) {
                $passedCount++;
            }
        }

        return ($passedCount / $assessments->count()) * 100;
    }
}
```

---

## 5.4 Notification Strategies

### Contract

```php
<?php
// app/Domain/Shared/Contracts/NotificationChannelContract.php

namespace App\Domain\Shared\Contracts;

use App\Models\User;
use Illuminate\Notifications\Notification;

interface NotificationChannelContract
{
    /**
     * Check if this channel is available for the user.
     */
    public function isAvailable(User $user): bool;

    /**
     * Send the notification through this channel.
     */
    public function send(User $user, Notification $notification): void;

    /**
     * Get the channel name.
     */
    public function getName(): string;
}
```

### Email Channel

```php
<?php
// app/Domain/Shared/Notifications/Channels/EmailChannel.php

namespace App\Domain\Shared\Notifications\Channels;

use App\Domain\Shared\Contracts\NotificationChannelContract;
use App\Models\User;
use Illuminate\Notifications\Notification;

class EmailChannel implements NotificationChannelContract
{
    public function isAvailable(User $user): bool
    {
        return !empty($user->email) && $user->email_verified_at !== null;
    }

    public function send(User $user, Notification $notification): void
    {
        if (!method_exists($notification, 'toMail')) {
            return;
        }

        $user->notify($notification);
    }

    public function getName(): string
    {
        return 'email';
    }
}
```

### Database/In-App Channel

```php
<?php
// app/Domain/Shared/Notifications/Channels/DatabaseChannel.php

namespace App\Domain\Shared\Notifications\Channels;

use App\Domain\Shared\Contracts\NotificationChannelContract;
use App\Models\User;
use Illuminate\Notifications\Notification;

class DatabaseChannel implements NotificationChannelContract
{
    public function isAvailable(User $user): bool
    {
        return true; // Always available
    }

    public function send(User $user, Notification $notification): void
    {
        if (!method_exists($notification, 'toArray')) {
            return;
        }

        $user->notifications()->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => get_class($notification),
            'data' => $notification->toArray($user),
        ]);
    }

    public function getName(): string
    {
        return 'database';
    }
}
```

### Multi-Channel Notification Service

```php
<?php
// app/Domain/Shared/Services/NotificationService.php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\Contracts\NotificationChannelContract;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class NotificationService
{
    protected Collection $channels;

    public function __construct(iterable $channels)
    {
        $this->channels = collect($channels);
    }

    /**
     * Send notification through all available channels.
     */
    public function send(User $user, Notification $notification, ?array $viaChannels = null): void
    {
        $channels = $viaChannels
            ? $this->channels->filter(fn($c) => in_array($c->getName(), $viaChannels))
            : $this->channels;

        foreach ($channels as $channel) {
            if ($channel->isAvailable($user)) {
                $channel->send($user, $notification);
            }
        }
    }

    /**
     * Get available channels for a user.
     */
    public function getAvailableChannels(User $user): array
    {
        return $this->channels
            ->filter(fn($channel) => $channel->isAvailable($user))
            ->map(fn($channel) => $channel->getName())
            ->values()
            ->toArray();
    }
}
```

---

## 5.5 Service Container Configuration

### Domain Service Provider (Updated)

```php
<?php
// app/Providers/DomainServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Contracts
use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;
use App\Domain\Progress\Contracts\ProgressTrackingServiceContract;
use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Domain\Assessment\Contracts\GradingServiceContract;
use App\Domain\Assessment\Contracts\AssessmentAttemptServiceContract;
use App\Domain\Assessment\Contracts\GradingStrategyResolverContract;
use App\Domain\Course\Contracts\CoursePublishingServiceContract;

// Services
use App\Domain\Enrollment\Services\EnrollmentService;
use App\Domain\Progress\Services\ProgressTrackingService;
use App\Domain\Assessment\Services\GradingService;
use App\Domain\Assessment\Services\AssessmentAttemptService;
use App\Domain\Assessment\Services\GradingStrategyResolver;
use App\Domain\Course\Services\CoursePublishingService;

// Strategies
use App\Domain\Assessment\Strategies\MultipleChoiceGradingStrategy;
use App\Domain\Assessment\Strategies\TrueFalseGradingStrategy;
use App\Domain\Assessment\Strategies\ShortAnswerGradingStrategy;
use App\Domain\Assessment\Strategies\ManualGradingStrategy;
use App\Domain\Progress\Strategies\LessonBasedProgressCalculator;
use App\Domain\Progress\Strategies\WeightedProgressCalculator;
use App\Domain\Progress\Strategies\AssessmentInclusiveProgressCalculator;

// Notification Channels
use App\Domain\Shared\Notifications\Channels\EmailChannel;
use App\Domain\Shared\Notifications\Channels\DatabaseChannel;
use App\Domain\Shared\Services\NotificationService;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * Simple bindings.
     */
    public array $bindings = [
        EnrollmentServiceContract::class => EnrollmentService::class,
        ProgressTrackingServiceContract::class => ProgressTrackingService::class,
        GradingServiceContract::class => GradingService::class,
        AssessmentAttemptServiceContract::class => AssessmentAttemptService::class,
        CoursePublishingServiceContract::class => CoursePublishingService::class,
    ];

    public function register(): void
    {
        $this->registerGradingStrategies();
        $this->registerProgressCalculators();
        $this->registerNotificationChannels();
    }

    public function boot(): void
    {
        // Nothing needed here
    }

    /**
     * Register grading strategies and resolver.
     */
    protected function registerGradingStrategies(): void
    {
        // Tag all grading strategies
        $this->app->tag([
            MultipleChoiceGradingStrategy::class,
            TrueFalseGradingStrategy::class,
            ShortAnswerGradingStrategy::class,
            ManualGradingStrategy::class,
        ], 'grading.strategies');

        // Register the strategy resolver
        $this->app->singleton(GradingStrategyResolverContract::class, function ($app) {
            return new GradingStrategyResolver(
                $app->tagged('grading.strategies')
            );
        });
    }

    /**
     * Register progress calculator strategies.
     */
    protected function registerProgressCalculators(): void
    {
        // Tag all calculators
        $this->app->tag([
            LessonBasedProgressCalculator::class,
            WeightedProgressCalculator::class,
            AssessmentInclusiveProgressCalculator::class,
        ], 'progress.calculators');

        // Default calculator binding
        $this->app->bind(ProgressCalculatorContract::class, function ($app) {
            // Could be configurable per course or globally
            $calculatorType = config('lms.progress_calculator', 'lesson_based');

            return match ($calculatorType) {
                'weighted' => $app->make(WeightedProgressCalculator::class),
                'assessment_inclusive' => $app->make(AssessmentInclusiveProgressCalculator::class),
                default => $app->make(LessonBasedProgressCalculator::class),
            };
        });
    }

    /**
     * Register notification channels.
     */
    protected function registerNotificationChannels(): void
    {
        // Tag all notification channels
        $this->app->tag([
            EmailChannel::class,
            DatabaseChannel::class,
        ], 'notification.channels');

        // Register the notification service
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService(
                $app->tagged('notification.channels')
            );
        });
    }
}
```

### Configuration File

```php
<?php
// config/lms.php

return [

    /*
    |--------------------------------------------------------------------------
    | Progress Calculator
    |--------------------------------------------------------------------------
    |
    | The default progress calculator strategy to use for enrollment progress.
    |
    | Options: 'lesson_based', 'weighted', 'assessment_inclusive'
    |
    */

    'progress_calculator' => env('LMS_PROGRESS_CALCULATOR', 'lesson_based'),

    /*
    |--------------------------------------------------------------------------
    | Grading Settings
    |--------------------------------------------------------------------------
    */

    'grading' => [
        // Partial credit settings for short answer fuzzy matching
        'short_answer_similarity_threshold' => 0.8,

        // Whether to enable partial credit for multiple choice
        'multiple_choice_partial_credit' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Default notification channels for different event types.
    |
    */

    'notifications' => [
        'enrollment_created' => ['email', 'database'],
        'enrollment_completed' => ['email', 'database'],
        'assessment_graded' => ['email', 'database'],
        'default' => ['database'],
    ],

];
```

---

## 5.6 Contextual Binding

### Course-Specific Calculator

```php
<?php
// app/Domain/Progress/Services/ProgressCalculatorFactory.php

namespace App\Domain\Progress\Services;

use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Domain\Progress\Strategies\LessonBasedProgressCalculator;
use App\Domain\Progress\Strategies\WeightedProgressCalculator;
use App\Domain\Progress\Strategies\AssessmentInclusiveProgressCalculator;
use App\Models\Course;
use Illuminate\Contracts\Container\Container;

class ProgressCalculatorFactory
{
    public function __construct(
        protected Container $container
    ) {}

    /**
     * Get the appropriate calculator for a course.
     */
    public function forCourse(Course $course): ProgressCalculatorContract
    {
        // Course can specify its calculator type
        $calculatorType = $course->progress_calculator_type
            ?? config('lms.progress_calculator', 'lesson_based');

        return $this->resolve($calculatorType);
    }

    /**
     * Resolve calculator by type name.
     */
    public function resolve(string $type): ProgressCalculatorContract
    {
        return match ($type) {
            'weighted' => $this->container->make(WeightedProgressCalculator::class),
            'assessment_inclusive' => $this->container->make(AssessmentInclusiveProgressCalculator::class),
            default => $this->container->make(LessonBasedProgressCalculator::class),
        };
    }
}
```

---

## 5.7 Testing Strategies

### Strategy Tests

```php
<?php
// tests/Unit/Domain/Assessment/Strategies/MultipleChoiceGradingStrategyTest.php

use App\Domain\Assessment\Strategies\MultipleChoiceGradingStrategy;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('MultipleChoiceGradingStrategy', function () {
    beforeEach(function () {
        $this->strategy = new MultipleChoiceGradingStrategy();

        $this->question = Question::factory()->create([
            'type' => 'single_choice',
            'points' => 10,
        ]);

        // Create options
        $this->correctOption = QuestionOption::factory()->create([
            'question_id' => $this->question->id,
            'content' => 'Correct Answer',
            'is_correct' => true,
        ]);

        $this->wrongOption = QuestionOption::factory()->create([
            'question_id' => $this->question->id,
            'content' => 'Wrong Answer',
            'is_correct' => false,
        ]);
    });

    it('supports multiple choice questions', function () {
        expect($this->strategy->supports($this->question))->toBeTrue();
    });

    it('grades correct answer', function () {
        $result = $this->strategy->grade($this->question, $this->correctOption->id);

        expect($result->isCorrect)->toBeTrue();
        expect($result->score)->toBe(10.0);
    });

    it('grades incorrect answer', function () {
        $result = $this->strategy->grade($this->question, $this->wrongOption->id);

        expect($result->isCorrect)->toBeFalse();
        expect($result->score)->toBe(0.0);
    });

    it('handles multiple choice with partial credit', function () {
        $question = Question::factory()->create([
            'type' => 'multiple_choice',
            'points' => 10,
        ]);

        $correct1 = QuestionOption::factory()->create([
            'question_id' => $question->id,
            'is_correct' => true,
        ]);

        $correct2 = QuestionOption::factory()->create([
            'question_id' => $question->id,
            'is_correct' => true,
        ]);

        $wrong = QuestionOption::factory()->create([
            'question_id' => $question->id,
            'is_correct' => false,
        ]);

        // Selecting one correct answer
        $result = $this->strategy->grade($question, [$correct1->id]);

        expect($result->score)->toBeGreaterThan(0);
        expect($result->score)->toBeLessThan(10);
    });
});
```

```php
<?php
// tests/Unit/Domain/Assessment/Strategies/TrueFalseGradingStrategyTest.php

use App\Domain\Assessment\Strategies\TrueFalseGradingStrategy;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('TrueFalseGradingStrategy', function () {
    beforeEach(function () {
        $this->strategy = new TrueFalseGradingStrategy();

        $this->question = Question::factory()->create([
            'type' => 'true_false',
            'points' => 5,
        ]);

        // Correct answer is "true"
        QuestionOption::factory()->create([
            'question_id' => $this->question->id,
            'content' => 'true',
            'is_correct' => true,
        ]);
    });

    it('supports true/false questions', function () {
        expect($this->strategy->supports($this->question))->toBeTrue();
    });

    it('grades "true" as correct', function () {
        $result = $this->strategy->grade($this->question, 'true');
        expect($result->isCorrect)->toBeTrue();
    });

    it('grades "benar" as correct (Indonesian)', function () {
        $result = $this->strategy->grade($this->question, 'benar');
        expect($result->isCorrect)->toBeTrue();
    });

    it('grades "false" as incorrect', function () {
        $result = $this->strategy->grade($this->question, 'false');
        expect($result->isCorrect)->toBeFalse();
    });

    it('handles boolean values', function () {
        $result = $this->strategy->grade($this->question, true);
        expect($result->isCorrect)->toBeTrue();
    });
});
```

### Strategy Resolver Tests

```php
<?php
// tests/Unit/Domain/Assessment/Services/GradingStrategyResolverTest.php

use App\Domain\Assessment\Services\GradingStrategyResolver;
use App\Domain\Assessment\Strategies\MultipleChoiceGradingStrategy;
use App\Domain\Assessment\Strategies\TrueFalseGradingStrategy;
use App\Domain\Assessment\Strategies\ManualGradingStrategy;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GradingStrategyResolver', function () {
    beforeEach(function () {
        $this->resolver = new GradingStrategyResolver([
            new MultipleChoiceGradingStrategy(),
            new TrueFalseGradingStrategy(),
            new ManualGradingStrategy(),
        ]);
    });

    it('resolves multiple choice strategy', function () {
        $question = Question::factory()->create(['type' => 'multiple_choice']);

        $strategy = $this->resolver->resolve($question);

        expect($strategy)->toBeInstanceOf(MultipleChoiceGradingStrategy::class);
    });

    it('resolves true/false strategy', function () {
        $question = Question::factory()->create(['type' => 'true_false']);

        $strategy = $this->resolver->resolve($question);

        expect($strategy)->toBeInstanceOf(TrueFalseGradingStrategy::class);
    });

    it('resolves manual grading for essays', function () {
        $question = Question::factory()->create(['type' => 'essay']);

        $strategy = $this->resolver->resolve($question);

        expect($strategy)->toBeInstanceOf(ManualGradingStrategy::class);
    });

    it('returns null for unsupported types', function () {
        $question = Question::factory()->create(['type' => 'unknown_type']);

        $strategy = $this->resolver->resolve($question);

        expect($strategy)->toBeNull();
    });

    it('lists all supported types', function () {
        $types = $this->resolver->getSupportedTypes();

        expect($types)->toContain('multiple_choice');
        expect($types)->toContain('true_false');
        expect($types)->toContain('essay');
    });
});
```

---

## 5.8 Implementation Checklist

### Week 10: Grading Strategies

- [ ] Create grading strategy contract
- [ ] Implement MultipleChoiceGradingStrategy
- [ ] Implement TrueFalseGradingStrategy
- [ ] Implement ShortAnswerGradingStrategy
- [ ] Implement ManualGradingStrategy
- [ ] Create GradingStrategyResolver
- [ ] Write strategy tests
- [ ] Update GradingService to use resolver

### Week 11: Progress & Notification Strategies

- [ ] Create progress calculator contract
- [ ] Implement LessonBasedProgressCalculator
- [ ] Implement WeightedProgressCalculator
- [ ] Implement AssessmentInclusiveProgressCalculator
- [ ] Create ProgressCalculatorFactory
- [ ] Implement notification channel contract
- [ ] Create EmailChannel and DatabaseChannel
- [ ] Create NotificationService
- [ ] Update DomainServiceProvider
- [ ] Create config/lms.php
- [ ] Write comprehensive tests

---

## Next Phase

Once Phase 5 is complete, proceed to [Phase 6: Observability & Debugging](./06-OBSERVABILITY.md).

Strategies are now in place, making the system flexible. Phase 6 will add observability to make debugging and monitoring easier.
