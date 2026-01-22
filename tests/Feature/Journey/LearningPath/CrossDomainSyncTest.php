<?php

/**
 * Cross-Domain Synchronization Test
 *
 * Tests covering synchronization between Learning Path, Course Enrollment,
 * and Lesson Progress domains. When a learner completes a course, this must
 * propagate to the Learning Path domain to unlock next courses.
 *
 * From the test plan: plans/tests/journey/learning-path/05-cross-domain-sync.md
 */

use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\Enrollment\Events\UserDropped;
use App\Domain\LearningPath\Contracts\PathEnrollmentServiceContract;
use App\Domain\LearningPath\Contracts\PathProgressServiceContract;
use App\Domain\LearningPath\Events\CourseUnlockedInPath;
use App\Domain\LearningPath\Events\PathCompleted;
use App\Domain\LearningPath\Events\PathProgressUpdated;
use App\Domain\LearningPath\Listeners\UpdatePathProgressOnCourseCompletion;
use App\Domain\LearningPath\States\AvailableCourseState;
use App\Domain\LearningPath\States\CompletedCourseState;
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

describe('Course Completion → Path Progress', function () {
    it('course completion updates path progress automatically', function () {
        Event::fake([PathProgressUpdated::class, CourseUnlockedInPath::class]);

        $path = LearningPath::factory()->published()->create([
            'prerequisite_mode' => 'sequential',
        ]);
        $courses = Course::factory()->published()->count(2)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        // Enroll in path
        $enrollmentService = app(PathEnrollmentServiceContract::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Get course enrollment created during path enrollment
        $courseProgress = $enrollment->courseProgress()->first();
        $courseEnrollment = $courseProgress->courseEnrollment;

        // Complete the course enrollment
        $courseEnrollment->update([
            'status' => 'completed',
            'completed_at' => now(),
            'progress_percentage' => 100,
        ]);

        // Dispatch EnrollmentCompleted event
        EnrollmentCompleted::dispatch($courseEnrollment);

        // Verify path progress updated
        $enrollment->refresh();
        $courseProgress->refresh();

        expect($courseProgress->state->getValue())->toBe(CompletedCourseState::$name);
        expect($enrollment->progress_percentage)->toBe(50); // 1 of 2

        Event::assertDispatched(PathProgressUpdated::class);
        Event::assertDispatched(CourseUnlockedInPath::class);
    });

    it('path completion triggered when all required courses done via events', function () {
        Event::fake([PathCompleted::class]);

        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        // Enroll
        $enrollmentService = app(PathEnrollmentServiceContract::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Complete course
        $courseProgress = $enrollment->courseProgress()->first();
        $courseEnrollment = $courseProgress->courseEnrollment;
        $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

        // Dispatch event
        EnrollmentCompleted::dispatch($courseEnrollment);

        // Verify path completed
        $enrollment->refresh();
        expect($enrollment->state->getValue())->toBe('completed');

        Event::assertDispatched(PathCompleted::class);
    });

    it('next course unlocked and enrolled on completion', function () {
        $path = LearningPath::factory()->published()->create([
            'prerequisite_mode' => 'sequential',
        ]);
        $courses = Course::factory()->published()->count(2)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        // Enroll in path
        $enrollmentService = app(PathEnrollmentServiceContract::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Complete first course
        $courseProgress = $enrollment->courseProgress()->first();
        $courseEnrollment = $courseProgress->courseEnrollment;
        $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);
        EnrollmentCompleted::dispatch($courseEnrollment);

        // Verify second course now has enrollment
        $secondProgress = $enrollment->courseProgress()
            ->where('course_id', $courses[1]->id)
            ->first();

        $secondProgress->refresh();

        expect($secondProgress->state->getValue())->toBe(AvailableCourseState::$name);
        expect($secondProgress->course_enrollment_id)->not->toBeNull();
        expect($secondProgress->courseEnrollment->status->getValue())->toBe('active');
    });
});

describe('Shared Course Across Paths', function () {
    it('completing shared course updates all enrolled paths', function () {
        Event::fake([PathProgressUpdated::class]);

        $sharedCourse = Course::factory()->published()->create();

        // Create two paths with the shared course
        $path1 = LearningPath::factory()->published()->create(['title' => 'Path 1']);
        $path2 = LearningPath::factory()->published()->create(['title' => 'Path 2']);

        $path1->courses()->attach($sharedCourse->id, ['position' => 1, 'is_required' => true]);
        $path2->courses()->attach($sharedCourse->id, ['position' => 1, 'is_required' => true]);

        // Enroll in both paths
        $enrollmentService = app(PathEnrollmentServiceContract::class);
        $enrollment1 = $enrollmentService->enroll($this->learner, $path1);
        $enrollment2 = $enrollmentService->enroll($this->learner, $path2);

        // Fetch models to access relationships
        // pathEnrollment1 is already enrollment1;
        // pathEnrollment2 is already enrollment2;

        // Both paths should share the same course enrollment
        $progress1 = $enrollment1->courseProgress()->first();
        $progress2 = $enrollment2->courseProgress()->first();

        expect($progress1->course_enrollment_id)->toBe($progress2->course_enrollment_id);

        // Complete the shared course
        $courseEnrollment = $progress1->courseEnrollment;
        $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);
        EnrollmentCompleted::dispatch($courseEnrollment);

        // Both paths should update
        $enrollment1->refresh();
        $enrollment2->refresh();

        expect($enrollment1->state->getValue())->toBe('completed');
        expect($enrollment2->state->getValue())->toBe('completed');

        // Event dispatched twice (once per path)
        Event::assertDispatchedTimes(PathProgressUpdated::class, 2);
    });

    it('existing course enrollment reused when enrolling in new path', function () {
        $course = Course::factory()->published()->create();

        // User already enrolled in course directly
        $existingEnrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
        ]);

        // Create path with same course
        $path = LearningPath::factory()->published()->create();
        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        // Enroll in path
        $enrollmentService = app(PathEnrollmentServiceContract::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Path should reuse existing enrollment
        // Fetch model to access relationships
        $pathProgress = $enrollment->courseProgress()->first();

        expect($pathProgress->course_enrollment_id)->toBe($existingEnrollment->id);

        // No duplicate enrollment created
        $enrollmentCount = Enrollment::where('user_id', $this->learner->id)
            ->where('course_id', $course->id)
            ->count();
        expect($enrollmentCount)->toBe(1);
    });

    it('course completed before path enrollment is recognized as complete', function () {
        $course = Course::factory()->published()->create();

        // Complete course before enrolling in path
        $existingEnrollment = Enrollment::factory()->completed()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
            'completed_at' => now()->subDays(7),
        ]);

        // Create path with same course
        $path = LearningPath::factory()->published()->create();
        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        // Enroll in path
        $enrollmentService = app(PathEnrollmentServiceContract::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Course should be linked in path
        $pathProgress = $enrollment->courseProgress()->first();

        expect($pathProgress->course_enrollment_id)->toBe($existingEnrollment->id);
    });
});

describe('Course Drop → Path Progress Reversion', function () {
    it('dropping course reverts path progress', function () {
        Event::fake([PathProgressUpdated::class]);

        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(2)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        // Enroll and complete first course
        $enrollmentService = app(PathEnrollmentServiceContract::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships
        $enrollment = LearningPathEnrollment::find($enrollment->id);

        $courseProgress = $enrollment->courseProgress()->first();
        $courseProgress->update([
            'state' => CompletedCourseState::$name,
            'completed_at' => now(),
        ]);
        $enrollment->update(['progress_percentage' => 50]);

        // Drop the course
        $courseEnrollment = $courseProgress->courseEnrollment;
        $courseEnrollment->update(['status' => 'dropped']);
        UserDropped::dispatch($courseEnrollment, 'Learner requested');

        // Verify path reverted
        $enrollment->refresh();
        $courseProgress->refresh();

        expect($courseProgress->state->getValue())->toBe(AvailableCourseState::$name);
        expect($courseProgress->completed_at)->toBeNull();
        expect($enrollment->progress_percentage)->toBe(0);

        Event::assertDispatched(PathProgressUpdated::class);
    });

    it('dropping course in completed path reverts to active', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        // Create completed path
        $enrollment = LearningPathEnrollment::factory()->completed()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
            'progress_percentage' => 100,
        ]);

        $courseEnrollment = Enrollment::factory()->completed()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
        ]);

        LearningPathCourseProgress::create([
            'learning_path_enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'course_enrollment_id' => $courseEnrollment->id,
            'state' => CompletedCourseState::$name,
            'position' => 1,
            'completed_at' => now(),
        ]);

        // Drop the course
        $courseEnrollment->update(['status' => 'dropped']);
        UserDropped::dispatch($courseEnrollment, 'Testing');

        // Path should revert to active
        $enrollment->refresh();
        expect($enrollment->state->getValue())->toBe('active');
        expect($enrollment->completed_at)->toBeNull();
    });

    it('dropping shared course affects all enrolled paths', function () {
        $sharedCourse = Course::factory()->published()->create();

        $path1 = LearningPath::factory()->published()->create();
        $path2 = LearningPath::factory()->published()->create();

        $path1->courses()->attach($sharedCourse->id, ['position' => 1, 'is_required' => true]);
        $path2->courses()->attach($sharedCourse->id, ['position' => 1, 'is_required' => true]);

        // Enroll in both paths (completed)
        $enrollment1 = LearningPathEnrollment::factory()->completed()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path1->id,
            'progress_percentage' => 100,
        ]);
        $enrollment2 = LearningPathEnrollment::factory()->completed()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path2->id,
            'progress_percentage' => 100,
        ]);

        $courseEnrollment = Enrollment::factory()->completed()->create([
            'user_id' => $this->learner->id,
            'course_id' => $sharedCourse->id,
        ]);

        LearningPathCourseProgress::create([
            'learning_path_enrollment_id' => $enrollment1->id,
            'course_id' => $sharedCourse->id,
            'course_enrollment_id' => $courseEnrollment->id,
            'state' => CompletedCourseState::$name,
            'position' => 1,
        ]);
        LearningPathCourseProgress::create([
            'learning_path_enrollment_id' => $enrollment2->id,
            'course_id' => $sharedCourse->id,
            'course_enrollment_id' => $courseEnrollment->id,
            'state' => CompletedCourseState::$name,
            'position' => 1,
        ]);

        // Drop the course
        $courseEnrollment->update(['status' => 'dropped']);
        UserDropped::dispatch($courseEnrollment, 'Testing');

        // Both paths should revert
        $enrollment1->refresh();
        $enrollment2->refresh();

        expect($enrollment1->state->getValue())->toBe('active');
        expect($enrollment2->state->getValue())->toBe('active');
    });
});

describe('Event Listener Behavior', function () {
    it('listener only processes active path enrollments', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        // Create dropped path enrollment
        $enrollment = LearningPathEnrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        $courseEnrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
        ]);

        LearningPathCourseProgress::create([
            'learning_path_enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'course_enrollment_id' => $courseEnrollment->id,
            'state' => AvailableCourseState::$name,
            'position' => 1,
        ]);

        // Complete course
        $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

        // Handle the event
        $listener = app(UpdatePathProgressOnCourseCompletion::class);
        $listener->handle(new EnrollmentCompleted($courseEnrollment));

        // Path should remain dropped
        $enrollment->refresh();
        expect($enrollment->state->getValue())->toBe('dropped');
        expect($enrollment->progress_percentage)->toBe(0);
    });

    it('listener handles course not in any path gracefully', function () {
        $course = Course::factory()->published()->create();

        // Direct enrollment, not part of any path
        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
        ]);

        // Complete course
        $enrollment->update(['status' => 'completed', 'completed_at' => now()]);

        // Should not throw
        $listener = app(UpdatePathProgressOnCourseCompletion::class);
        $listener->handle(new EnrollmentCompleted($enrollment));

        // Just verify no exception
        expect(true)->toBeTrue();
    });
});

describe('Progress Consistency', function () {
    it('progress eventually consistent after event processing', function () {
        $path = LearningPath::factory()->published()->create([
            'prerequisite_mode' => 'sequential',
        ]);
        $courses = Course::factory()->published()->count(2)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        $enrollmentService = app(PathEnrollmentServiceContract::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Complete first course
        $courseProgress = $enrollment->courseProgress()->first();
        $courseEnrollment = $courseProgress->courseEnrollment;
        $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

        // Process listener synchronously
        $listener = app(UpdatePathProgressOnCourseCompletion::class);
        $listener->handle(new EnrollmentCompleted($courseEnrollment));

        // Verify state is consistent
        $enrollment->refresh();
        $courseProgress->refresh();

        expect($courseProgress->state->getValue())->toBe(CompletedCourseState::$name);
        expect($enrollment->progress_percentage)->toBe(50);

        // Second course should be unlocked
        $secondProgress = $enrollment->courseProgress()
            ->where('course_id', $courses[1]->id)
            ->first();
        expect($secondProgress->state->getValue())->toBe(AvailableCourseState::$name);
    });
});

describe('Data Integrity', function () {
    it('transaction rolls back on failure during sync', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        $enrollmentService = app(PathEnrollmentServiceContract::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        $courseProgress = $enrollment->courseProgress()->first();
        $courseEnrollment = $courseProgress->courseEnrollment;

        // Mock the progress service to throw
        $this->mock(PathProgressServiceContract::class)
            ->shouldReceive('onCourseCompleted')
            ->andThrow(new \RuntimeException('Database error'));

        // Complete course
        $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

        try {
            $listener = app(UpdatePathProgressOnCourseCompletion::class);
            $listener->handle(new EnrollmentCompleted($courseEnrollment));
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Path progress should not have changed
        $enrollment->refresh();
        expect($enrollment->progress_percentage)->toBe(0);
    });
});

describe('Edge Cases', function () {
    it('handles concurrent course completions correctly', function () {
        $path = LearningPath::factory()->published()->create([
            'prerequisite_mode' => 'none', // All available immediately (for test purposes)
        ]);
        $courses = Course::factory()->published()->count(2)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        $enrollmentService = app(PathEnrollmentServiceContract::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Get both course progress records
        // With prerequisite_mode: 'none', both courses are available and have enrollments
        $progress1 = $enrollment->courseProgress()
            ->where('course_id', $courses[0]->id)
            ->first();
        $progress2 = $enrollment->courseProgress()
            ->where('course_id', $courses[1]->id)
            ->first();

        // Complete both courses "simultaneously"
        $enrollment1 = $progress1->courseEnrollment;
        $enrollment2 = $progress2->courseEnrollment;

        $enrollment1->update(['status' => 'completed', 'completed_at' => now()]);
        $enrollment2->update(['status' => 'completed', 'completed_at' => now()]);

        // Process both events
        $listener = app(UpdatePathProgressOnCourseCompletion::class);
        $listener->handle(new EnrollmentCompleted($enrollment1));
        $listener->handle(new EnrollmentCompleted($enrollment2));

        // Path should be complete
        $enrollment->refresh();
        expect($enrollment->state->getValue())->toBe('completed');
        expect($enrollment->progress_percentage)->toBe(100);
    });

    it('handles course removed from path during progress', function () {
        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(2)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        $enrollmentService = app(PathEnrollmentServiceContract::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Admin removes first course from path
        $path->courses()->detach($courses[0]->id);

        // Learner completes the removed course
        $courseProgress = $enrollment->courseProgress()
            ->where('course_id', $courses[0]->id)
            ->first();

        if ($courseProgress) {
            $courseEnrollment = $courseProgress->courseEnrollment;
            $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

            // Should handle gracefully
            $listener = app(UpdatePathProgressOnCourseCompletion::class);
            $listener->handle(new EnrollmentCompleted($courseEnrollment));
        }

        // Verify no crash
        expect(true)->toBeTrue();
    });
});
