<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titles = [
            'Pengantar Pemrograman Python',
            'Manajemen Proyek untuk Pemula',
            'Desain UI/UX Modern',
            'Analisis Data dengan Excel',
            'Bahasa Inggris Bisnis',
            'Kepemimpinan Efektif',
            'Digital Marketing Dasar',
            'Akuntansi Keuangan Dasar',
            'Pengembangan Aplikasi Web dengan Laravel',
            'Machine Learning untuk Pemula',
            'Komunikasi Bisnis Profesional',
            'Manajemen Waktu dan Produktivitas',
            'Dasar-Dasar Database SQL',
            'Pemrograman JavaScript Modern',
            'Strategi Pemasaran Digital',
        ];

        $title = fake()->unique()->randomElement($titles);

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'short_description' => fake('id_ID')->paragraph(2),
            'long_description' => fake('id_ID')->paragraphs(5, true),
            'objectives' => [
                fake('id_ID')->sentence(),
                fake('id_ID')->sentence(),
                fake('id_ID')->sentence(),
            ],
            'prerequisites' => [
                fake('id_ID')->sentence(),
            ],
            'category_id' => Category::factory(),
            'thumbnail_path' => null,
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'visibility' => fake()->randomElement(['public', 'restricted', 'hidden']),
            'difficulty_level' => fake()->randomElement(['beginner', 'intermediate', 'advanced']),
            'estimated_duration_minutes' => fake()->numberBetween(60, 600),
            'manual_duration_minutes' => null,
            'published_at' => null,
            'published_by' => null,
        ];
    }

    /**
     * Indicate that the course is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
            'published_by' => null,
        ]);
    }

    /**
     * Indicate that the course is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
            'published_by' => $attributes['user_id'],
        ]);
    }

    /**
     * Indicate that the course is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }

    /**
     * Indicate that the course is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'public',
        ]);
    }

    /**
     * Indicate that the course is restricted.
     */
    public function restricted(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'restricted',
        ]);
    }

    /**
     * Indicate that the course is beginner level.
     */
    public function beginner(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty_level' => 'beginner',
        ]);
    }

    /**
     * Indicate that the course is intermediate level.
     */
    public function intermediate(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty_level' => 'intermediate',
        ]);
    }

    /**
     * Indicate that the course is advanced level.
     */
    public function advanced(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty_level' => 'advanced',
        ]);
    }
}
