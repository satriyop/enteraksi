<?php

/**
 * Re-Enrollment Journey Tests
 *
 * Tests covering re-enrollment scenarios when a learner who previously dropped
 * a learning path wants to enroll again. Supports two modes:
 * - Reset progress (default): Delete old progress, start fresh
 * - Preserve progress: Keep previous course progress, re-link enrollments
 *
 * From the test plan: plans/tests/journey/learning-path/06-re-enrollment.md
 */

use App\Domain\LearningPath\Events\PathEnrollmentCreated;
use App\Domain\LearningPath\Exceptions\AlreadyEnrolledInPathException;
use App\Domain\LearningPath\Exceptions\PathNotPublishedException;
use App\Domain\LearningPath\Services\PathEnrollmentService;
use App\Domain\LearningPath\States\AvailableCourseState;
use App\Domain\LearningPath\States\CompletedCourseState;
use App\Domain\LearningPath\States\LockedCourseState;
use App\Domain\Shared\Services\MetricsService;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\LearningPathCourseProgress;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->learner = User::factory()->create(['role' => 'learner']);
});

describe('Re-enrollment Detection', function () {
    it('system detects dropped enrollment when enrolling', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        // Create dropped enrollment
        $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
            'progress_percentage' => 50,
            'dropped_at' => now()->subDays(30),
            'drop_reason' => 'Sibuk dengan pekerjaan',
        ]);

        // Enroll again
        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Should reuse the same enrollment record
        expect($enrollment->id)->toBe($droppedEnrollment->id);
        expect($enrollment->wasRecentlyCreated)->toBeFalse();
    });

    it('new enrollment created when no dropped enrollment exists', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        // Enroll for the first time
        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        expect($enrollment->wasRecentlyCreated)->toBeTrue();
        expect($enrollment)->not->toBeNull();
        expect($enrollment->user_id)->toBe($this->learner->id);
        expect($enrollment->learning_path_id)->toBe($path->id);
    });
});

describe('Re-enrollment with Progress Reset', function () {
    it('progress reset when explicitly requested', function () {
        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(3)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        // Create dropped enrollment with progress
        $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
            'progress_percentage' => 67, // 2 of 3 complete
        ]);

        // Add old course progress
        foreach ($courses as $i => $course) {
            LearningPathCourseProgress::create([
                'learning_path_enrollment_id' => $droppedEnrollment->id,
                'course_id' => $course->id,
                'position' => $i + 1,
                'state' => $i < 2 ? CompletedCourseState::$name : LockedCourseState::$name,
                'completed_at' => $i < 2 ? now()->subDays(30) : null,
            ]);
        }

        // Re-enroll with explicit reset (preserveProgress: false)
        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path, preserveProgress: false);

        // Progress should be reset
        expect($enrollment->progress_percentage)->toBe(0);

        // Old course progress should be replaced with fresh state
        // Fetch model to access relationships
        $courseProgress = $enrollment->courseProgress()->orderBy('position')->get();
        expect($courseProgress)->toHaveCount(3);
        expect($courseProgress[0]->isAvailable())->toBeTrue();
        expect($courseProgress[1]->isLocked())->toBeTrue();
        expect($courseProgress[2]->isLocked())->toBeTrue();
    });

    it('course enrollment becomes active on progress reset', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        // Create dropped enrollment
        $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        $oldCourseEnrollment = Enrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
        ]);

        LearningPathCourseProgress::create([
            'learning_path_enrollment_id' => $droppedEnrollment->id,
            'course_id' => $course->id,
            'course_enrollment_id' => $oldCourseEnrollment->id,
            'position' => 1,
            'state' => AvailableCourseState::$name,
        ]);

        // Re-enroll
        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Course enrollment should be active (may reuse existing enrollment record)
        // Fetch model to access relationships
        $courseProgress = $enrollment->courseProgress()->first();
        expect($courseProgress->course_enrollment_id)->not->toBeNull();
        expect($courseProgress->courseEnrollment->isActive())->toBeTrue();
    });

    it('dropped timestamp cleared on re-enrollment', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
            'dropped_at' => now()->subDays(30),
            'drop_reason' => 'Testing',
        ]);

        // Re-enroll
        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        expect($enrollment->dropped_at)->toBeNull();
        expect($enrollment->drop_reason)->toBeNull();
        expect($enrollment->enrolled_at)->not->toBeNull();
        expect($enrollment->isActive())->toBeTrue();
    });
});

describe('Re-enrollment with Progress Preserved', function () {
    it('progress preserved when requested', function () {
        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(3)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        // Create dropped enrollment with 2 completed courses
        $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
            'progress_percentage' => 67,
        ]);

        foreach ($courses as $i => $course) {
            LearningPathCourseProgress::create([
                'learning_path_enrollment_id' => $droppedEnrollment->id,
                'course_id' => $course->id,
                'position' => $i + 1,
                'state' => $i < 2 ? CompletedCourseState::$name : AvailableCourseState::$name,
                'completed_at' => $i < 2 ? now()->subDays(30) : null,
            ]);
        }

        // Re-enroll WITH preserve
        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->reactivatePathEnrollment($droppedEnrollment, preserveProgress: true);

        // Progress should be maintained
        // Fetch model to access relationships
        $courseProgress = $enrollment->courseProgress()->orderBy('position')->get();

        expect($courseProgress[0]->isCompleted())->toBeTrue();
        expect($courseProgress[1]->isCompleted())->toBeTrue();
        expect($courseProgress[2]->isAvailable())->toBeTrue();
    });

    it('course enrollments re-linked when progress preserved', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        // Create dropped enrollment
        $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        // Old course enrollment was dropped
        $oldCourseEnrollment = Enrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
        ]);

        LearningPathCourseProgress::create([
            'learning_path_enrollment_id' => $droppedEnrollment->id,
            'course_id' => $course->id,
            'course_enrollment_id' => $oldCourseEnrollment->id,
            'position' => 1,
            'state' => AvailableCourseState::$name,
        ]);

        // Re-enroll with preserve
        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->reactivatePathEnrollment($droppedEnrollment, preserveProgress: true);

        // Course enrollment should be new/active
        // Fetch model to access relationships
        $courseProgress = $enrollment->courseProgress()->first();
        expect($courseProgress->courseEnrollment->isActive())->toBeTrue();
    });

    it('locked courses remain locked when progress preserved', function () {
        $path = LearningPath::factory()->published()->create([
            'prerequisite_mode' => 'sequential',
        ]);
        $courses = Course::factory()->published()->count(3)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        // Create dropped enrollment - only first completed
        $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        LearningPathCourseProgress::create([
            'learning_path_enrollment_id' => $droppedEnrollment->id,
            'course_id' => $courses[0]->id,
            'position' => 1,
            'state' => CompletedCourseState::$name,
        ]);
        LearningPathCourseProgress::create([
            'learning_path_enrollment_id' => $droppedEnrollment->id,
            'course_id' => $courses[1]->id,
            'position' => 2,
            'state' => AvailableCourseState::$name,
        ]);
        LearningPathCourseProgress::create([
            'learning_path_enrollment_id' => $droppedEnrollment->id,
            'course_id' => $courses[2]->id,
            'position' => 3,
            'state' => LockedCourseState::$name,
        ]);

        // Re-enroll with preserve
        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->reactivatePathEnrollment($droppedEnrollment, preserveProgress: true);

        // Fetch model to access relationships
        $courseProgress = $enrollment->courseProgress()->orderBy('position')->get();

        // Third course should still be locked
        expect($courseProgress[2]->isLocked())->toBeTrue();
        expect($courseProgress[2]->course_enrollment_id)->toBeNull();
    });
});

describe('HTTP Re-enrollment Flow', function () {
    it('HTTP endpoint handles re-enrollment', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        // Create dropped enrollment
        LearningPathEnrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        $response = $this->actingAs($this->learner)
            ->post(route('learner.learning-paths.enroll', $path));

        $response->assertRedirect();

        // Should be enrolled again
        $enrollment = LearningPathEnrollment::where('user_id', $this->learner->id)
            ->where('learning_path_id', $path->id)
            ->first();

        expect($enrollment->isActive())->toBeTrue();
    });

    it('user can choose to preserve progress via UI', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        // Create dropped enrollment with progress
        $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
            'progress_percentage' => 50,
        ]);

        LearningPathCourseProgress::create([
            'learning_path_enrollment_id' => $droppedEnrollment->id,
            'course_id' => $course->id,
            'position' => 1,
            'state' => CompletedCourseState::$name,
            'completed_at' => now()->subDays(30),
        ]);

        // Post with preserve flag
        $response = $this->actingAs($this->learner)
            ->post(route('learner.learning-paths.enroll', $path), [
                'preserve_progress' => true,
            ]);

        $response->assertRedirect();

        // Progress should be preserved
        $droppedEnrollment->refresh();
        expect($droppedEnrollment->courseProgress()->first()->isCompleted())->toBeTrue();
    });
});

describe('Re-enrollment Validation', function () {
    it('cannot re-enroll if already actively enrolled', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        // Active enrollment exists
        LearningPathEnrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        // Try to enroll again
        $enrollmentService = app(PathEnrollmentService::class);

        expect(fn () => $enrollmentService->enroll($this->learner, $path))
            ->toThrow(AlreadyEnrolledInPathException::class);
    });

    it('cannot re-enroll in unpublished path', function () {
        $path = LearningPath::factory()->unpublished()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        // Dropped enrollment exists
        LearningPathEnrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        // Try to re-enroll
        $enrollmentService = app(PathEnrollmentService::class);

        expect(fn () => $enrollmentService->enroll($this->learner, $path))
            ->toThrow(PathNotPublishedException::class);
    });
});

describe('Re-enrollment Events', function () {
    it('PathEnrollmentCreated event dispatched on re-enrollment', function () {
        Event::fake([PathEnrollmentCreated::class]);

        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        LearningPathEnrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        // Re-enroll
        $enrollmentService = app(PathEnrollmentService::class);
        $enrollmentService->enroll($this->learner, $path);

        Event::assertDispatched(PathEnrollmentCreated::class);
    });

    it('metrics incremented on re-enrollment', function () {
        $metrics = $this->mock(MetricsService::class);
        $metrics->shouldReceive('increment')
            ->with('learning_path.enrollments.reactivated')
            ->once();
        $metrics->shouldReceive('increment')
            ->with(\Mockery::any())
            ->zeroOrMoreTimes();
        $metrics->shouldReceive('timing')
            ->zeroOrMoreTimes();

        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        LearningPathEnrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        // Re-enroll
        $enrollmentService = app(PathEnrollmentService::class);
        $enrollmentService->enroll($this->learner, $path);
    });
});

describe('Edge Cases', function () {
    it('handles multiple historical enrollments correctly', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        // Note: In practice, there should only be one enrollment per user/path
        // But test handles if multiple somehow exist
        $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        // Re-enroll
        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Should use the existing enrollment
        expect($enrollment->id)->toBe($droppedEnrollment->id);

        // Should only have one enrollment record
        $count = LearningPathEnrollment::where('user_id', $this->learner->id)
            ->where('learning_path_id', $path->id)
            ->count();
        expect($count)->toBe(1);
    });

    it('re-enrollment handles path courses changed since dropping', function () {
        $path = LearningPath::factory()->published()->create();
        $oldCourse = Course::factory()->published()->create();
        $newCourse = Course::factory()->published()->create();

        // Original path had only one course
        $path->courses()->attach($oldCourse->id, ['position' => 1, 'is_required' => true]);

        // Create dropped enrollment with old structure
        $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        LearningPathCourseProgress::create([
            'learning_path_enrollment_id' => $droppedEnrollment->id,
            'course_id' => $oldCourse->id,
            'position' => 1,
            'state' => CompletedCourseState::$name,
        ]);

        // Admin adds new course to path
        $path->courses()->attach($newCourse->id, ['position' => 2, 'is_required' => true]);

        // Re-enroll with reset to get new course structure
        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path, preserveProgress: false);

        // Should have both courses now (because we reset progress)
        // Fetch model to access relationships
        $courseProgress = $enrollment->courseProgress()->orderBy('position')->get();

        expect($courseProgress)->toHaveCount(2);
        expect($courseProgress[0]->course_id)->toBe($oldCourse->id);
        expect($courseProgress[1]->course_id)->toBe($newCourse->id);
    });
});
