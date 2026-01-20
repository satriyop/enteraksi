<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentAttemptFactory extends Factory
{
    protected $model = AssessmentAttempt::class;

    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'user_id' => User::factory(),
            'attempt_number' => 1,
            'status' => 'in_progress',
            'score' => null,
            'max_score' => null,
            'percentage' => null,
            'passed' => null,
            'started_at' => now(),
            'submitted_at' => null,
            'graded_at' => null,
            'graded_by' => null,
            'feedback' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'in_progress',
                'started_at' => now(),
                'submitted_at' => null,
            ];
        });
    }

    public function submitted(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'submitted',
                'submitted_at' => now(),
            ];
        });
    }

    public function graded(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'graded',
                'score' => $this->faker->numberBetween(50, 100),
                'max_score' => 100,
                'percentage' => $this->faker->numberBetween(50, 100),
                'passed' => $this->faker->boolean(70), // 70% chance of passing
                'submitted_at' => now(),
                'graded_at' => now(),
                'graded_by' => User::factory(),
                'feedback' => $this->faker->optional()->paragraph,
            ];
        });
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'completed',
                'score' => $this->faker->numberBetween(50, 100),
                'max_score' => 100,
                'percentage' => $this->faker->numberBetween(50, 100),
                'passed' => $this->faker->boolean(70),
                'submitted_at' => now(),
                'graded_at' => now(),
                'graded_by' => User::factory(),
                'feedback' => $this->faker->optional()->paragraph,
            ];
        });
    }

    public function passed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'graded',
                'score' => 85,
                'max_score' => 100,
                'percentage' => 85,
                'passed' => true,
                'submitted_at' => now(),
                'graded_at' => now(),
            ];
        });
    }

    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'graded',
                'score' => 40,
                'max_score' => 100,
                'percentage' => 40,
                'passed' => false,
                'submitted_at' => now(),
                'graded_at' => now(),
            ];
        });
    }
}
