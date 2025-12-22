<?php
namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionOptionFactory extends Factory
{
    protected $model = QuestionOption::class;

    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'option_text' => $this->faker->sentence(4),
            'is_correct'  => $this->faker->boolean(20), // 20% chance of being correct
            'feedback'    => $this->faker->optional()->paragraph,
            'order'       => 0,
        ];
    }

    public function correct(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_correct' => true,
            ];
        });
    }

    public function incorrect(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_correct' => false,
            ];
        });
    }
}