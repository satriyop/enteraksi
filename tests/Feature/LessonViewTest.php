<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_lessons(): void
    {
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

        $response = $this->get("/courses/{$course->id}/lessons/{$lesson->id}");

        $response->assertRedirect('/login');
    }

    public function test_non_enrolled_users_cannot_view_lessons(): void
    {
        $user = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

        $response = $this->actingAs($user)->get("/courses/{$course->id}/lessons/{$lesson->id}");

        $response->assertForbidden();
    }

    public function test_enrolled_learners_can_view_lessons(): void
    {
        $user = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create([
            'course_section_id' => $section->id,
            'content_type' => 'youtube',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get("/courses/{$course->id}/lessons/{$lesson->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('lessons/Show')
            ->has('course')
            ->has('lesson')
            ->has('enrollment')
            ->where('lesson.id', $lesson->id)
        );
    }

    public function test_course_owner_can_view_any_lesson(): void
    {
        $owner = User::factory()->create(['role' => 'content_manager']);
        $course = Course::factory()->draft()->create(['user_id' => $owner->id]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

        $response = $this->actingAs($owner)->get("/courses/{$course->id}/lessons/{$lesson->id}");

        $response->assertOk();
    }

    public function test_lms_admin_can_view_any_lesson(): void
    {
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $owner = User::factory()->create(['role' => 'content_manager']);
        $course = Course::factory()->draft()->create(['user_id' => $owner->id]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

        $response = $this->actingAs($admin)->get("/courses/{$course->id}/lessons/{$lesson->id}");

        $response->assertOk();
    }

    public function test_lesson_must_belong_to_course(): void
    {
        $user = User::factory()->create(['role' => 'learner']);
        $course1 = Course::factory()->published()->create();
        $course2 = Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course1->id]);
        $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course2->id,
            'status' => 'active',
        ]);

        // Try to access lesson from course1 using course2's URL
        $response = $this->actingAs($user)->get("/courses/{$course2->id}/lessons/{$lesson->id}");

        $response->assertNotFound();
    }

    public function test_dropped_enrollment_cannot_view_lessons(): void
    {
        $user = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'dropped',
        ]);

        $response = $this->actingAs($user)->get("/courses/{$course->id}/lessons/{$lesson->id}");

        $response->assertForbidden();
    }

    public function test_lesson_view_returns_navigation_data(): void
    {
        $user = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        $lesson1 = Lesson::factory()->create([
            'course_section_id' => $section->id,
            'order' => 1,
            'title' => 'First Lesson',
        ]);
        $lesson2 = Lesson::factory()->create([
            'course_section_id' => $section->id,
            'order' => 2,
            'title' => 'Second Lesson',
        ]);
        $lesson3 = Lesson::factory()->create([
            'course_section_id' => $section->id,
            'order' => 3,
            'title' => 'Third Lesson',
        ]);

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get("/courses/{$course->id}/lessons/{$lesson2->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('lessons/Show')
            ->has('prevLesson')
            ->has('nextLesson')
            ->has('allLessons', 3)
            ->where('prevLesson.id', $lesson1->id)
            ->where('nextLesson.id', $lesson3->id)
        );
    }

    public function test_first_lesson_has_no_previous(): void
    {
        $user = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        $lesson1 = Lesson::factory()->create([
            'course_section_id' => $section->id,
            'order' => 1,
        ]);
        $lesson2 = Lesson::factory()->create([
            'course_section_id' => $section->id,
            'order' => 2,
        ]);

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get("/courses/{$course->id}/lessons/{$lesson1->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('prevLesson', null)
            ->has('nextLesson')
        );
    }

    public function test_last_lesson_has_no_next(): void
    {
        $user = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        $lesson1 = Lesson::factory()->create([
            'course_section_id' => $section->id,
            'order' => 1,
        ]);
        $lesson2 = Lesson::factory()->create([
            'course_section_id' => $section->id,
            'order' => 2,
        ]);

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get("/courses/{$course->id}/lessons/{$lesson2->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('prevLesson')
            ->where('nextLesson', null)
        );
    }
}
