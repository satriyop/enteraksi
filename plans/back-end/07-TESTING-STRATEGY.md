# Phase 7: Testing Strategy

**Duration**: Ongoing (parallel with all phases)
**Priority**: Critical - Ensures safe refactoring

---

## Objectives

1. Establish testing pyramid for the refactored architecture
2. Create unit tests for all services and strategies
3. Build integration tests for state machines
4. Write feature tests for complete user flows
5. Implement contract tests for strategies

---

## 7.1 Testing Pyramid

```
                    ╱╲
                   ╱  ╲
                  ╱ E2E╲           Few, slow, comprehensive
                 ╱──────╲
                ╱ Feature ╲        Medium, integrated flows
               ╱────────────╲
              ╱ Integration  ╲     State machines, services
             ╱────────────────╲
            ╱      Unit        ╲   Fast, isolated, many
           ╱────────────────────╲
```

### Test Distribution Goals

| Type | Percentage | Focus |
|------|------------|-------|
| Unit | 60% | Services, strategies, value objects, DTOs |
| Integration | 25% | State machines, service interactions, database |
| Feature | 12% | Complete user flows, API endpoints |
| E2E | 3% | Critical paths (enrollment → completion) |

---

## 7.2 Directory Structure

```
tests/
├── Unit/
│   ├── Domain/
│   │   ├── Assessment/
│   │   │   ├── Services/
│   │   │   │   ├── GradingServiceTest.php
│   │   │   │   └── AssessmentAttemptServiceTest.php
│   │   │   ├── Strategies/
│   │   │   │   ├── MultipleChoiceGradingStrategyTest.php
│   │   │   │   ├── TrueFalseGradingStrategyTest.php
│   │   │   │   └── ShortAnswerGradingStrategyTest.php
│   │   │   ├── States/
│   │   │   │   └── AttemptStateTest.php
│   │   │   └── ValueObjects/
│   │   │       └── ScoreTest.php
│   │   ├── Course/
│   │   │   ├── Services/
│   │   │   │   └── CoursePublishingServiceTest.php
│   │   │   └── States/
│   │   │       └── CourseStateTest.php
│   │   ├── Enrollment/
│   │   │   ├── Services/
│   │   │   │   └── EnrollmentServiceTest.php
│   │   │   └── States/
│   │   │       └── EnrollmentStateTest.php
│   │   ├── Progress/
│   │   │   ├── Services/
│   │   │   │   └── ProgressTrackingServiceTest.php
│   │   │   └── Strategies/
│   │   │       ├── LessonBasedProgressCalculatorTest.php
│   │   │       └── WeightedProgressCalculatorTest.php
│   │   └── Shared/
│   │       └── ValueObjects/
│   │           ├── PercentageTest.php
│   │           └── DurationTest.php
│   └── Policies/
│       ├── CoursePolicyTest.php
│       └── AssessmentPolicyTest.php
├── Integration/
│   ├── StateMachines/
│   │   ├── CourseStateMachineTest.php
│   │   ├── EnrollmentStateMachineTest.php
│   │   └── AttemptStateMachineTest.php
│   ├── Events/
│   │   ├── CourseEventsTest.php
│   │   ├── EnrollmentEventsTest.php
│   │   └── AssessmentEventsTest.php
│   └── Services/
│       └── ServiceInteractionTest.php
├── Feature/
│   ├── EnrollmentFlowTest.php
│   ├── CoursePublishingFlowTest.php
│   ├── AssessmentFlowTest.php
│   ├── ProgressTrackingFlowTest.php
│   └── Api/
│       └── HealthCheckTest.php
└── Pest.php
```

---

## 7.3 Unit Tests

### Service Test Example

```php
<?php
// tests/Unit/Domain/Enrollment/Services/EnrollmentServiceTest.php

use App\Domain\Enrollment\Services\EnrollmentService;
use App\Domain\Enrollment\DTOs\CreateEnrollmentDTO;
use App\Domain\Enrollment\Exceptions\AlreadyEnrolledException;
use App\Domain\Enrollment\Exceptions\CourseNotPublishedException;
use App\Domain\Shared\Services\DomainLogger;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mock the logger to avoid noise
    $this->logger = Mockery::mock(DomainLogger::class);
    $this->logger->shouldReceive('actionStarted')->andReturnNull();
    $this->logger->shouldReceive('actionCompleted')->andReturnNull();
    $this->logger->shouldReceive('actionFailed')->andReturnNull();

    $this->service = new EnrollmentService($this->logger);
});

describe('EnrollmentService', function () {

    describe('enroll', function () {

        it('creates enrollment for valid user and published course', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();

            $dto = new CreateEnrollmentDTO(
                userId: $user->id,
                courseId: $course->id,
            );

            $result = $this->service->enroll($dto);

            expect($result->isNewEnrollment)->toBeTrue();
            expect($result->enrollment)->toBeInstanceOf(Enrollment::class);
            expect($result->enrollment->user_id)->toBe($user->id);
            expect($result->enrollment->course_id)->toBe($course->id);
            expect($result->enrollment->status)->toBe('active');
        });

        it('throws AlreadyEnrolledException when user is already enrolled', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();

            // First enrollment
            Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            $dto = new CreateEnrollmentDTO(
                userId: $user->id,
                courseId: $course->id,
            );

            $this->service->enroll($dto);
        })->throws(AlreadyEnrolledException::class);

        it('throws CourseNotPublishedException for draft course', function () {
            $user = User::factory()->create();
            $course = Course::factory()->draft()->create();

            $dto = new CreateEnrollmentDTO(
                userId: $user->id,
                courseId: $course->id,
            );

            $this->service->enroll($dto);
        })->throws(CourseNotPublishedException::class);

        it('sets invited_by when provided', function () {
            $user = User::factory()->create();
            $inviter = User::factory()->create();
            $course = Course::factory()->published()->create();

            $dto = new CreateEnrollmentDTO(
                userId: $user->id,
                courseId: $course->id,
                invitedBy: $inviter->id,
            );

            $result = $this->service->enroll($dto);

            expect($result->enrollment->invited_by)->toBe($inviter->id);
        });
    });

    describe('canEnroll', function () {

        it('returns true for valid enrollment scenario', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();

            $canEnroll = $this->service->canEnroll($user, $course);

            expect($canEnroll)->toBeTrue();
        });

        it('returns false when already enrolled', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            $canEnroll = $this->service->canEnroll($user, $course);

            expect($canEnroll)->toBeFalse();
        });

        it('returns false for draft course', function () {
            $user = User::factory()->create();
            $course = Course::factory()->draft()->create();

            $canEnroll = $this->service->canEnroll($user, $course);

            expect($canEnroll)->toBeFalse();
        });
    });
});
```

### Strategy Test Example

```php
<?php
// tests/Unit/Domain/Assessment/Strategies/ShortAnswerGradingStrategyTest.php

use App\Domain\Assessment\Strategies\ShortAnswerGradingStrategy;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->strategy = new ShortAnswerGradingStrategy();
});

describe('ShortAnswerGradingStrategy', function () {

    it('supports short_answer question type', function () {
        $question = Question::factory()->create(['type' => 'short_answer']);

        expect($this->strategy->supports($question))->toBeTrue();
    });

    it('grades exact match as correct', function () {
        $question = Question::factory()->create([
            'type' => 'short_answer',
            'points' => 10,
            'correct_answer' => 'Jakarta',
        ]);

        $result = $this->strategy->grade($question, 'Jakarta');

        expect($result->isCorrect)->toBeTrue();
        expect($result->score)->toBe(10.0);
    });

    it('is case insensitive by default', function () {
        $question = Question::factory()->create([
            'type' => 'short_answer',
            'points' => 10,
            'correct_answer' => 'Jakarta',
            'case_sensitive' => false,
        ]);

        $result = $this->strategy->grade($question, 'jakarta');

        expect($result->isCorrect)->toBeTrue();
    });

    it('accepts multiple correct answers', function () {
        $question = Question::factory()->create([
            'type' => 'short_answer',
            'points' => 10,
            'correct_answer' => 'Jakarta, DKI Jakarta, Ibu Kota Jakarta',
        ]);

        $result1 = $this->strategy->grade($question, 'Jakarta');
        $result2 = $this->strategy->grade($question, 'DKI Jakarta');

        expect($result1->isCorrect)->toBeTrue();
        expect($result2->isCorrect)->toBeTrue();
    });

    it('gives partial credit for close answers', function () {
        $question = Question::factory()->create([
            'type' => 'short_answer',
            'points' => 10,
            'correct_answer' => 'Independence',
        ]);

        // Typo: "Independance"
        $result = $this->strategy->grade($question, 'Independance');

        expect($result->score)->toBeGreaterThan(0);
        expect($result->score)->toBeLessThan(10);
    });

    it('requires manual grading when no correct answer defined', function () {
        $question = Question::factory()->create([
            'type' => 'short_answer',
            'points' => 10,
            'correct_answer' => null,
        ]);

        $result = $this->strategy->grade($question, 'Any answer');

        expect($result->metadata['requires_manual_grading'])->toBeTrue();
    });
});
```

### Value Object Test Example

```php
<?php
// tests/Unit/Domain/Shared/ValueObjects/PercentageTest.php

use App\Domain\Shared\ValueObjects\Percentage;

describe('Percentage', function () {

    describe('construction', function () {

        it('creates valid percentage', function () {
            $percentage = new Percentage(75.5);

            expect($percentage->value)->toBe(75.5);
        });

        it('accepts zero', function () {
            $percentage = new Percentage(0);

            expect($percentage->value)->toBe(0.0);
        });

        it('accepts 100', function () {
            $percentage = new Percentage(100);

            expect($percentage->value)->toBe(100.0);
        });

        it('rejects negative values', function () {
            new Percentage(-1);
        })->throws(InvalidArgumentException::class);

        it('rejects values over 100', function () {
            new Percentage(100.1);
        })->throws(InvalidArgumentException::class);
    });

    describe('fromFraction', function () {

        it('calculates percentage from fraction', function () {
            $percentage = Percentage::fromFraction(3, 4);

            expect($percentage->value)->toBe(75.0);
        });

        it('handles zero denominator', function () {
            $percentage = Percentage::fromFraction(5, 0);

            expect($percentage->value)->toBe(0.0);
        });

        it('rounds to two decimal places', function () {
            $percentage = Percentage::fromFraction(1, 3);

            expect($percentage->value)->toBe(33.33);
        });
    });

    describe('behavior', function () {

        it('detects completion at 100', function () {
            $complete = new Percentage(100);
            $incomplete = new Percentage(99.9);

            expect($complete->isComplete())->toBeTrue();
            expect($incomplete->isComplete())->toBeFalse();
        });

        it('converts to fraction', function () {
            $percentage = new Percentage(75);

            expect($percentage->toFraction())->toBe(0.75);
        });

        it('formats correctly', function () {
            $percentage = new Percentage(75.55);

            expect($percentage->format())->toBe('75.6%');
            expect($percentage->format(2))->toBe('75.55%');
        });

        it('serializes to JSON', function () {
            $percentage = new Percentage(75);

            expect(json_encode($percentage))->toBe('75');
        });

        it('converts to string', function () {
            $percentage = new Percentage(75);

            expect((string) $percentage)->toBe('75.0%');
        });
    });
});
```

---

## 7.4 Integration Tests

### State Machine Integration Test

```php
<?php
// tests/Integration/StateMachines/CourseStateMachineTest.php

use App\Domain\Course\Events\CoursePublished;
use App\Domain\Course\Events\CourseUnpublished;
use App\Domain\Course\Events\CourseArchived;
use App\Domain\Course\States\DraftState;
use App\Domain\Course\States\PublishedState;
use App\Domain\Course\States\ArchivedState;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

describe('Course State Machine Integration', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->category = Category::factory()->create();

        // Create a publishable course
        $this->course = Course::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'status' => 'draft',
        ]);

        $section = CourseSection::factory()->create([
            'course_id' => $this->course->id,
        ]);

        Lesson::factory()->create([
            'course_section_id' => $section->id,
        ]);
    });

    describe('publish transition', function () {

        it('transitions from draft to published', function () {
            Event::fake([CoursePublished::class]);

            $this->course->publish($this->user->id);

            expect($this->course->fresh()->status)->toBeInstanceOf(PublishedState::class);
            expect($this->course->fresh()->published_at)->not->toBeNull();
            expect($this->course->fresh()->published_by)->toBe($this->user->id);

            Event::assertDispatched(CoursePublished::class);
        });

        it('cannot publish without content', function () {
            // Remove all content
            $this->course->sections()->delete();

            $this->course->publish($this->user->id);
        })->throws(\App\Domain\Course\Exceptions\CannotPublishException::class);

        it('cannot publish without category', function () {
            $this->course->update(['category_id' => null]);

            $this->course->publish($this->user->id);
        })->throws(\App\Domain\Course\Exceptions\CannotPublishException::class);
    });

    describe('unpublish transition', function () {

        it('transitions from published to draft', function () {
            Event::fake([CourseUnpublished::class]);

            $this->course->publish($this->user->id);
            $this->course->unpublish();

            expect($this->course->fresh()->status)->toBeInstanceOf(DraftState::class);
            expect($this->course->fresh()->published_at)->toBeNull();

            Event::assertDispatched(CourseUnpublished::class);
        });

        it('cannot unpublish a draft course', function () {
            $this->course->unpublish();
        })->throws(\Spatie\ModelStates\Exceptions\CouldNotPerformTransition::class);
    });

    describe('archive transition', function () {

        it('transitions from published to archived', function () {
            Event::fake([CourseArchived::class]);

            $this->course->publish($this->user->id);
            $this->course->archive();

            expect($this->course->fresh()->status)->toBeInstanceOf(ArchivedState::class);

            Event::assertDispatched(CourseArchived::class);
        });
    });

    describe('state-based behavior', function () {

        it('allows editing in draft state', function () {
            expect($this->course->canBeEdited())->toBeTrue();
        });

        it('prevents editing in published state', function () {
            $this->course->publish($this->user->id);

            expect($this->course->canBeEdited())->toBeFalse();
        });

        it('allows enrollments in published state', function () {
            $this->course->publish($this->user->id);

            expect($this->course->canAcceptEnrollments())->toBeTrue();
        });

        it('prevents enrollments in draft state', function () {
            expect($this->course->canAcceptEnrollments())->toBeFalse();
        });
    });
});
```

### Event Integration Test

```php
<?php
// tests/Integration/Events/EnrollmentEventsTest.php

use App\Domain\Enrollment\Events\UserEnrolled;
use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\Enrollment\Listeners\SendWelcomeNotification;
use App\Domain\Enrollment\Listeners\SendCompletionCongratulations;
use App\Domain\Enrollment\Listeners\GenerateCertificate;
use App\Domain\Enrollment\Notifications\WelcomeToCourseMail;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

describe('Enrollment Events Integration', function () {

    describe('UserEnrolled event', function () {

        it('dispatches when enrollment is created', function () {
            Event::fake([UserEnrolled::class]);

            $user = User::factory()->create();
            $course = Course::factory()->published()->create();

            $enrollment = Enrollment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'status' => 'active',
                'enrolled_at' => now(),
            ]);

            UserEnrolled::dispatch($enrollment);

            Event::assertDispatched(UserEnrolled::class, function ($event) use ($enrollment) {
                return $event->enrollment->id === $enrollment->id;
            });
        });

        it('triggers welcome notification', function () {
            Notification::fake();

            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $enrollment = Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            $event = new UserEnrolled($enrollment);
            (new SendWelcomeNotification())->handle($event);

            Notification::assertSentTo($user, WelcomeToCourseMail::class);
        });

        it('contains correct event metadata', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $enrollment = Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            $event = new UserEnrolled($enrollment);
            $metadata = $event->getMetadata();

            expect($metadata['enrollment_id'])->toBe($enrollment->id);
            expect($metadata['user_id'])->toBe($user->id);
            expect($metadata['course_id'])->toBe($course->id);
            expect($metadata['course_title'])->toBe($course->title);
        });
    });

    describe('EnrollmentCompleted event', function () {

        it('triggers completion notifications', function () {
            Notification::fake();

            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $enrollment = Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $event = new EnrollmentCompleted($enrollment);
            (new SendCompletionCongratulations())->handle($event);

            Notification::assertSentTo($user, \App\Domain\Enrollment\Notifications\CourseCompletedMail::class);
        });
    });
});
```

---

## 7.5 Feature Tests

### Complete Enrollment Flow Test

```php
<?php
// tests/Feature/EnrollmentFlowTest.php

use App\Domain\Enrollment\Events\UserEnrolled;
use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\Progress\Events\LessonCompleted;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

describe('Enrollment Flow', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->course = Course::factory()->published()->create();

        // Create course content
        $section = CourseSection::factory()->create([
            'course_id' => $this->course->id,
        ]);

        $this->lessons = Lesson::factory()->count(3)->create([
            'course_section_id' => $section->id,
        ]);
    });

    it('completes full enrollment to completion flow', function () {
        Event::fake();

        // Step 1: User enrolls
        $response = $this->actingAs($this->user)
            ->post(route('courses.enroll', $this->course));

        $response->assertRedirect();
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);

        Event::assertDispatched(UserEnrolled::class);

        // Get the enrollment
        $enrollment = Enrollment::where('user_id', $this->user->id)
            ->where('course_id', $this->course->id)
            ->first();

        // Step 2: User completes lessons one by one
        foreach ($this->lessons as $index => $lesson) {
            $response = $this->actingAs($this->user)
                ->post(route('lessons.progress.complete', [
                    'course' => $this->course,
                    'lesson' => $lesson,
                ]));

            $response->assertSuccessful();

            // Verify progress tracking
            $this->assertDatabaseHas('lesson_progress', [
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
            ]);

            Event::assertDispatched(LessonCompleted::class);

            // Verify course progress percentage
            $expectedProgress = round((($index + 1) / 3) * 100, 1);
            expect($enrollment->fresh()->progress_percentage)->toBe($expectedProgress);
        }

        // Step 3: Verify course completion
        expect($enrollment->fresh()->status)->toBe('completed');
        expect($enrollment->fresh()->completed_at)->not->toBeNull();

        Event::assertDispatched(EnrollmentCompleted::class);
    });

    it('tracks progress percentage correctly', function () {
        $enrollment = Enrollment::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);

        // Complete first lesson (1/3 = 33.3%)
        LessonProgress::factory()->completed()->create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $this->lessons[0]->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('learner.dashboard'));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) =>
            $page->has('enrollments.data', 1)
                ->where('enrollments.data.0.progress_percentage', 33.3)
        );
    });
});
```

### Assessment Flow Test

```php
<?php
// tests/Feature/AssessmentFlowTest.php

use App\Domain\Assessment\Events\AttemptStarted;
use App\Domain\Assessment\Events\AssessmentSubmitted;
use App\Domain\Assessment\Events\AssessmentGraded;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

describe('Assessment Flow', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->course = Course::factory()->published()->create();

        // Enroll user
        $this->enrollment = Enrollment::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);

        // Create assessment with questions
        $this->assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'passing_score' => 70,
            'max_attempts' => 3,
        ]);

        // Multiple choice question
        $this->mcQuestion = Question::factory()->create([
            'assessment_id' => $this->assessment->id,
            'type' => 'single_choice',
            'points' => 10,
        ]);

        $this->correctOption = QuestionOption::factory()->create([
            'question_id' => $this->mcQuestion->id,
            'is_correct' => true,
        ]);

        $this->wrongOption = QuestionOption::factory()->create([
            'question_id' => $this->mcQuestion->id,
            'is_correct' => false,
        ]);

        // True/false question
        $this->tfQuestion = Question::factory()->create([
            'assessment_id' => $this->assessment->id,
            'type' => 'true_false',
            'points' => 10,
        ]);

        QuestionOption::factory()->create([
            'question_id' => $this->tfQuestion->id,
            'content' => 'true',
            'is_correct' => true,
        ]);
    });

    it('completes full assessment flow with passing score', function () {
        Event::fake();

        // Step 1: Start attempt
        $response = $this->actingAs($this->user)
            ->post(route('assessments.start', [
                'course' => $this->course,
                'assessment' => $this->assessment,
            ]));

        $response->assertRedirect();
        Event::assertDispatched(AttemptStarted::class);

        $attempt = AssessmentAttempt::where('user_id', $this->user->id)
            ->where('assessment_id', $this->assessment->id)
            ->first();

        expect($attempt->status)->toBe('in_progress');

        // Step 2: Submit answers (all correct)
        $response = $this->actingAs($this->user)
            ->post(route('assessments.submit', [
                'course' => $this->course,
                'assessment' => $this->assessment,
                'attempt' => $attempt,
            ]), [
                'answers' => [
                    ['question_id' => $this->mcQuestion->id, 'answer_text' => $this->correctOption->id],
                    ['question_id' => $this->tfQuestion->id, 'answer_text' => 'true'],
                ],
            ]);

        $response->assertRedirect();
        Event::assertDispatched(AssessmentSubmitted::class);
        Event::assertDispatched(AssessmentGraded::class);

        // Step 3: Verify results
        $attempt = $attempt->fresh();

        expect($attempt->status)->toBe('graded');
        expect($attempt->score)->toBe(20.0);
        expect($attempt->percentage)->toBe(100.0);
        expect($attempt->passed)->toBeTrue();
    });

    it('respects max attempts limit', function () {
        // Create 3 completed attempts (max allowed)
        AssessmentAttempt::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'assessment_id' => $this->assessment->id,
            'status' => 'graded',
        ]);

        // Try to start 4th attempt
        $response = $this->actingAs($this->user)
            ->post(route('assessments.start', [
                'course' => $this->course,
                'assessment' => $this->assessment,
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    });
});
```

---

## 7.6 Contract Tests

### Strategy Contract Test

```php
<?php
// tests/Unit/Domain/Assessment/Contracts/GradingStrategyContractTest.php

use App\Domain\Assessment\Contracts\GradingStrategyContract;
use App\Domain\Assessment\Strategies\MultipleChoiceGradingStrategy;
use App\Domain\Assessment\Strategies\TrueFalseGradingStrategy;
use App\Domain\Assessment\Strategies\ShortAnswerGradingStrategy;
use App\Domain\Assessment\Strategies\ManualGradingStrategy;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Contract test: All grading strategies must satisfy the same contract.
 */
describe('GradingStrategyContract', function () {

    $strategies = [
        'MultipleChoice' => fn() => new MultipleChoiceGradingStrategy(),
        'TrueFalse' => fn() => new TrueFalseGradingStrategy(),
        'ShortAnswer' => fn() => new ShortAnswerGradingStrategy(),
        'ManualGrading' => fn() => new ManualGradingStrategy(),
    ];

    foreach ($strategies as $name => $factory) {

        describe("{$name}Strategy", function () use ($factory) {

            it('implements GradingStrategyContract', function () use ($factory) {
                $strategy = $factory();

                expect($strategy)->toBeInstanceOf(GradingStrategyContract::class);
            });

            it('returns array of handled types', function () use ($factory) {
                $strategy = $factory();
                $types = $strategy->getHandledTypes();

                expect($types)->toBeArray();
                expect($types)->not->toBeEmpty();
            });

            it('supports returns boolean', function () use ($factory) {
                $strategy = $factory();
                $question = Question::factory()->create();

                $supports = $strategy->supports($question);

                expect($supports)->toBeBool();
            });

            it('grade returns GradingResult with required properties', function () use ($factory) {
                $strategy = $factory();
                $types = $strategy->getHandledTypes();
                $question = Question::factory()->create([
                    'type' => $types[0],
                    'points' => 10,
                ]);

                $result = $strategy->grade($question, 'test answer');

                expect($result)->toHaveProperty('isCorrect');
                expect($result)->toHaveProperty('score');
                expect($result)->toHaveProperty('maxScore');
                expect($result->isCorrect)->toBeBool();
                expect($result->score)->toBeFloat();
                expect($result->maxScore)->toBe(10.0);
            });
        });
    }
});
```

---

## 7.7 Test Helpers

### Custom Test Assertions

```php
<?php
// tests/TestCase.php (add methods)

namespace Tests;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a user enrolled in a published course.
     */
    protected function createEnrolledUser(?Course $course = null): array
    {
        $user = User::factory()->create();
        $course = $course ?? Course::factory()->published()->create();

        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        return [$user, $course, $enrollment];
    }

    /**
     * Assert that a domain event was logged.
     */
    protected function assertEventLogged(string $eventName, array $metadata = []): void
    {
        $query = \Illuminate\Support\Facades\DB::table('domain_event_log')
            ->where('event_name', $eventName);

        foreach ($metadata as $key => $value) {
            $query->whereJsonContains("metadata->{$key}", $value);
        }

        $this->assertTrue(
            $query->exists(),
            "Event '{$eventName}' was not logged with the expected metadata."
        );
    }

    /**
     * Assert that a state transition was logged.
     */
    protected function assertStateTransition(
        string $modelType,
        int|string $modelId,
        string $fromState,
        string $toState
    ): void {
        $this->assertDatabaseHas('state_transitions', [
            'transitionable_type' => $modelType,
            'transitionable_id' => $modelId,
            'from_state' => $fromState,
            'to_state' => $toState,
        ]);
    }
}
```

### Factory States for Common Scenarios

```php
<?php
// database/factories/CourseFactory.php (add states)

public function draft(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => 'draft',
        'published_at' => null,
        'published_by' => null,
    ]);
}

public function published(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => 'published',
        'published_at' => now(),
        'published_by' => User::factory(),
        'category_id' => Category::factory(),
    ]);
}

public function archived(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => 'archived',
    ]);
}

public function withContent(): static
{
    return $this->afterCreating(function (Course $course) {
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->count(3)->create(['course_section_id' => $section->id]);
    });
}
```

---

## 7.8 CI/CD Integration

### GitHub Actions Workflow

```yaml
# .github/workflows/tests.yml
name: Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  tests:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: dom, curl, mbstring, pdo, pdo_mysql
          coverage: xdebug

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Copy env
        run: cp .env.example .env.testing

      - name: Generate key
        run: php artisan key:generate --env=testing

      - name: Run migrations
        run: php artisan migrate --env=testing --force

      - name: Run unit tests
        run: php artisan test --testsuite=Unit --parallel

      - name: Run integration tests
        run: php artisan test --testsuite=Integration

      - name: Run feature tests
        run: php artisan test --testsuite=Feature

      - name: Generate coverage report
        run: php artisan test --coverage --min=80
```

---

## 7.9 Implementation Checklist

### Ongoing Throughout All Phases

- [ ] Unit Tests
  - [ ] Value objects (Percentage, Duration, Score)
  - [ ] DTOs (all data transfer objects)
  - [ ] Services (EnrollmentService, GradingService, etc.)
  - [ ] Strategies (grading, progress calculation)
  - [ ] Policies

- [ ] Integration Tests
  - [ ] Course state machine
  - [ ] Enrollment state machine
  - [ ] Assessment attempt state machine
  - [ ] Event dispatching and handling
  - [ ] Service interactions

- [ ] Feature Tests
  - [ ] Enrollment flow
  - [ ] Course publishing flow
  - [ ] Assessment flow
  - [ ] Progress tracking flow
  - [ ] Health check endpoints

- [ ] Contract Tests
  - [ ] Grading strategies
  - [ ] Progress calculators
  - [ ] Notification channels

- [ ] Test Infrastructure
  - [ ] Custom assertions
  - [ ] Factory states
  - [ ] Test helpers
  - [ ] CI/CD integration

---

## Next Phase

Once testing is established, proceed to [Phase 8: Migration & Rollout Guide](./08-MIGRATION-GUIDE.md).

Testing provides the safety net for the entire refactoring effort. Phase 8 ensures the new architecture is rolled out safely to production.
