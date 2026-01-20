<?php

namespace Database\Factories;

use App\Domain\LearningPath\States\ActivePathState;
use App\Domain\LearningPath\States\CompletedPathState;
use App\Domain\LearningPath\States\DroppedPathState;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LearningPathEnrollment>
 */
class LearningPathEnrollmentFactory extends Factory
{
    protected $model = LearningPathEnrollment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'learning_path_id' => LearningPath::factory()->published(),
            'state' => ActivePathState::$name,
            'progress_percentage' => 0,
            'enrolled_at' => now(),
            'completed_at' => null,
            'dropped_at' => null,
            'drop_reason' => null,
        ];
    }

    /**
     * Indicate that the enrollment is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => ActivePathState::$name,
            'completed_at' => null,
            'dropped_at' => null,
        ]);
    }

    /**
     * Indicate that the enrollment is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => CompletedPathState::$name,
            'progress_percentage' => 100,
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the enrollment is dropped.
     */
    public function dropped(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => DroppedPathState::$name,
            'dropped_at' => now(),
            'drop_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Set progress percentage.
     */
    public function withProgress(int $percentage): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_percentage' => $percentage,
        ]);
    }
}
