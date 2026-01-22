<?php

use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;
use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\Enrollment\Events\UserDropped;
use App\Domain\Enrollment\Events\UserEnrolled;
use App\Domain\Enrollment\Exceptions\AlreadyEnrolledException;
use App\Domain\Enrollment\Exceptions\CourseNotPublishedException;
use App\Domain\Shared\Exceptions\InvalidStateTransitionException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->service = app(EnrollmentServiceContract::class);
    Event::fake();
});

describe('EnrollmentService', function () {
    describe('enroll', function () {
        it('enrolls user in published course', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();

            // Service now returns Enrollment model directly
            $enrollment = $this->service->enroll(
                userId: $user->id,
                courseId: $course->id,
            );

            expect($enrollment)->toBeInstanceOf(Enrollment::class);
            expect($enrollment->user_id)->toBe($user->id);
            expect($enrollment->course_id)->toBe($course->id);
            expect($enrollment->isActive())->toBeTrue();
            expect($enrollment->progress_percentage)->toBe(0);

            Event::assertDispatched(UserEnrolled::class);
        });

        it('throws exception for draft course', function () {
            $user = User::factory()->create();
            $course = Course::factory()->draft()->create();

            $this->service->enroll(
                userId: $user->id,
                courseId: $course->id,
            );
        })->throws(CourseNotPublishedException::class);

        it('throws exception when already enrolled', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            $this->service->enroll(
                userId: $user->id,
                courseId: $course->id,
            );
        })->throws(AlreadyEnrolledException::class);

        it('allows enrollment with invited_by', function () {
            $user = User::factory()->create();
            $inviter = User::factory()->create();
            $course = Course::factory()->published()->create();

            $enrollment = $this->service->enroll(
                userId: $user->id,
                courseId: $course->id,
                invitedBy: $inviter->id,
            );

            expect($enrollment->invited_by)->toBe($inviter->id);
        });
    });

    describe('canEnroll', function () {
        it('returns true for published public course', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->public()->create();

            expect($this->service->canEnroll($user, $course))->toBeTrue();
        });

        it('returns false when already enrolled', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            expect($this->service->canEnroll($user, $course))->toBeFalse();
        });

        it('returns false for draft course', function () {
            $user = User::factory()->create();
            $course = Course::factory()->draft()->create();

            expect($this->service->canEnroll($user, $course))->toBeFalse();
        });

        it('returns false for hidden course', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create([
                'visibility' => 'hidden',
            ]);

            expect($this->service->canEnroll($user, $course))->toBeFalse();
        });
    });

    // Note: drop() and complete() are now on the Enrollment model
    // These tests verify the model methods work correctly
    describe('Enrollment model drop', function () {
        it('drops active enrollment', function () {
            $enrollment = Enrollment::factory()->active()->create();

            $enrollment->drop('Test reason');

            expect($enrollment->fresh()->isDropped())->toBeTrue();
            Event::assertDispatched(UserDropped::class);
        });

        it('throws exception for completed enrollment', function () {
            $enrollment = Enrollment::factory()->completed()->create();

            $enrollment->drop();
        })->throws(InvalidStateTransitionException::class);
    });

    describe('Enrollment model complete', function () {
        it('marks enrollment as completed', function () {
            $enrollment = Enrollment::factory()->active()->create();

            $enrollment->complete();

            expect($enrollment->fresh()->isCompleted())->toBeTrue();
            expect($enrollment->fresh()->completed_at)->not->toBeNull();
            Event::assertDispatched(EnrollmentCompleted::class);
        });

        it('is idempotent for already completed enrollment', function () {
            $enrollment = Enrollment::factory()->completed()->create();
            $originalCompletedAt = $enrollment->completed_at;

            $enrollment->complete();

            expect($enrollment->fresh()->completed_at->timestamp)
                ->toBe($originalCompletedAt->timestamp);
        });
    });
});
