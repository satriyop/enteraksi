<?php
namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition(): array
    {
        return [
            'course_id'            => Course::factory(),
            'user_id'              => User::factory(),
            'title'                => $this->faker->sentence(4),
            'slug'                 => $this->faker->unique()->slug . '-' . $this->faker->randomNumber(6),
            'description'          => $this->faker->paragraph,
            'instructions'         => $this->faker->paragraphs(3, true),
            'time_limit_minutes'   => $this->faker->randomElement([null, 30, 60, 90]),
            'passing_score'        => $this->faker->numberBetween(60, 90),
            'max_attempts'         => $this->faker->numberBetween(1, 5),
            'shuffle_questions'    => $this->faker->boolean,
            'show_correct_answers' => $this->faker->boolean,
            'allow_review'         => $this->faker->boolean,
            'status'               => 'draft',
            'visibility'           => 'public',
            'published_at'         => null,
            'published_by'         => null,
        ];
    }

    public function published(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status'       => 'published',
                'published_at' => now(),
                'published_by' => User::factory(),
            ];
        });
    }

    public function archived(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'archived',
            ];
        });
    }

    public function restricted(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'visibility' => 'restricted',
            ];
        });
    }

    public function hidden(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'visibility' => 'hidden',
            ];
        });
    }

    public function draft(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status'       => 'draft',
                'published_at' => null,
                'published_by' => null,
            ];
        });
    }
}