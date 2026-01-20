<?php

namespace Database\Factories;

use App\Domain\LearningPath\States\AvailableCourseState;
use App\Domain\LearningPath\States\CompletedCourseState;
use App\Domain\LearningPath\States\InProgressCourseState;
use App\Domain\LearningPath\States\LockedCourseState;
use App\Models\Course;
use App\Models\LearningPathCourseProgress;
use App\Models\LearningPathEnrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LearningPathCourseProgress>
 */
class LearningPathCourseProgressFactory extends Factory
{
    protected $model = LearningPathCourseProgress::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_path_enrollment_id' => LearningPathEnrollment::factory(),
            'course_id' => Course::factory()->published(),
            'state' => LockedCourseState::$name,
            'position' => 1,
            'course_enrollment_id' => null,
            'unlocked_at' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the course is locked.
     */
    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => LockedCourseState::$name,
            'unlocked_at' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the course is available.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => AvailableCourseState::$name,
            'unlocked_at' => now(),
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the course is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => InProgressCourseState::$name,
            'unlocked_at' => now()->subDays(1),
            'started_at' => now(),
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the course is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => CompletedCourseState::$name,
            'unlocked_at' => now()->subDays(2),
            'started_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Set the position in the path.
     */
    public function atPosition(int $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
        ]);
    }
}
