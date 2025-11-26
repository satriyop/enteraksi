<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake('id_ID')->unique()->randomElement([
            'Teknologi Informasi',
            'Bisnis & Manajemen',
            'Bahasa',
            'Desain & Multimedia',
            'Keuangan & Akuntansi',
            'Soft Skills',
            'Marketing Digital',
            'Pengembangan Diri',
            'Data Science',
            'Keamanan Siber',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake('id_ID')->sentence(),
            'parent_id' => null,
            'order' => fake()->numberBetween(1, 10),
        ];
    }
}
