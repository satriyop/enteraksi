<?php

namespace Database\Factories;

use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Media>
 */
class MediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mediable_type' => Lesson::class,
            'mediable_id' => Lesson::factory(),
            'collection_name' => 'video',
            'name' => fake()->word(),
            'file_name' => fake()->word().'.mp4',
            'mime_type' => 'video/mp4',
            'disk' => 'public',
            'path' => 'lessons/'.fake()->randomNumber(5).'/video/'.fake()->uuid().'.mp4',
            'size' => fake()->numberBetween(1024, 10485760),
            'duration_seconds' => fake()->numberBetween(60, 3600),
            'custom_properties' => [],
            'order_column' => 0,
        ];
    }

    /**
     * Configure the media as a video.
     */
    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection_name' => 'video',
            'file_name' => fake()->word().'.mp4',
            'mime_type' => 'video/mp4',
            'path' => 'lessons/'.fake()->randomNumber(5).'/video/'.fake()->uuid().'.mp4',
        ]);
    }

    /**
     * Configure the media as audio.
     */
    public function audio(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection_name' => 'audio',
            'file_name' => fake()->word().'.mp3',
            'mime_type' => 'audio/mpeg',
            'path' => 'lessons/'.fake()->randomNumber(5).'/audio/'.fake()->uuid().'.mp3',
        ]);
    }

    /**
     * Configure the media as a document.
     */
    public function document(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection_name' => 'document',
            'file_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'path' => 'lessons/'.fake()->randomNumber(5).'/document/'.fake()->uuid().'.pdf',
            'duration_seconds' => null,
        ]);
    }

    /**
     * Configure the media as a thumbnail.
     */
    public function thumbnail(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection_name' => 'thumbnail',
            'file_name' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'path' => 'lessons/'.fake()->randomNumber(5).'/thumbnail/'.fake()->uuid().'.jpg',
            'duration_seconds' => null,
        ]);
    }
}
