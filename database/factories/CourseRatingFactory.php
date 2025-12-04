<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourseRating>
 */
class CourseRatingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reviews = [
            'Kursus yang sangat bagus dan mudah dipahami.',
            'Materinya lengkap dan instrukturnya menjelaskan dengan baik.',
            'Sangat membantu untuk memahami konsep dasar.',
            'Cocok untuk pemula yang ingin belajar.',
            'Penjelasannya detail dan contoh-contohnya relevan.',
            'Saya sangat puas dengan kursus ini.',
            'Materi yang diberikan sesuai dengan ekspektasi.',
            'Bagus, tapi bisa ditambah lebih banyak latihan.',
            'Kursus ini membantu saya meningkatkan kemampuan.',
            'Recommended untuk yang ingin belajar topik ini.',
            null,
            null,
            null,
        ];

        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'review' => fake()->randomElement($reviews),
        ];
    }

    /**
     * Create a rating with a specific star value.
     */
    public function withRating(int $rating): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => $rating,
        ]);
    }

    /**
     * Create a rating with a review.
     */
    public function withReview(?string $review = null): static
    {
        return $this->state(fn (array $attributes) => [
            'review' => $review ?? fake('id_ID')->paragraph(),
        ]);
    }

    /**
     * Create a rating without a review.
     */
    public function withoutReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'review' => null,
        ]);
    }
}
