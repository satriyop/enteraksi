<?php

namespace Database\Factories;

use App\Models\CourseSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titles = [
            'Apa itu ' . fake('id_ID')->word() . '?',
            'Memahami Konsep ' . fake('id_ID')->word(),
            'Praktik: ' . fake('id_ID')->words(3, true),
            'Studi Kasus: ' . fake('id_ID')->words(2, true),
            'Tips dan Trik ' . fake('id_ID')->word(),
            'Kesalahan Umum yang Harus Dihindari',
            'Ringkasan dan Kesimpulan',
            'Quiz: Uji Pemahaman Anda',
        ];

        return [
            'course_section_id' => CourseSection::factory(),
            'title' => fake()->randomElement($titles),
            'description' => fake('id_ID')->sentence(),
            'order' => fake()->numberBetween(1, 10),
            'content_type' => fake()->randomElement(['text', 'video', 'youtube']),
            'rich_content' => null,
            'youtube_url' => null,
            'conference_url' => null,
            'conference_type' => null,
            'estimated_duration_minutes' => fake()->numberBetween(5, 30),
            'is_free_preview' => fake()->boolean(20),
        ];
    }

    /**
     * Indicate that the lesson is text content.
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => 'text',
            'rich_content' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => fake('id_ID')->paragraphs(3, true)],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Indicate that the lesson is YouTube video.
     */
    public function youtube(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => 'youtube',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
    }

    /**
     * Indicate that the lesson is a conference.
     */
    public function conference(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => 'conference',
            'conference_url' => 'https://zoom.us/j/123456789',
            'conference_type' => 'zoom',
        ]);
    }

    /**
     * Indicate that the lesson is a free preview.
     */
    public function freePreview(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_free_preview' => true,
        ]);
    }
}
