<?php

use App\Domain\LearningPath\Contracts\PathEnrollmentServiceContract;
use App\Domain\LearningPath\Events\PathCompleted;
use App\Domain\LearningPath\Events\PathDropped;
use App\Domain\LearningPath\Events\PathEnrollmentCreated;
use App\Domain\LearningPath\Exceptions\AlreadyEnrolledInPathException;
use App\Domain\LearningPath\Exceptions\PathNotPublishedException;
use App\Domain\Shared\Exceptions\InvalidStateTransitionException;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->service = app(PathEnrollmentServiceContract::class);
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

            $result = $this->service->enroll($user, $path);

            expect($result->isNewEnrollment)->toBeTrue();
            expect($result->enrollment->user_id)->toBe($user->id);
            expect($result->enrollment->learning_path_id)->toBe($path->id);
            expect($result->enrollment->isActive())->toBeTrue();
            expect($result->enrollment->progress_percentage)->toBe(0);

            // Check course progress initialized
            expect($result->enrollment->courseProgress)->toHaveCount(3);

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

            $result = $this->service->enroll($user, $path);

            $courseProgress = $result->enrollment->courseProgress()->orderBy('position')->get();

            expect($courseProgress[0]->isAvailable())->toBeTrue();
            expect($courseProgress[1]->isLocked())->toBeTrue();
            expect($courseProgress[2]->isLocked())->toBeTrue();
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
