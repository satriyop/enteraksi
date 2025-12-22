<?php
namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        $questionTypes = ['multiple_choice', 'true_false', 'short_answer', 'essay'];
        $questionType  = $this->faker->randomElement($questionTypes);

        return [
            'assessment_id' => Assessment::factory(),
            'question_text' => $this->faker->sentence(8),
            'question_type' => $questionType,
            'points'        => $this->faker->numberBetween(1, 5),
            'feedback'      => $this->faker->optional()->paragraph,
            'order'         => 0,
        ];
    }

    public function multipleChoice(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'question_type' => 'multiple_choice',
            ];
        });
    }

    public function trueFalse(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'question_type' => 'true_false',
            ];
        });
    }

    public function shortAnswer(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'question_type' => 'short_answer',
            ];
        });
    }

    public function essay(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'question_type' => 'essay',
            ];
        });
    }

    public function matching(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'question_type' => 'matching',
            ];
        });
    }

    public function fileUpload(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'question_type' => 'file_upload',
            ];
        });
    }
}