<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            'Pemrograman',
            'Web Development',
            'Mobile App',
            'Database',
            'Cloud Computing',
            'Artificial Intelligence',
            'Machine Learning',
            'Data Science',
            'Cyber Security',
            'DevOps',
            'Manajemen Proyek',
            'Kepemimpinan',
            'Komunikasi',
            'Negosiasi',
            'Presentasi',
            'Excel',
            'Python',
            'JavaScript',
            'PHP',
            'Laravel',
            'Vue.js',
            'React',
            'UI/UX Design',
            'Desain Grafis',
            'Adobe Photoshop',
            'Figma',
            'Akuntansi',
            'Keuangan',
            'Investasi',
            'Pemasaran Digital',
            'SEO',
            'Social Media Marketing',
            'Content Writing',
            'Produktivitas',
            'Time Management',
            'Bahasa Inggris',
            'Bahasa Jepang',
            'TOEFL',
            'IELTS',
            'Public Speaking',
        ];

        foreach ($tags as $tagName) {
            Tag::create([
                'name' => $tagName,
                'slug' => Str::slug($tagName),
            ]);
        }
    }
}
