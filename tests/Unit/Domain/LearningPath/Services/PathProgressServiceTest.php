<?php

use App\Domain\Enrollment\Events\UserDropped;
use App\Domain\LearningPath\Contracts\PathEnrollmentServiceContract;
use App\Domain\LearningPath\Contracts\PathProgressServiceContract;
use App\Domain\LearningPath\Events\CourseUnlockedInPath;
use App\Domain\LearningPath\Events\PathProgressUpdated;
use App\Domain\LearningPath\Listeners\UpdatePathProgressOnCourseDrop;
use App\Domain\LearningPath\States\AvailableCourseState;
use App\Domain\LearningPath\States\CompletedCourseState;
use App\Domain\LearningPath\States\LockedCourseState;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\LearningPathCourseProgress;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->progressService = app(PathProgressServiceContract::class);
    $this->enrollmentService = app(PathEnrollmentServiceContract::class);
});

describe('PathProgressService', function () {
    describe('unlockNextCourses', function () {
        it('creates course enrollment when unlocking a course', function () {
            Event::fake([CourseUnlockedInPath::class]);

            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create([
                'prerequisite_mode' => 'sequential',
            ]);
            $courses = Course::factory()->published()->count(3)->create();

            foreach ($courses as $index => $course) {
                $path->courses()->attach($course->id, [
                    'position' => $index + 1,
                    'is_required' => true,
                ]);
            }

            // Enroll user in path
            $pathEnrollment = $this->enrollmentService->enroll($user, $path);

            // Get course progress records
            $courseProgress = $pathEnrollment->courseProgress()->orderBy('position')->get();

            // Verify initial state - first course enrolled, others locked without enrollment
            expect($courseProgress[0]->course_enrollment_id)->not->toBeNull();
            expect($courseProgress[1]->course_enrollment_id)->toBeNull();
            expect($courseProgress[1]->isLocked())->toBeTrue();

            // Simulate completing the first course enrollment
            $firstCourseEnrollment = Enrollment::find($courseProgress[0]->course_enrollment_id);
            $firstCourseEnrollment->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Mark first course progress as completed
            $courseProgress[0]->update([
                'state' => CompletedCourseState::$name,
                'completed_at' => now(),
            ]);

            // Unlock next courses
            $unlockedCourses = $this->progressService->unlockNextCourses($pathEnrollment->fresh());

            // Second course should now be unlocked
            expect($unlockedCourses)->toHaveCount(1);
            expect($unlockedCourses[0]->id)->toBe($courses[1]->id);

            // Refresh and check second course progress
            $courseProgress = $pathEnrollment->courseProgress()->orderBy('position')->get();

            expect($courseProgress[1]->isAvailable())->toBeTrue();
            expect($courseProgress[1]->course_enrollment_id)->not->toBeNull();
            expect($courseProgress[1]->unlocked_at)->not->toBeNull();

            // Verify the course enrollment was created
            $secondCourseEnrollment = $courseProgress[1]->courseEnrollment;
            expect($secondCourseEnrollment)->not->toBeNull();
            expect($secondCourseEnrollment->user_id)->toBe($user->id);
            expect($secondCourseEnrollment->course_id)->toBe($courses[1]->id);
            expect($secondCourseEnrollment->isActive())->toBeTrue();

            // Third course should still be locked
            expect($courseProgress[2]->isLocked())->toBeTrue();
            expect($courseProgress[2]->course_enrollment_id)->toBeNull();

            Event::assertDispatched(CourseUnlockedInPath::class);
        });

        it('reuses existing course enrollment when unlocking', function () {
            Event::fake([CourseUnlockedInPath::class]);

            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create([
                'prerequisite_mode' => 'sequential',
            ]);
            $courses = Course::factory()->published()->count(2)->create();

            // User already has enrollment in second course (from another path or direct enrollment)
            $existingEnrollment = Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $courses[1]->id,
            ]);

            foreach ($courses as $index => $course) {
                $path->courses()->attach($course->id, [
                    'position' => $index + 1,
                    'is_required' => true,
                ]);
            }

            // Enroll user in path
            $pathEnrollment = $this->enrollmentService->enroll($user, $path);

            // Complete first course
            $courseProgress = $pathEnrollment->courseProgress()->orderBy('position')->get();
            $courseProgress[0]->update([
                'state' => CompletedCourseState::$name,
                'completed_at' => now(),
            ]);

            // Unlock next courses
            $this->progressService->unlockNextCourses($pathEnrollment->fresh());

            // Check second course reuses existing enrollment
            $courseProgress = $pathEnrollment->courseProgress()->orderBy('position')->get();
            expect($courseProgress[1]->course_enrollment_id)->toBe($existingEnrollment->id);

            // Should not create duplicate enrollment
            $enrollmentCount = Enrollment::where('user_id', $user->id)
                ->where('course_id', $courses[1]->id)
                ->count();
            expect($enrollmentCount)->toBe(1);
        });
    });

    describe('calculateProgressPercentage', function () {
        it('calculates percentage based on completed required courses', function () {
            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();
            $courses = Course::factory()->published()->count(4)->create();

            foreach ($courses as $index => $course) {
                $path->courses()->attach($course->id, [
                    'position' => $index + 1,
                    'is_required' => true,
                ]);
            }

            $enrollment = LearningPathEnrollment::factory()->active()->create([
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
            ]);

            // Create course progress - 2 completed, 2 locked
            foreach ($courses as $index => $course) {
                LearningPathCourseProgress::create([
                    'learning_path_enrollment_id' => $enrollment->id,
                    'course_id' => $course->id,
                    'position' => $index + 1,
                    'state' => $index < 2 ? CompletedCourseState::$name : LockedCourseState::$name,
                ]);
            }

            $percentage = $this->progressService->calculateProgressPercentage($enrollment);

            expect($percentage)->toBe(50); // 2/4 = 50%
        });

        it('only counts required courses for progress', function () {
            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();
            $courses = Course::factory()->published()->count(5)->create();

            // First 3 courses required, last 2 optional
            foreach ($courses as $index => $course) {
                $path->courses()->attach($course->id, [
                    'position' => $index + 1,
                    'is_required' => $index < 3,
                ]);
            }

            $enrollment = LearningPathEnrollment::factory()->active()->create([
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
            ]);

            // Complete all 3 required courses, none of the optional
            foreach ($courses as $index => $course) {
                LearningPathCourseProgress::create([
                    'learning_path_enrollment_id' => $enrollment->id,
                    'course_id' => $course->id,
                    'position' => $index + 1,
                    'state' => $index < 3 ? CompletedCourseState::$name : AvailableCourseState::$name,
                ]);
            }

            $percentage = $this->progressService->calculateProgressPercentage($enrollment);

            // Should be 100% because all REQUIRED courses are done
            expect($percentage)->toBe(100);
        });

        it('includes required stats in getProgress result', function () {
            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();
            $courses = Course::factory()->published()->count(4)->create();

            // First 2 courses required, last 2 optional
            foreach ($courses as $index => $course) {
                $path->courses()->attach($course->id, [
                    'position' => $index + 1,
                    'is_required' => $index < 2,
                ]);
            }

            $enrollment = LearningPathEnrollment::factory()->active()->create([
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
            ]);

            // Complete 1 required, 1 optional
            foreach ($courses as $index => $course) {
                LearningPathCourseProgress::create([
                    'learning_path_enrollment_id' => $enrollment->id,
                    'course_id' => $course->id,
                    'position' => $index + 1,
                    'state' => $index === 0 || $index === 2
                        ? CompletedCourseState::$name
                        : AvailableCourseState::$name,
                ]);
            }

            $progress = $this->progressService->getProgress($enrollment);

            // Total stats include all courses
            expect($progress->totalCourses)->toBe(4);
            expect($progress->completedCourses)->toBe(2); // 1 required + 1 optional

            // Required stats only count required courses
            expect($progress->requiredCourses)->toBe(2);
            expect($progress->completedRequiredCourses)->toBe(1);
            expect($progress->requiredPercentage)->toBe(50); // 1/2 = 50%
        });
    });

    describe('isPathCompleted', function () {
        it('returns true when all required courses are completed', function () {
            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();
            $courses = Course::factory()->published()->count(3)->create();

            // First 2 courses required, third optional
            foreach ($courses as $index => $course) {
                $path->courses()->attach($course->id, [
                    'position' => $index + 1,
                    'is_required' => $index < 2, // Only first 2 required
                ]);
            }

            $enrollment = LearningPathEnrollment::factory()->active()->create([
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
            ]);

            // Complete only required courses
            foreach ($courses as $index => $course) {
                LearningPathCourseProgress::create([
                    'learning_path_enrollment_id' => $enrollment->id,
                    'course_id' => $course->id,
                    'position' => $index + 1,
                    'state' => $index < 2 ? CompletedCourseState::$name : AvailableCourseState::$name,
                ]);
            }

            expect($this->progressService->isPathCompleted($enrollment))->toBeTrue();
        });

        it('returns false when required courses are not completed', function () {
            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();
            $courses = Course::factory()->published()->count(3)->create();

            foreach ($courses as $index => $course) {
                $path->courses()->attach($course->id, [
                    'position' => $index + 1,
                    'is_required' => true,
                ]);
            }

            $enrollment = LearningPathEnrollment::factory()->active()->create([
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
            ]);

            // Complete only 1 of 3 required courses
            foreach ($courses as $index => $course) {
                LearningPathCourseProgress::create([
                    'learning_path_enrollment_id' => $enrollment->id,
                    'course_id' => $course->id,
                    'position' => $index + 1,
                    'state' => $index === 0 ? CompletedCourseState::$name : LockedCourseState::$name,
                ]);
            }

            expect($this->progressService->isPathCompleted($enrollment))->toBeFalse();
        });
    });

    describe('UpdatePathProgressOnCourseDrop listener', function () {
        it('reverts completed course progress when course enrollment dropped', function () {
            Event::fake([PathProgressUpdated::class]);

            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();
            $courses = Course::factory()->published()->count(2)->create();

            foreach ($courses as $index => $course) {
                $path->courses()->attach($course->id, [
                    'position' => $index + 1,
                    'is_required' => true,
                ]);
            }

            // Enroll in path
            $pathEnrollment = $this->enrollmentService->enroll($user, $path);

            // Get course progress and enrollment
            $courseProgress = $pathEnrollment->courseProgress()->orderBy('position')->first();
            $courseEnrollment = $courseProgress->courseEnrollment;

            // Mark course as completed in path progress
            $courseProgress->update([
                'state' => CompletedCourseState::$name,
                'completed_at' => now(),
            ]);
            $pathEnrollment->update(['progress_percentage' => 50]);

            // Simulate dropping the course enrollment
            $courseEnrollment->update(['status' => 'dropped']);

            // Dispatch the event and handle it
            $listener = app(UpdatePathProgressOnCourseDrop::class);
            $listener->handle(new UserDropped($courseEnrollment, 'Testing'));

            // Verify course progress reverted to available
            $courseProgress->refresh();
            expect($courseProgress->isAvailable())->toBeTrue();
            expect($courseProgress->completed_at)->toBeNull();

            // Verify path progress recalculated
            $pathEnrollment->refresh();
            expect($pathEnrollment->progress_percentage)->toBe(0);
        });

        it('reverts completed path to active when course dropped', function () {
            Event::fake([PathProgressUpdated::class]);

            $user = User::factory()->create();
            $path = LearningPath::factory()->published()->create();
            $course = Course::factory()->published()->create();

            $path->courses()->attach($course->id, [
                'position' => 1,
                'is_required' => true,
            ]);

            // Create completed path enrollment
            $pathEnrollment = LearningPathEnrollment::factory()->completed()->create([
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
                'progress_percentage' => 100,
            ]);

            // Create completed course enrollment
            $courseEnrollment = Enrollment::factory()->completed()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            // Link them via course progress
            LearningPathCourseProgress::create([
                'learning_path_enrollment_id' => $pathEnrollment->id,
                'course_id' => $course->id,
                'course_enrollment_id' => $courseEnrollment->id,
                'state' => CompletedCourseState::$name,
                'position' => 1,
                'completed_at' => now(),
            ]);

            // Simulate dropping the course
            $courseEnrollment->update(['status' => 'dropped']);

            // Handle the event
            $listener = app(UpdatePathProgressOnCourseDrop::class);
            $listener->handle(new UserDropped($courseEnrollment, 'Testing'));

            // Path should be reverted to active
            $pathEnrollment->refresh();
            expect($pathEnrollment->isActive())->toBeTrue();
            expect($pathEnrollment->completed_at)->toBeNull();
            expect($pathEnrollment->progress_percentage)->toBe(0);
        });

        it('does not affect unrelated path enrollments', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();

            // Create course enrollment without linking to any path
            $courseEnrollment = Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            // Handle the drop event - should not throw
            $listener = app(UpdatePathProgressOnCourseDrop::class);
            $listener->handle(new UserDropped($courseEnrollment, 'Testing'));

            // No assertions needed - just verify it doesn't error
            expect(true)->toBeTrue();
        });
    });
});
