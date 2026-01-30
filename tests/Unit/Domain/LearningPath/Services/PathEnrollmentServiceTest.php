<?php

use App\Domain\LearningPath\Events\PathCompleted;
use App\Domain\LearningPath\Events\PathDropped;
use App\Domain\LearningPath\Events\PathEnrollmentCreated;
use App\Domain\LearningPath\Exceptions\AlreadyEnrolledInPathException;
use App\Domain\LearningPath\Exceptions\PathNotPublishedException;
use App\Domain\LearningPath\Services\PathEnrollmentService;
use App\Domain\Shared\Exceptions\InvalidStateTransitionException;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->service = app(PathEnrollmentService::class);
    Event::fake();
});

describe('PathEnrollmentService', function () {
    describe('enroll', function () {
        it('enrolls user in published learning path', function () {
            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();
            $courses = Course::factory()->published()->count(3)->create();

            // Attach courses to path
            foreach ($courses as $index => $course) {
                $path->courses()->attach($course->id, [
                    'position' => $index + 1,
                    'is_required' => true,
                ]);
            }

            $enrollment = $this->service->enroll($user, $path);

            expect($enrollment->wasRecentlyCreated)->toBeTrue();
            expect($enrollment->user_id)->toBe($user->id);
            expect($enrollment->learning_path_id)->toBe($path->id);
            expect($enrollment->isActive())->toBeTrue();
            expect($enrollment->progress_percentage)->toBe(0);

            // Check course progress initialized
            expect($enrollment->courseProgress)->toHaveCount(3);

            Event::assertDispatched(PathEnrollmentCreated::class);
        });

        it('initializes first course as available', function () {
            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();
            $courses = Course::factory()->published()->count(3)->create();

            foreach ($courses as $index => $course) {
                $path->courses()->attach($course->id, [
                    'position' => $index + 1,
                    'is_required' => true,
                ]);
            }

            $enrollment = $this->service->enroll($user, $path);

            $courseProgress = $enrollment->courseProgress()->orderBy('position')->get();

            expect($courseProgress[0]->isAvailable())->toBeTrue();
            expect($courseProgress[1]->isLocked())->toBeTrue();
            expect($courseProgress[2]->isLocked())->toBeTrue();
        });

        it('creates course enrollment for first available course', function () {
            // Don't fake events for this test - we need enrollment events to fire
            Event::fake([PathEnrollmentCreated::class]);

            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();
            $courses = Course::factory()->published()->count(3)->create();

            foreach ($courses as $index => $course) {
                $path->courses()->attach($course->id, [
                    'position' => $index + 1,
                    'is_required' => true,
                ]);
            }

            $enrollment = $this->service->enroll($user, $path);

            $courseProgress = $enrollment->courseProgress()->orderBy('position')->get();

            // First course should have a course enrollment linked
            expect($courseProgress[0]->course_enrollment_id)->not->toBeNull();
            expect($courseProgress[0]->unlocked_at)->not->toBeNull();

            // Verify the course enrollment exists
            $courseEnrollment = $courseProgress[0]->courseEnrollment;
            expect($courseEnrollment)->not->toBeNull();
            expect($courseEnrollment->user_id)->toBe($user->id);
            expect($courseEnrollment->course_id)->toBe($courses[0]->id);
            expect($courseEnrollment->isActive())->toBeTrue();

            // Locked courses should NOT have course enrollment yet
            expect($courseProgress[1]->course_enrollment_id)->toBeNull();
            expect($courseProgress[2]->course_enrollment_id)->toBeNull();
        });

        it('reuses existing course enrollment when enrolling in path', function () {
            Event::fake([PathEnrollmentCreated::class]);

            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();
            $course = Course::factory()->published()->create();

            // User is already enrolled in the course
            $existingEnrollment = \App\Models\Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            $path->courses()->attach($course->id, [
                'position' => 1,
                'is_required' => true,
            ]);

            $enrollment = $this->service->enroll($user, $path);

            $courseProgress = $enrollment->courseProgress()->first();

            // Should reuse the existing enrollment
            expect($courseProgress->course_enrollment_id)->toBe($existingEnrollment->id);

            // Should not create duplicate enrollment
            $enrollmentCount = \App\Models\Enrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->count();
            expect($enrollmentCount)->toBe(1);
        });

        it('throws exception for unpublished path', function () {
            $user = User::factory()->create();
            $path = LearningPath::factory()->unpublished()->create();

            $this->service->enroll($user, $path);
        })->throws(PathNotPublishedException::class);

        it('throws exception when already enrolled', function () {
            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();

            LearningPathEnrollment::factory()->active()->create([
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
            ]);

            $this->service->enroll($user, $path);
        })->throws(AlreadyEnrolledInPathException::class);
    });

    describe('canEnroll', function () {
        it('returns true for published path with no enrollment', function () {
            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();

            expect($this->service->canEnroll($user, $path))->toBeTrue();
        });

        it('returns false when already enrolled', function () {
            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();

            LearningPathEnrollment::factory()->active()->create([
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
            ]);

            expect($this->service->canEnroll($user, $path))->toBeFalse();
        });

        it('returns false for unpublished path', function () {
            $user = User::factory()->create();
            $path = LearningPath::factory()->unpublished()->create();

            expect($this->service->canEnroll($user, $path))->toBeFalse();
        });

        it('returns true for dropped enrollment (re-enrollment allowed)', function () {
            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();

            LearningPathEnrollment::factory()->dropped()->create([
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
            ]);

            expect($this->service->canEnroll($user, $path))->toBeTrue();
        });
    });

    describe('re-enrollment', function () {
        it('reactivates dropped enrollment instead of creating new', function () {
            Event::fake([PathEnrollmentCreated::class]);

            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();
            $course = Course::factory()->published()->create();

            $path->courses()->attach($course->id, [
                'position' => 1,
                'is_required' => true,
            ]);

            // Create and drop enrollment
            $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
            ]);

            // Re-enroll
            $enrollment = $this->service->enroll($user, $path);

            // Should reactivate existing, not create new
            expect($enrollment->wasRecentlyCreated)->toBeFalse();
            expect($enrollment->id)->toBe($droppedEnrollment->id);
            expect($enrollment->isActive())->toBeTrue();
            expect($enrollment->dropped_at)->toBeNull();
            expect($enrollment->drop_reason)->toBeNull();

            // Should only have one enrollment record
            $enrollmentCount = LearningPathEnrollment::where('user_id', $user->id)
                ->where('learning_path_id', $path->id)
                ->count();
            expect($enrollmentCount)->toBe(1);

            Event::assertDispatched(PathEnrollmentCreated::class);
        });

        it('preserves progress by default on re-enrollment', function () {
            Event::fake([PathEnrollmentCreated::class]);

            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();
            $courses = Course::factory()->published()->count(2)->create();

            foreach ($courses as $index => $course) {
                $path->courses()->attach($course->id, [
                    'position' => $index + 1,
                    'is_required' => true,
                ]);
            }

            // Create dropped enrollment with progress
            $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
                'progress_percentage' => 50,
            ]);

            // Create course progress for the dropped enrollment
            \App\Models\LearningPathCourseProgress::factory()->available()->create([
                'learning_path_enrollment_id' => $droppedEnrollment->id,
                'course_id' => $courses[0]->id,
                'position' => 1,
            ]);

            // Re-enroll (default: preserve progress)
            $enrollment = $this->service->enroll($user, $path);

            // Progress should be preserved (not reset to 0)
            expect($enrollment->progress_percentage)->toBe(50);
        });

        it('resets progress when requested on re-enrollment', function () {
            Event::fake([PathEnrollmentCreated::class]);

            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();
            $courses = Course::factory()->published()->count(2)->create();

            foreach ($courses as $index => $course) {
                $path->courses()->attach($course->id, [
                    'position' => $index + 1,
                    'is_required' => true,
                ]);
            }

            // Create dropped enrollment with progress
            $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
                'progress_percentage' => 50,
            ]);

            // Re-enroll with preserveProgress=false (reset progress)
            $enrollment = $this->service->enroll($user, $path, preserveProgress: false);

            // Progress should be reset
            expect($enrollment->progress_percentage)->toBe(0);

            // Course progress should be fresh
            $courseProgress = $enrollment->courseProgress()->orderBy('position')->get();
            expect($courseProgress)->toHaveCount(2);
            expect($courseProgress[0]->isAvailable())->toBeTrue();
            expect($courseProgress[1]->isLocked())->toBeTrue();
        });

    });

    describe('drop', function () {
        it('drops active enrollment', function () {
            $enrollment = LearningPathEnrollment::factory()->active()->create();

            $this->service->drop($enrollment, 'Test reason');

            expect($enrollment->fresh()->isDropped())->toBeTrue();
            Event::assertDispatched(PathDropped::class);
        });

        it('throws exception for completed enrollment', function () {
            $enrollment = LearningPathEnrollment::factory()->completed()->create();

            $this->service->drop($enrollment);
        })->throws(InvalidStateTransitionException::class);
    });

    describe('complete', function () {
        it('marks enrollment as completed', function () {
            $enrollment = LearningPathEnrollment::factory()->active()->create();

            $this->service->complete($enrollment);

            expect($enrollment->fresh()->isCompleted())->toBeTrue();
            expect($enrollment->fresh()->completed_at)->not->toBeNull();
            Event::assertDispatched(PathCompleted::class);
        });

        it('is idempotent for already completed enrollment', function () {
            $enrollment = LearningPathEnrollment::factory()->completed()->create();
            $originalCompletedAt = $enrollment->completed_at;

            $this->service->complete($enrollment);

            expect($enrollment->fresh()->completed_at->timestamp)
                ->toBe($originalCompletedAt->timestamp);
        });
    });

    describe('getActiveEnrollments', function () {
        it('returns only active enrollments for user', function () {
            $user = User::factory()->create();

            LearningPathEnrollment::factory()->active()->count(2)->create([
                'user_id' => $user->id,
            ]);
            LearningPathEnrollment::factory()->dropped()->create([
                'user_id' => $user->id,
            ]);
            LearningPathEnrollment::factory()->active()->create(); // Different user

            $enrollments = $this->service->getActiveEnrollments($user);

            expect($enrollments)->toHaveCount(2);
        });
    });
});
