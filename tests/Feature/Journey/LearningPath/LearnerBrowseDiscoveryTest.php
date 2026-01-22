<?php

/**
 * Learner Browse & Discovery Test
 *
 * Tests covering the learner's journey to discover and browse available
 * learning paths. These are the first touchpoints before enrollment.
 *
 * From the test plan: plans/tests/journey/learning-path/01-learner-browse-discovery.md
 */

use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;

beforeEach(function () {
    $this->learner = User::factory()->create(['role' => 'learner']);
});

describe('Browse Page Access', function () {
    it('authenticated learner can access browse page', function () {
        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.browse'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('learner/learning-paths/Browse')
        );
    });

    it('unauthenticated user is redirected to login', function () {
        $response = $this->get(route('learner.learning-paths.browse'));

        $response->assertRedirect(route('login'));
    });

    it('admin and content manager can also access browse page', function () {
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $cm = User::factory()->create(['role' => 'content_manager']);

        $this->actingAs($admin)
            ->get(route('learner.learning-paths.browse'))
            ->assertOk();

        $this->actingAs($cm)
            ->get(route('learner.learning-paths.browse'))
            ->assertOk();
    });
});

describe('Learning Path Listing', function () {
    it('only published learning paths are displayed', function () {
        LearningPath::factory()->published()->count(3)->create();
        LearningPath::factory()->unpublished()->count(2)->create();

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.browse'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('learningPaths.data', 3)
        );
    });

    it('learning paths show correct metadata', function () {
        $creator = User::factory()->create(['role' => 'content_manager']);

        $path = LearningPath::factory()->published()->create([
            'title' => 'Jalur Keamanan Siber',
            'description' => 'Pelajari dasar-dasar keamanan siber',
            'created_by' => $creator->id,
            'difficulty_level' => 'beginner',
            'estimated_duration' => 120,
        ]);

        $courses = Course::factory()->published()->count(3)->create();
        foreach ($courses as $i => $course) {
            $path->courses()->attach($course->id, ['position' => $i + 1]);
        }

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.browse'));

        $response->assertInertia(fn ($page) => $page
            ->has('learningPaths.data.0', fn ($item) => $item
                ->where('title', 'Jalur Keamanan Siber')
                ->where('difficulty_level', 'beginner')
                ->where('estimated_duration', 120)
                ->where('courses_count', 3)
                ->has('creator', fn ($c) => $c
                    ->where('name', $creator->name)
                    ->etc()
                )
                ->etc()
            )
        );
    });

    it('already enrolled paths are marked', function () {
        $enrolledPath = LearningPath::factory()->published()->create();
        $notEnrolledPath = LearningPath::factory()->published()->create();

        LearningPathEnrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $enrolledPath->id,
        ]);

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.browse'));

        $response->assertInertia(fn ($page) => $page
            ->has('enrolledPathIds', 1)
            ->where('enrolledPathIds.0', $enrolledPath->id)
        );
    });
});

describe('Search Functionality', function () {
    it('search by title returns matching results', function () {
        LearningPath::factory()->published()->create([
            'title' => 'Jalur Keamanan Siber',
        ]);
        LearningPath::factory()->published()->create([
            'title' => 'Jalur Pengembangan Web',
        ]);
        LearningPath::factory()->published()->create([
            'title' => 'Jalur Data Science',
        ]);

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.browse', ['search' => 'Keamanan']));

        $response->assertInertia(fn ($page) => $page
            ->has('learningPaths.data', 1)
            ->where('learningPaths.data.0.title', 'Jalur Keamanan Siber')
        );
    });

    it('search by description returns matching results', function () {
        LearningPath::factory()->published()->create([
            'title' => 'Path A',
            'description' => 'Belajar tentang kriptografi dan enkripsi data.',
        ]);
        LearningPath::factory()->published()->create([
            'title' => 'Path B',
            'description' => 'Belajar tentang web development.',
        ]);

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.browse', ['search' => 'kriptografi']));

        $response->assertInertia(fn ($page) => $page
            ->has('learningPaths.data', 1)
            ->where('learningPaths.data.0.title', 'Path A')
        );
    });

    it('search is case-insensitive', function () {
        LearningPath::factory()->published()->create([
            'title' => 'Jalur KEAMANAN Siber',
        ]);

        // Search with lowercase
        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.browse', ['search' => 'keamanan']));

        $response->assertInertia(fn ($page) => $page
            ->has('learningPaths.data', 1)
        );

        // Search with uppercase
        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.browse', ['search' => 'KEAMANAN']));

        $response->assertInertia(fn ($page) => $page
            ->has('learningPaths.data', 1)
        );
    });

    it('empty search returns all published paths', function () {
        LearningPath::factory()->published()->count(5)->create();

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.browse', ['search' => '']));

        $response->assertInertia(fn ($page) => $page
            ->has('learningPaths.data', 5)
        );
    });

    it('search with no results shows empty state', function () {
        LearningPath::factory()->published()->count(3)->create();

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.browse', [
                'search' => 'tidak ada hasil xyz123',
            ]));

        $response->assertInertia(fn ($page) => $page
            ->has('learningPaths.data', 0)
        );
    });
});

describe('Filter Functionality', function () {
    it('filter by difficulty level', function () {
        LearningPath::factory()->published()->beginner()->create();
        LearningPath::factory()->published()->intermediate()->count(2)->create();
        LearningPath::factory()->published()->advanced()->create();

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.browse', [
                'difficulty' => 'intermediate',
            ]));

        $response->assertInertia(fn ($page) => $page
            ->has('learningPaths.data', 2)
        );
    });

    it('combine search and filter', function () {
        LearningPath::factory()->published()->beginner()->create([
            'title' => 'Keamanan Dasar',
        ]);
        LearningPath::factory()->published()->advanced()->create([
            'title' => 'Keamanan Lanjutan',
        ]);
        LearningPath::factory()->published()->beginner()->create([
            'title' => 'Web Development',
        ]);

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.browse', [
                'search' => 'Keamanan',
                'difficulty' => 'beginner',
            ]));

        $response->assertInertia(fn ($page) => $page
            ->has('learningPaths.data', 1)
            ->where('learningPaths.data.0.title', 'Keamanan Dasar')
        );
    });
});

describe('Pagination', function () {
    it('results are paginated correctly', function () {
        // Create 15 paths (more than default page size of 12)
        for ($i = 1; $i <= 15; $i++) {
            LearningPath::create([
                'title' => "Path $i",
                'slug' => "path-$i",
                'description' => "Description $i",
                'is_published' => true,
                'published_at' => now(),
                'created_by' => $this->learner->id,
                'updated_by' => $this->learner->id,
                'prerequisite_mode' => 'sequential',
            ]);
        }

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.browse'));

        $response->assertInertia(fn ($page) => $page
            ->has('learningPaths.data', 12)  // First page
            ->where('learningPaths.total', 15)
            ->where('learningPaths.per_page', 12)
        );
    });

    it('can navigate to second page', function () {
        for ($i = 1; $i <= 15; $i++) {
            LearningPath::create([
                'title' => "Path $i",
                'slug' => "path-$i",
                'description' => "Description $i",
                'is_published' => true,
                'published_at' => now(),
                'created_by' => $this->learner->id,
                'updated_by' => $this->learner->id,
                'prerequisite_mode' => 'sequential',
            ]);
        }

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.browse', ['page' => 2]));

        $response->assertInertia(fn ($page) => $page
            ->has('learningPaths.data', 3)  // Remaining 3 items
            ->where('learningPaths.current_page', 2)
        );
    });
});

describe('Empty States', function () {
    it('shows empty state when no learning paths exist', function () {
        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.browse'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('learningPaths.data', 0)
        );
    });

    it('shows empty state when all paths are unpublished', function () {
        LearningPath::factory()->unpublished()->count(5)->create();

        $response = $this->actingAs($this->learner)
            ->get(route('learner.learning-paths.browse'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('learningPaths.data', 0)
        );
    });
});
