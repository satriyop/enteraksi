<?php

namespace Tests;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable Vite to avoid manifest errors in tests
        $this->withoutVite();
    }

    /**
     * Create a user enrolled in a published course.
     *
     * @return array{0: User, 1: Course, 2: Enrollment}
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
     * Create a user with a specific role.
     */
    protected function createUserWithRole(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /**
     * Assert that a domain event was logged.
     *
     * @param  array<string, mixed>  $metadata
     */
    protected function assertEventLogged(string $eventName, array $metadata = []): void
    {
        $query = DB::table('domain_event_log')
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
     * Assert that a state transition was logged (for spatie/laravel-model-states).
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

    /**
     * Assert that a model has a specific state.
     */
    protected function assertModelState(object $model, string $expectedState): void
    {
        $actualState = $model->fresh()->status;

        // Handle both string and State object
        $actualStateName = is_string($actualState) ? $actualState : class_basename($actualState);

        $this->assertEquals(
            $expectedState,
            $actualStateName,
            "Expected model state to be '{$expectedState}', got '{$actualStateName}'."
        );
    }

    /**
     * Create a course with content (sections and lessons).
     */
    protected function createCourseWithContent(int $sectionCount = 1, int $lessonsPerSection = 3): Course
    {
        $course = Course::factory()->create();

        for ($i = 0; $i < $sectionCount; $i++) {
            $section = \App\Models\CourseSection::factory()->create([
                'course_id' => $course->id,
                'order' => $i + 1,
            ]);

            \App\Models\Lesson::factory()->count($lessonsPerSection)->create([
                'course_section_id' => $section->id,
            ]);
        }

        return $course;
    }
}
