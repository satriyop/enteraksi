<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake('id_ID')->unique()->randomElement([
            'pemrograman',
            'manajemen',
            'komunikasi',
            'kepemimpinan',
            'desain',
            'keuangan',
            'pemasaran',
            'analisis',
            'produktivitas',
            'kreativitas',
            'presentasi',
            'negosiasi',
            'excel',
            'python',
            'javascript',
            'web development',
            'mobile app',
            'database',
            'cloud computing',
            'artificial intelligence',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
