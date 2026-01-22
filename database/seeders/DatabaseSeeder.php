<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create default users with different roles
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'learner',
        ]);

        User::factory()->create([
            'name' => 'Content Manager',
            'email' => 'content@example.com',
            'role' => 'content_manager',
        ]);

        User::factory()->create([
            'name' => 'Trainer',
            'email' => 'trainer@example.com',
            'role' => 'trainer',
        ]);

        User::factory()->create([
            'name' => 'LMS Admin',
            'email' => 'admin@example.com',
            'role' => 'lms_admin',
        ]);

        $this->call([
            CategorySeeder::class,
            TagSeeder::class,
            CourseSeeder::class,
            BankingCourseSeeder::class,
            LearningPathSeeder::class,
        ]);
    }
}
