<?php
namespace Database\Factories;

use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttemptAnswerFactory extends Factory
{
    protected $model = AttemptAnswer::class;

    public function definition(): array
    {
        return [
            'attempt_id'  => AssessmentAttempt::factory(),
            'question_id' => Question::factory(),
            'answer_text' => $this->faker->sentence,
            'file_path'   => null,
            'is_correct'  => null,
            'score'       => null,
            'feedback'    => null,
            'graded_by'   => null,
            'graded_at'   => null,
        ];
    }

    public function gradedCorrect(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_correct' => true,
                'score'      => $this->faker->numberBetween(1, 5),
                'feedback'   => $this->faker->optional()->paragraph,
                'graded_by'  => User::factory(),
                'graded_at'  => now(),
            ];
        });
    }

    public function gradedIncorrect(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_correct' => false,
                'score'      => 0,
                'feedback'   => $this->faker->optional()->paragraph,
                'graded_by'  => User::factory(),
                'graded_at'  => now(),
            ];
        });
    }

    public function fileUpload(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'answer_text' => null,
                'file_path'   => 'assessment_answers/' . $this->faker->uuid . '.pdf',
            ];
        });
    }
}