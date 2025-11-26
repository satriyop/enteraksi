<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Teknologi Informasi',
                'description' => 'Kursus seputar pemrograman, pengembangan software, dan teknologi digital.',
                'order' => 1,
            ],
            [
                'name' => 'Bisnis & Manajemen',
                'description' => 'Kursus untuk mengembangkan keterampilan bisnis dan manajemen.',
                'order' => 2,
            ],
            [
                'name' => 'Bahasa',
                'description' => 'Kursus pembelajaran bahasa asing untuk berbagai keperluan.',
                'order' => 3,
            ],
            [
                'name' => 'Desain & Multimedia',
                'description' => 'Kursus desain grafis, UI/UX, dan produksi multimedia.',
                'order' => 4,
            ],
            [
                'name' => 'Keuangan & Akuntansi',
                'description' => 'Kursus manajemen keuangan, akuntansi, dan investasi.',
                'order' => 5,
            ],
            [
                'name' => 'Soft Skills',
                'description' => 'Kursus pengembangan keterampilan interpersonal dan kepribadian.',
                'order' => 6,
            ],
        ];

        foreach ($categories as $category) {
            Category::create([
                'name' => $category['name'],
                'slug' => Str::slug($category['name']),
                'description' => $category['description'],
                'order' => $category['order'],
            ]);
        }
    }
}
