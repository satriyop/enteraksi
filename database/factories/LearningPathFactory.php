<?php
namespace Database\Factories;

use App\Models\LearningPath;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LearningPath>
 */
class LearningPathFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LearningPath::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titles = [
            'Jalur Pengembangan Web Full Stack',
            'Jalur Dasar Ilmu Data',
            'Jalur Pengembangan Aplikasi Mobile',
            'Jalur Penguasaan Pemasaran Digital',
            'Jalur Desain UI/UX Lengkap',
            'Jalur Karir Teknik DevOps',
            'Jalur Teknik Pembelajaran Mesin',
            'Jalur Profesional Manajemen Proyek',
            'Jalur Dasar Keamanan Siber',
            'Jalur Arsitektur Komputasi Awan',
            'Jalur Profesional Analisis Bisnis',
            'Jalur Karir Pengujian Perangkat Lunak QA',
        ];

        $title = fake()->unique()->randomElement($titles);

        return [
            'title'              => $title,
            'description'        => fake('id_ID')->paragraphs(3, true),
            'objectives'         => [
                fake('id_ID')->sentence(),
                fake('id_ID')->sentence(),
                fake('id_ID')->sentence(),
                fake('id_ID')->sentence(),
            ],
            'slug'               => Str::slug($title) . '-' . Str::random(6),
            'created_by'         => User::factory(),
            'updated_by'         => User::factory(),
            'is_published'       => false,
            'published_at'       => null,
            'estimated_duration' => fake()->numberBetween(120, 600),
            'difficulty_level'   => fake()->randomElement(['beginner', 'intermediate', 'advanced', 'expert']),
            'thumbnail_url'      => fake()->imageUrl(640, 480, 'education', true),
        ];
    }

    /**
     * Indicate that the learning path is published.
     */
    public function published(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the learning path is unpublished.
     */
    public function unpublished(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the learning path is beginner level.
     */
    public function beginner(): static
    {
        return $this->state(fn(array $attributes) => [
            'difficulty_level'   => 'beginner',
            'estimated_duration' => fake()->numberBetween(60, 180),
        ]);
    }

    /**
     * Indicate that the learning path is intermediate level.
     */
    public function intermediate(): static
    {
        return $this->state(fn(array $attributes) => [
            'difficulty_level'   => 'intermediate',
            'estimated_duration' => fake()->numberBetween(180, 360),
        ]);
    }

    /**
     * Indicate that the learning path is advanced level.
     */
    public function advanced(): static
    {
        return $this->state(fn(array $attributes) => [
            'difficulty_level'   => 'advanced',
            'estimated_duration' => fake()->numberBetween(240, 480),
        ]);
    }

    /**
     * Indicate that the learning path is expert level.
     */
    public function expert(): static
    {
        return $this->state(fn(array $attributes) => [
            'difficulty_level'   => 'expert',
            'estimated_duration' => fake()->numberBetween(300, 600),
        ]);
    }

    /**
     * Indicate that the learning path has a short duration.
     */
    public function short(): static
    {
        return $this->state(fn(array $attributes) => [
            'estimated_duration' => fake()->numberBetween(60, 120),
        ]);
    }

    /**
     * Indicate that the learning path has a long duration.
     */
    public function long(): static
    {
        return $this->state(fn(array $attributes) => [
            'estimated_duration' => fake()->numberBetween(480, 720),
        ]);
    }
}