<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LessonProgress>
 */
class LessonProgressFactory extends Factory
{
    protected $model = LessonProgress::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'lesson_id' => Lesson::factory(),
            'current_page' => 1,
            'total_pages' => null,
            'highest_page_reached' => 1,
            'time_spent_seconds' => 0,
            'is_completed' => false,
            'last_viewed_at' => now(),
        ];
    }

    /**
     * Mark the progress as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }

    /**
     * Set up for pagination-based content.
     */
    public function withPagination(int $currentPage, int $totalPages): static
    {
        return $this->state(fn (array $attributes) => [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'highest_page_reached' => $currentPage,
        ]);
    }

    /**
     * Set up for media-based content.
     */
    public function withMedia(int $position, int $duration): static
    {
        $percentage = $duration > 0 ? round(($position / $duration) * 100, 2) : 0;

        return $this->state(fn (array $attributes) => [
            'media_position_seconds' => $position,
            'media_duration_seconds' => $duration,
            'media_progress_percentage' => min(100, $percentage),
        ]);
    }
}
