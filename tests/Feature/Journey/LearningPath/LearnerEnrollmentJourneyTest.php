<?php

/**
 * Learning Path Learner Enrollment Journey Tests
 *
 * Tests covering the complete learner journey for enrolling in learning paths.
 * From the test plan: plans/tests/journey/learning-path/02-learner-enrollment.md
 */

use App\Domain\LearningPath\Events\PathDropped;
use App\Domain\LearningPath\Events\PathEnrollmentCreated;
use App\Domain\LearningPath\States\AvailableCourseState;
use App\Domain\LearningPath\States\CompletedCourseState;
use App\Domain\LearningPath\States\LockedCourseState;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->learner = User::factory()->create(['role' => 'learner']);
});

describe('View Learning Path Details', function () {
    it('can view published learning path details when not enrolled', function () {
        $creator = User::factory()->create(['role' => 'content_manager']);

        $path = LearningPath::factory()->published()->create([
            'title' => 'Jalur Keamanan Siber',
            'description' => 'Pelajari dasar-dasar keamanan siber',
            'created_by' => $creator->id,
        ]);

        $courses = Course::factory()->published()->count(3)->create();
        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.show', $path));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('learner/learning-paths/Show')
            ->has('learningPath', fn ($lp) => $lp
                ->where('title', 'Jalur Keamanan Siber')
                ->has('courses', 3)
                ->etc()
            )
            ->where('enrollment', null)
            ->where('canEnroll', true)
        );
    });

    it('shows all courses in correct order', function () {
        $path = LearningPath::factory()->published()->create();

        $course1 = Course::factory()->published()->create(['title' => 'Kursus Pertama']);
        $course2 = Course::factory()->published()->create(['title' => 'Kursus Kedua']);
        $course3 = Course::factory()->published()->create(['title' => 'Kursus Ketiga']);

        $path->courses()->attach($course1->id, ['position' => 1, 'is_required' => true]);
        $path->courses()->attach($course2->id, ['position' => 2, 'is_required' => true]);
        $path->courses()->attach($course3->id, ['position' => 3, 'is_required' => false]);

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.show', $path));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('learningPath.courses.0.title', 'Kursus Pertama')
            ->where('learningPath.courses.0.position', 1)
            ->where('learningPath.courses.0.is_required', true)
            ->where('learningPath.courses.1.title', 'Kursus Kedua')
            ->where('learningPath.courses.1.position', 2)
            ->where('learningPath.courses.2.title', 'Kursus Ketiga')
            ->where('learningPath.courses.2.is_required', false)
        );
    });

    it('cannot view unpublished learning path as learner', function () {
        $path = LearningPath::factory()->unpublished()->create();

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.show', $path));

        $response->assertForbidden();
    });

    it('shows enrollment and progress when already enrolled', function () {
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
            'progress_percentage' => 33,
        ]);

        foreach ($courses as $i => $course) {
            $enrollment->courseProgress()->create([
                'course_id' => $course->id,
                'state' => $i === 0 ? CompletedCourseState::$name : ($i === 1 ? AvailableCourseState::$name : LockedCourseState::$name),
                'position' => $i + 1,
            ]);
        }

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.show', $path));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('enrollment')
            ->where('enrollment.state', 'active')
            ->has('progress')
            ->where('canEnroll', false)
        );
    });
});

describe('Enrollment Action', function () {
    it('successfully enrolls in learning path', function () {
        Event::fake([PathEnrollmentCreated::class]);

        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(3)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        $response = $this->actingAs($this->learner)
            ->post(route('learner.learning-paths.enroll', $path));

        $response->assertRedirect(route('learner.learning-paths.show', $path));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('learning_path_enrollments', [
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
            'state' => 'active',
            'progress_percentage' => 0,
        ]);

        $enrollment = LearningPathEnrollment::where('user_id', $this->learner->id)->first();
        expect($enrollment->courseProgress)->toHaveCount(3);

        Event::assertDispatched(PathEnrollmentCreated::class);
    });

    it('enrollment creates course enrollment for first course', function () {
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

        $this->actingAs($this->learner)
            ->post(route('learner.learning-paths.enroll', $path));

        $enrollment = LearningPathEnrollment::where('user_id', $this->learner->id)->first();
        $courseProgress = $enrollment->courseProgress()->orderBy('position')->get();

        expect($courseProgress[0]->course_enrollment_id)->not->toBeNull();
        expect($courseProgress[0]->state->getValue())->toBe(AvailableCourseState::$name);

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->learner->id,
            'course_id' => $courses[0]->id,
            'status' => 'active',
        ]);

        expect($courseProgress[1]->course_enrollment_id)->toBeNull();
        expect($courseProgress[1]->state->getValue())->toBe(LockedCourseState::$name);
        expect($courseProgress[2]->course_enrollment_id)->toBeNull();
        expect($courseProgress[2]->state->getValue())->toBe(LockedCourseState::$name);
    });

    it('enrollment reuses existing course enrollment if learner already enrolled in course', function () {
        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(3)->create();

        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, [
                'position' => $i + 1,
                'is_required' => true,
            ]);
        }

        $existingEnrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $courses[0]->id,
        ]);

        $this->actingAs($this->learner)
            ->post(route('learner.learning-paths.enroll', $path));

        $enrollment = LearningPathEnrollment::where('user_id', $this->learner->id)->first();
        $courseProgress = $enrollment->courseProgress()->orderBy('position')->first();

        expect($courseProgress->course_enrollment_id)->toBe($existingEnrollment->id);

        $enrollmentCount = Enrollment::where('user_id', $this->learner->id)
            ->where('course_id', $courses[0]->id)
            ->count();
        expect($enrollmentCount)->toBe(1);
    });

    it('cannot enroll in unpublished learning path', function () {
        $path = LearningPath::factory()->unpublished()->create();

        $response = $this->actingAs($this->learner)
            ->post(route('learner.learning-paths.enroll', $path));

        $response->assertRedirect(route('learner.learning-paths.browse'));
        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('learning_path_enrollments', [
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);
    });

    it('cannot enroll when already enrolled', function () {
        $path = LearningPath::factory()->published()->create();

        LearningPathEnrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        $response = $this->actingAs($this->learner)
            ->post(route('learner.learning-paths.enroll', $path));

        $response->assertRedirect();
        $response->assertSessionHas('warning');

        $count = LearningPathEnrollment::where('user_id', $this->learner->id)
            ->where('learning_path_id', $path->id)
            ->count();
        expect($count)->toBe(1);
    });

    it('cannot enroll when already completed the path', function () {
        $path = LearningPath::factory()->published()->create();

        LearningPathEnrollment::factory()->completed()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        $response = $this->actingAs($this->learner)
            ->post(route('learner.learning-paths.enroll', $path));

        $response->assertRedirect();
        $response->assertSessionHas('warning');
    });
});

describe('Enrollment with Different Path Configurations', function () {
    it('enrolls in path with mixed required and optional courses', function () {
        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->published()->count(4)->create();

        $path->courses()->attach($courses[0]->id, ['position' => 1, 'is_required' => true]);
        $path->courses()->attach($courses[1]->id, ['position' => 2, 'is_required' => true]);
        $path->courses()->attach($courses[2]->id, ['position' => 3, 'is_required' => false]);
        $path->courses()->attach($courses[3]->id, ['position' => 4, 'is_required' => false]);

        $this->actingAs($this->learner)
            ->post(route('learner.learning-paths.enroll', $path));

        $enrollment = LearningPathEnrollment::where('user_id', $this->learner->id)->first();

        expect($enrollment->courseProgress)->toHaveCount(4);
        expect($enrollment->progress_percentage)->toBe(0);
    });

    it('can enroll in path with no courses', function () {
        $path = LearningPath::factory()->published()->create();

        $response = $this->actingAs($this->learner)
            ->post(route('learner.learning-paths.enroll', $path));

        $response->assertRedirect();

        $enrollment = LearningPathEnrollment::where('user_id', $this->learner->id)->first();
        expect($enrollment)->not->toBeNull();
        expect($enrollment->courseProgress)->toHaveCount(0);
    });

    it('can enroll in path with single course', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();
        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        $this->actingAs($this->learner)
            ->post(route('learner.learning-paths.enroll', $path));

        $enrollment = LearningPathEnrollment::where('user_id', $this->learner->id)->first();

        expect($enrollment->courseProgress)->toHaveCount(1);

        $progress = $enrollment->courseProgress()->first();
        expect($progress->state->getValue())->toBe(AvailableCourseState::$name);
    });
});

describe('Drop Enrollment', function () {
    it('can drop from learning path', function () {
        Event::fake([PathDropped::class]);

        $path = LearningPath::factory()->published()->create();
        $enrollment = LearningPathEnrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        $response = $this->actingAs($this->learner)
            ->delete(route('learner.learning-paths.drop', $path), [
                'reason' => 'Tidak sesuai ekspektasi',
            ]);

        $response->assertRedirect(route('learner.learning-paths.index'));
        $response->assertSessionHas('success');

        $enrollment->refresh();
        expect($enrollment->state->getValue())->toBe('dropped');
        expect($enrollment->drop_reason)->toBe('Tidak sesuai ekspektasi');
        expect($enrollment->dropped_at)->not->toBeNull();

        Event::assertDispatched(PathDropped::class);
    });

    it('cannot drop when not enrolled', function () {
        $path = LearningPath::factory()->published()->create();

        $response = $this->actingAs($this->learner)
            ->delete(route('learner.learning-paths.drop', $path));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    });

    it('cannot drop completed enrollment', function () {
        $path = LearningPath::factory()->published()->create();
        $enrollment = LearningPathEnrollment::factory()->completed()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
        ]);

        $response = $this->actingAs($this->learner)
            ->delete(route('learner.learning-paths.drop', $path));

        // Controller returns 403 when user can't drop (completed enrollment)
        $response->assertForbidden();

        $enrollment->refresh();
        expect($enrollment->state->getValue())->toBe('completed');
    });

    it('cannot drop another users enrollment', function () {
        $learner2 = User::factory()->create(['role' => 'learner']);

        $path = LearningPath::factory()->published()->create();
        $enrollment = LearningPathEnrollment::factory()->active()->create([
            'user_id' => $learner2->id,
            'learning_path_id' => $path->id,
        ]);

        $response = $this->actingAs($this->learner)
            ->delete(route('learner.learning-paths.drop', $path));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $enrollment->refresh();
        expect($enrollment->state->getValue())->toBe('active');
    });
});

describe('My Learning Paths List', function () {
    it('can view list of enrolled learning paths', function () {
        $enrollments = [];
        for ($i = 1; $i <= 3; $i++) {
            $path = LearningPath::factory()->published()->create([
                'title' => "Path $i",
            ]);
            $enrollments[] = LearningPathEnrollment::factory()->active()->create([
                'user_id' => $this->learner->id,
                'learning_path_id' => $path->id,
                'progress_percentage' => $i * 20,
            ]);
        }

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('learner/learning-paths/Index')
            ->has('enrollments.data', 3)
        );
    });

    it('can filter enrolled paths by status', function () {
        LearningPathEnrollment::factory()->active()->count(2)->create([
            'user_id' => $this->learner->id,
        ]);
        LearningPathEnrollment::factory()->completed()->create([
            'user_id' => $this->learner->id,
        ]);
        LearningPathEnrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
        ]);

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.index', ['status' => 'active']));

        $response->assertInertia(fn ($page) => $page
            ->has('enrollments.data', 2)
        );

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.index', ['status' => 'completed']));

        $response->assertInertia(fn ($page) => $page
            ->has('enrollments.data', 1)
        );
    });

    it('only shows current users enrollments', function () {
        $learner2 = User::factory()->create(['role' => 'learner']);

        LearningPathEnrollment::factory()->active()->count(3)->create([
            'user_id' => $this->learner->id,
        ]);
        LearningPathEnrollment::factory()->active()->count(2)->create([
            'user_id' => $learner2->id,
        ]);

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('enrollments.data', 3)
        );
    });
});

describe('View Progress Page', function () {
    it('can view detailed progress page', function () {
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
            'progress_percentage' => 33,
        ]);

        foreach ($courses as $i => $course) {
            $enrollment->courseProgress()->create([
                'course_id' => $course->id,
                'state' => $i === 0 ? CompletedCourseState::$name : ($i === 1 ? AvailableCourseState::$name : LockedCourseState::$name),
                'position' => $i + 1,
            ]);
        }

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.progress', $path));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('learner/learning-paths/Progress')
            ->has('progress.courses', 3)
        );
    });

    it('cannot view progress when not enrolled', function () {
        $path = LearningPath::factory()->published()->create();

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.progress', $path));

        $response->assertRedirect(route('learner.learning-paths.show', $path));
        $response->assertSessionHas('error');
    });

    it('can view progress of completed enrollment', function () {
        $path = LearningPath::factory()->published()->create();
        $enrollment = LearningPathEnrollment::factory()->completed()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $path->id,
            'progress_percentage' => 100,
        ]);

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.progress', $path));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('enrollment.state', 'completed')
        );
    });

    it('cannot view another users progress', function () {
        $learner2 = User::factory()->create(['role' => 'learner']);

        $path = LearningPath::factory()->published()->create();
        LearningPathEnrollment::factory()->active()->create([
            'user_id' => $learner2->id,
            'learning_path_id' => $path->id,
        ]);

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.progress', $path));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    });
});
