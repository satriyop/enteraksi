<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_loads_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
            ->has('canRegister')
            ->has('featuredCourses')
            ->has('popularCourses')
            ->has('categories')
            ->has('stats')
        );
    }

    public function test_homepage_displays_published_courses(): void
    {
        $instructor = User::factory()->create();
        $category = Category::factory()->create();

        $publishedCourse = Course::factory()->create([
            'user_id' => $instructor->id,
            'category_id' => $category->id,
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $draftCourse = Course::factory()->create([
            'user_id' => $instructor->id,
            'category_id' => $category->id,
            'status' => 'draft',
            'visibility' => 'public',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
            ->where('featuredCourses.0.id', $publishedCourse->id)
        );
    }

    public function test_homepage_displays_categories(): void
    {
        $categories = Category::factory()->count(3)->create();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
            ->has('categories', 3)
        );
    }

    public function test_homepage_displays_stats(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
            ->has('stats', 4)
            ->where('stats.0.icon', 'courses')
        );
    }

    public function test_homepage_hides_hidden_courses(): void
    {
        $instructor = User::factory()->create();

        $visibleCourse = Course::factory()->create([
            'user_id' => $instructor->id,
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $hiddenCourse = Course::factory()->create([
            'user_id' => $instructor->id,
            'status' => 'published',
            'visibility' => 'hidden',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
            ->has('featuredCourses', 1)
        );
    }
}
