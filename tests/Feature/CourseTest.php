<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_courses_index(): void
    {
        $response = $this->get('/courses');

        $response->assertRedirect('/login');
    }

    public function test_learners_cannot_access_courses_index(): void
    {
        $user = User::factory()->create(['role' => 'learner']);

        $response = $this->actingAs($user)->get('/courses');

        $response->assertForbidden();
    }

    public function test_content_managers_can_access_courses_index(): void
    {
        $user = User::factory()->create(['role' => 'content_manager']);

        $response = $this->actingAs($user)->get('/courses');

        $response->assertOk();
    }

    public function test_lms_admins_can_access_courses_index(): void
    {
        $user = User::factory()->create(['role' => 'lms_admin']);

        $response = $this->actingAs($user)->get('/courses');

        $response->assertOk();
    }

    public function test_content_managers_can_create_courses(): void
    {
        $user = User::factory()->create(['role' => 'content_manager']);
        $category = Category::factory()->create();

        $response = $this->actingAs($user)->post('/courses', [
            'title' => 'Test Course',
            'short_description' => 'This is a test course description.',
            'difficulty_level' => 'beginner',
            'visibility' => 'public',
            'category_id' => $category->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('courses', [
            'title' => 'Test Course',
            'user_id' => $user->id,
            'status' => 'draft',
        ]);
    }

    public function test_content_managers_can_update_their_own_courses(): void
    {
        $user = User::factory()->create(['role' => 'content_manager']);
        $course = Course::factory()->draft()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put("/courses/{$course->id}", [
            'title' => 'Updated Course Title',
            'short_description' => $course->short_description,
            'difficulty_level' => $course->difficulty_level,
            'visibility' => $course->visibility,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => 'Updated Course Title',
        ]);
    }

    public function test_content_managers_cannot_update_others_courses(): void
    {
        $owner = User::factory()->create(['role' => 'content_manager']);
        $other = User::factory()->create(['role' => 'content_manager']);
        $course = Course::factory()->draft()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->put("/courses/{$course->id}", [
            'title' => 'Hijacked Title',
            'short_description' => $course->short_description,
            'difficulty_level' => $course->difficulty_level,
            'visibility' => $course->visibility,
        ]);

        $response->assertForbidden();
    }

    public function test_lms_admins_can_update_any_course(): void
    {
        $owner = User::factory()->create(['role' => 'content_manager']);
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $course = Course::factory()->draft()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($admin)->put("/courses/{$course->id}", [
            'title' => 'Admin Updated Title',
            'short_description' => $course->short_description,
            'difficulty_level' => $course->difficulty_level,
            'visibility' => $course->visibility,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => 'Admin Updated Title',
        ]);
    }

    public function test_content_managers_cannot_publish_courses(): void
    {
        $user = User::factory()->create(['role' => 'content_manager']);
        $course = Course::factory()->draft()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/courses/{$course->id}/publish");

        $response->assertForbidden();
    }

    public function test_lms_admins_can_publish_courses(): void
    {
        $owner = User::factory()->create(['role' => 'content_manager']);
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $course = Course::factory()->draft()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/publish");

        $response->assertRedirect();
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'status' => 'published',
            'published_by' => $admin->id,
        ]);
    }

    public function test_lms_admins_can_unpublish_courses(): void
    {
        $owner = User::factory()->create(['role' => 'content_manager']);
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $course = Course::factory()->published()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/unpublish");

        $response->assertRedirect();
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'status' => 'draft',
        ]);
    }

    public function test_content_managers_can_delete_their_own_draft_courses(): void
    {
        $user = User::factory()->create(['role' => 'content_manager']);
        $course = Course::factory()->draft()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete("/courses/{$course->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('courses', ['id' => $course->id]);
    }

    public function test_content_managers_cannot_delete_published_courses(): void
    {
        $user = User::factory()->create(['role' => 'content_manager']);
        $course = Course::factory()->published()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete("/courses/{$course->id}");

        $response->assertForbidden();
    }

    public function test_lms_admins_can_delete_any_course(): void
    {
        $owner = User::factory()->create(['role' => 'content_manager']);
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $course = Course::factory()->published()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($admin)->delete("/courses/{$course->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('courses', ['id' => $course->id]);
    }

    public function test_course_can_have_sections_and_lessons(): void
    {
        $user = User::factory()->create(['role' => 'content_manager']);
        $course = Course::factory()->create(['user_id' => $user->id]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->text()->create(['course_section_id' => $section->id]);

        $this->assertCount(1, $course->sections);
        $this->assertCount(1, $section->lessons);
        $this->assertEquals($lesson->title, $course->sections->first()->lessons->first()->title);
    }

    public function test_course_can_have_tags(): void
    {
        $course = Course::factory()->create();
        $tags = Tag::factory()->count(3)->create();

        $course->tags()->attach($tags);

        $this->assertCount(3, $course->tags);
    }

    public function test_courses_index_filters_by_status(): void
    {
        $user = User::factory()->create(['role' => 'content_manager']);
        Course::factory()->draft()->create(['user_id' => $user->id, 'title' => 'Draft Course']);
        Course::factory()->published()->create(['user_id' => $user->id, 'title' => 'Published Course']);

        $response = $this->actingAs($user)->get('/courses?status=draft');

        $response->assertOk();
        $response->assertSee('Draft Course');
    }

    public function test_courses_index_filters_by_search(): void
    {
        $user = User::factory()->create(['role' => 'content_manager']);
        Course::factory()->create(['user_id' => $user->id, 'title' => 'Laravel Course']);
        Course::factory()->create(['user_id' => $user->id, 'title' => 'Python Course']);

        $response = $this->actingAs($user)->get('/courses?search=Laravel');

        $response->assertOk();
        $response->assertSee('Laravel Course');
    }
}
