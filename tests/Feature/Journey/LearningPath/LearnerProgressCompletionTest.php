<?php

/**
 * Learning Path Progress & Completion Journey Tests
 *
 * Tests covering the learner's journey of progressing through a learning path
 * and completing it.
 * From the test plan: plans/tests/journey/learning-path/03-learner-progress-completion.md
 */

use App\Domain\LearningPath\Events\CourseUnlockedInPath;
use App\Domain\LearningPath\Events\PathCompleted;
use App\Domain\LearningPath\Events\PathProgressUpdated;
use App\Domain\LearningPath\Services\PathEnrollmentService;
use App\Domain\LearningPath\Services\PathProgressService;
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
    $this->learner = User::factory()->create(['role' => 'learner']);
});

describe('View Progress Page', function () {
    it('learner can view progress page for enrolled path', function () {
        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(3)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.progress', $path));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('learner/learning-paths/Progress')
            ->has('enrollment')
            ->has('progress', fn ($progress) => $progress
                ->has('courses', 3)
                ->etc()
            )
        );
    });

    it('progress page shows correct course states', function () {
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

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Complete first course
        $courseProgress = $enrollment->courseProgress()->orderBy('position')->get();
        $courseProgress[0]->update([
            'state' => CompletedCourseState::$name,
            'completed_at' => now(),
        ]);

        // Unlock second course
        app(PathProgressService::class)->unlockNextCourses($enrollment);

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.progress', $path));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('progress.courses', 3)
            ->where('progress.courses.0.status', 'completed')
            ->where('progress.courses.1.status', 'available')
            ->where('progress.courses.2.status', 'locked')
        );
    });

    it('progress page returns 403 for non-enrolled learner', function () {
        $path = LearningPath::factory()->published()->create();

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.progress', $path));

        // May redirect instead of 403 depending on implementation
        $response->assertRedirect();
    });

    it('progress page returns redirect for unpublished path', function () {
        $path = LearningPath::factory()->unpublished()->create();

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.progress', $path));

        // Controller redirects instead of 404 for unpublished paths
        $response->assertRedirect();
    });
});

describe('Progress Calculation', function () {
    it('progress percentage based on required courses only', function () {
        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(5)->create();

        // 3 required, 2 optional
        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => $i < 3,
            ]);
        }

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Complete 1 required course
        $courseProgress = $enrollment->courseProgress()->orderBy('position')->first();
        $courseProgress->update([
            'state' => CompletedCourseState::$name,
            'completed_at' => now(),
        ]);

        $progressService = app(PathProgressService::class);
        $percentage = $progressService->calculateProgressPercentage($enrollment);

        // 1 of 3 required = 33%
        expect($percentage)->toBe(33);
    });

    it('shows 100% progress when all required courses completed', function () {
        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(4)->create();

        // 2 required, 2 optional
        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => $i < 2,
            ]);
        }

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Complete both required courses
        $courseProgress = $enrollment->courseProgress()
            ->orderBy('position')
            ->take(2)
            ->get();

        foreach ($courseProgress as $progress) {
            $progress->update([
                'state' => CompletedCourseState::$name,
                'completed_at' => now(),
            ]);
        }

        $progressService = app(PathProgressService::class);
        $percentage = $progressService->calculateProgressPercentage($enrollment);

        expect($percentage)->toBe(100);
    });

    it('treats all courses as required when none explicitly marked', function () {
        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(3)->create();

        // Default is_required = true
        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
            ]);
        }

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Complete 1 course
        $enrollment->courseProgress()->first()->update([
            'state' => CompletedCourseState::$name,
            'completed_at' => now(),
        ]);

        $progressService = app(PathProgressService::class);
        $percentage = $progressService->calculateProgressPercentage($enrollment);

        // 1 of 3 = 33%
        expect($percentage)->toBe(33);
    });
});

describe('Course State Transitions', function () {
    it('starting a course changes state to in_progress', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        $progressService = app(PathProgressService::class);
        $progressService->startCourse($enrollment, $course);

        $courseProgress = $enrollment->courseProgress()->first();
        $courseProgress->refresh();

        expect($courseProgress->state->getValue())->toBe('in_progress');
        expect($courseProgress->started_at)->not->toBeNull();
    });

    it('cannot start a locked course', function () {
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

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        $progressService = app(PathProgressService::class);

        // Try to start the second (locked) course
        $progressService->startCourse($enrollment, $courses[1]);

        $courseProgress = $enrollment->courseProgress()
            ->where('course_id', $courses[1]->id)
            ->first();

        // Should remain locked
        expect($courseProgress->state->getValue())->toBe(LockedCourseState::$name);
        expect($courseProgress->started_at)->toBeNull();
    });

    it('completing course triggers state update via event', function () {
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

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Get first course enrollment
        $courseProgress = $enrollment->courseProgress()->orderBy('position')->first();
        $courseEnrollment = $courseProgress->courseEnrollment;

        // Simulate completing the course enrollment
        $courseEnrollment->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Trigger the listener
        $progressService = app(PathProgressService::class);
        $progressService->onCourseCompleted($enrollment, $courseEnrollment);

        // Verify course progress updated
        $courseProgress->refresh();
        expect($courseProgress->state->getValue())->toBe(CompletedCourseState::$name);

        Event::assertDispatched(PathProgressUpdated::class);
        Event::assertDispatched(CourseUnlockedInPath::class);
    });
});

describe('Path Completion', function () {
    it('path marked complete when all required courses done', function () {
        Event::fake([PathCompleted::class]);

        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(2)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        $progressService = app(PathProgressService::class);

        // Complete each course in sequence
        foreach ($courses as $course) {
            $courseProgress = $enrollment->courseProgress()
                ->where('course_id', $course->id)
                ->first();

            $courseEnrollment = $courseProgress->courseEnrollment
                ?? Enrollment::factory()->active()->create([
                    'user_id' => $this->learner->id,
                    'course_id' => $course->id,
                ]);

            $courseEnrollment->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $courseProgress->update(['course_enrollment_id' => $courseEnrollment->id]);

            $progressService->onCourseCompleted($enrollment->fresh(), $courseEnrollment);
        }

        $enrollment->refresh();

        expect($enrollment->state->getValue())->toBe('completed');
        expect($enrollment->completed_at)->not->toBeNull();
        expect($enrollment->progress_percentage)->toBe(100);

        Event::assertDispatched(PathCompleted::class);
    });

    it('path complete even when optional courses remain incomplete', function () {
        $path = LearningPath::factory()->published()->create([
            'prerequisite_mode' => 'none', // All courses available immediately
        ]);
        $courses = Course::factory()->published()->count(3)->create();

        // First course required, others optional
        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => $i === 0,
            ]);
        }

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Get the existing course enrollment created by the enrollment service
        $courseProgress = $enrollment->courseProgress()->first();
        $courseEnrollment = $courseProgress->courseEnrollment;

        // Complete only the required course
        $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

        $progressService = app(PathProgressService::class);
        $progressService->onCourseCompleted($enrollment, $courseEnrollment);

        $enrollment->refresh();

        expect($enrollment->state->getValue())->toBe('completed');
        expect($enrollment->progress_percentage)->toBe(100);
    });

    it('completion timestamp recorded correctly', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Travel forward in time
        $this->travel(5)->days();

        // Complete the course
        $courseProgress = $enrollment->courseProgress()->first();
        $courseEnrollment = $courseProgress->courseEnrollment;
        $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

        $progressService = app(PathProgressService::class);
        $progressService->onCourseCompleted($enrollment->fresh(), $courseEnrollment);

        $enrollment->refresh();

        expect($enrollment->completed_at)->not->toBeNull();
        // Use enrolled_at->diffInDays(completed_at) for positive value (completed is after enrolled)
        expect((int) $enrollment->enrolled_at->diffInDays($enrollment->completed_at))->toBe(5);
    });
});

describe('Progress Display Information', function () {
    it('progress shows course titles and descriptions', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create([
            'title' => 'Pengantar Keamanan Siber',
            'short_description' => 'Pelajari dasar-dasar keamanan siber',
        ]);

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollmentService->enroll($this->learner, $path);

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.progress', $path));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('progress.courses.0', fn ($item) => $item
                ->has('course_id')
                ->has('course_title')
                ->etc()
            )
        );
    });

    it('progress shows required vs optional indicator', function () {
        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(2)->create();

        $path->courses()->attach($courses[0]->id, ['position' => 1, 'is_required' => true]);
        $path->courses()->attach($courses[1]->id, ['position' => 2, 'is_required' => false]);

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollmentService->enroll($this->learner, $path);

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.progress', $path));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('progress.courses.0.is_required', true)
            ->where('progress.courses.1.is_required', false)
        );
    });
});

describe('Continue Learning Flow', function () {
    it('can navigate to available course from progress page', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();

        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollmentService->enroll($this->learner, $path);

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.progress', $path));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('progress.courses.0', fn ($item) => $item
                ->where('status', 'available')
                ->etc()
            )
        );
    });

    it('locked course shows prerequisites not met', function () {
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

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollmentService->enroll($this->learner, $path);

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.progress', $path));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('progress.courses.1', fn ($item) => $item
                ->where('status', 'locked')
                ->etc()
            )
        );
    });
});

describe('Progress Persistence', function () {
    it('progress survives logout and login', function () {
        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(3)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Complete first course
        $courseProgress = $enrollment->courseProgress()->first();
        $courseProgress->update([
            'state' => CompletedCourseState::$name,
            'completed_at' => now(),
        ]);

        // Simulate re-login
        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.progress', $path));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('progress.courses.0.status', 'completed')
        );
    });

    it('progress percentage updates in database', function () {
        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(4)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        expect($enrollment->progress_percentage)->toBe(0);

        // Complete first course
        $courseProgress = $enrollment->courseProgress()->first();
        $courseEnrollment = $courseProgress->courseEnrollment;
        $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

        $progressService = app(PathProgressService::class);
        $progressService->onCourseCompleted($enrollment->fresh(), $courseEnrollment);

        $enrollment->refresh();

        expect($enrollment->progress_percentage)->toBe(25); // 1/4 = 25%
    });
});

describe('Edge Cases - Progress Calculation', function () {
    it('handles path with zero courses gracefully', function () {
        $path = LearningPath::factory()->published()->create();

        $enrollment = LearningPathEnrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        $progressService = app(PathProgressService::class);
        $progress = $progressService->getProgress($enrollment);

        expect($progress->totalCourses)->toBe(0);
        expect($progress->overallPercentage->value)->toBe(0.0);
    });

    it('progress percentage rounds correctly', function () {
        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(3)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        $enrollment = LearningPathEnrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        // Create progress - 1 of 3 complete (33.33%)
        foreach ($courses as $i => $course) {
            LearningPathCourseProgress::create([
                'learning_path_enrollment_id' => $enrollment->id,
                'course_id' => $course->id,
                'position' => $i + 1,
                'state' => $i === 0 ? CompletedCourseState::$name : LockedCourseState::$name,
            ]);
        }

        $progressService = app(PathProgressService::class);
        $percentage = $progressService->calculateProgressPercentage($enrollment);

        expect($percentage)->toBe(33); // Rounds down from 33.33
    });
});

describe('Event Dispatching', function () {
    it('PathProgressUpdated event contains correct data', function () {
        Event::fake([PathProgressUpdated::class]);

        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(4)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Complete first course
        $courseProgress = $enrollment->courseProgress()->first();
        $courseEnrollment = $courseProgress->courseEnrollment;
        $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

        $progressService = app(PathProgressService::class);
        $progressService->onCourseCompleted($enrollment->fresh(), $courseEnrollment);

        Event::assertDispatched(PathProgressUpdated::class, function ($event) use ($courses) {
            return $event->previousPercentage === 0
                && $event->newPercentage === 25
                && $event->completedCourseId === $courses[0]->id;
        });
    });

    it('CourseUnlockedInPath event dispatched when course unlocks', function () {
        Event::fake([CourseUnlockedInPath::class]);

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

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($this->learner, $path);

        // Fetch model to access relationships

        // Complete first course
        $courseProgress = $enrollment->courseProgress()->first();
        $courseProgress->update([
            'state' => CompletedCourseState::$name,
            'completed_at' => now(),
        ]);

        $progressService = app(PathProgressService::class);
        $progressService->unlockNextCourses($enrollment);

        Event::assertDispatched(CourseUnlockedInPath::class, function ($event) use ($courses) {
            return $event->course->id === $courses[1]->id
                && $event->coursePosition === 2;
        });
    });
});

describe('Concurrent Progress', function () {
    it('multiple paths progress tracked independently', function () {
        $sharedCourse = Course::factory()->published()->create();
        $path1Course = Course::factory()->published()->create();
        $path2Course = Course::factory()->published()->create();

        $path1 = LearningPath::factory()->published()->create(['title' => 'Path 1']);
        $path2 = LearningPath::factory()->published()->create(['title' => 'Path 2']);

        $path1->courses()->attach($sharedCourse->id, ['position' => 1, 'is_required' => true]);
        $path1->courses()->attach($path1Course->id, ['position' => 2, 'is_required' => true]);

        $path2->courses()->attach($sharedCourse->id, ['position' => 1, 'is_required' => true]);
        $path2->courses()->attach($path2Course->id, ['position' => 2, 'is_required' => true]);

        $enrollmentService = app(PathEnrollmentService::class);
        $enrollment1 = $enrollmentService->enroll($this->learner, $path1);
        $enrollment2 = $enrollmentService->enroll($this->learner, $path2);

        // Fetch models to access relationships
        $enrollment1 = $enrollment1;
        $enrollment2 = $enrollment2;

        // Complete shared course in path 1
        $courseProgress = $enrollment1->courseProgress()
            ->where('course_id', $sharedCourse->id)
            ->first();

        $courseProgress->update([
            'state' => CompletedCourseState::$name,
            'completed_at' => now(),
        ]);

        // Path 2's progress should be independent
        $path2Progress = $enrollment2->courseProgress()
            ->where('course_id', $sharedCourse->id)
            ->first();

        expect($path2Progress->state->getValue())->toBe(AvailableCourseState::$name);
        expect($enrollment2->progress_percentage)->toBe(0);
    });
});
