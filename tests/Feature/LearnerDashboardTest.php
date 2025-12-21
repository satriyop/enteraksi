<?php
namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'learner']);
    }

    public function test_learner_dashboard_shows_my_learning_section(): void
    {
        // Create a course and enroll the user
        $course     = Course::factory()->published()->create();
        $enrollment = Enrollment::factory()->create([
            'user_id'             => $this->user->id,
            'course_id'           => $course->id,
            'status'              => 'active',
            'progress_percentage' => 50,
        ]);

        $response = $this->actingAs($this->user)->get('/learner/dashboard');

        $response->assertOk();
        $response->assertInertia(fn($page) => $page
                ->component('learner/Dashboard')
                ->has('myLearning', 1)
                ->where('myLearning.0.course_id', $course->id)
                ->where('myLearning.0.progress_percentage', 50)
        );
    }

    public function test_my_learning_shows_correct_progress_data(): void
    {
        $course = Course::factory()->published()->create([
            'title'                      => 'Advanced JavaScript',
            'difficulty_level'           => 'advanced',
            'estimated_duration_minutes' => 180,
        ]);

        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson  = Lesson::factory()->create([
            'course_section_id' => $section->id,
            'title'             => 'JavaScript Patterns',
        ]);

        $enrollment = Enrollment::factory()->create([
            'user_id'             => $this->user->id,
            'course_id'           => $course->id,
            'status'              => 'active',
            'progress_percentage' => 75,
            'last_lesson_id'      => $lesson->id,
        ]);

        $response = $this->actingAs($this->user)->get('/learner/dashboard');

        $response->assertOk();
        $response->assertInertia(fn($page) => $page
                ->component('learner/Dashboard')
                ->has('myLearning', 1)
                ->where('myLearning.0.title', 'Advanced JavaScript')
                ->where('myLearning.0.progress_percentage', 75)
                ->where('myLearning.0.last_lesson_id', $lesson->id)
                ->where('myLearning.0.duration', 180)
                ->where('myLearning.0.difficulty_level', 'advanced')
        );
    }

    public function test_my_learning_shows_multiple_courses_ordered_by_recent_activity(): void
    {
        $course1 = Course::factory()->published()->create();
        $course2 = Course::factory()->published()->create();
        $course3 = Course::factory()->published()->create();

        // Create enrollments with different update times
        $enrollment1 = Enrollment::factory()->create([
            'user_id'             => $this->user->id,
            'course_id'           => $course1->id,
            'status'              => 'active',
            'progress_percentage' => 30,
            'updated_at'          => now()->subDays(2),
        ]);

        $enrollment2 = Enrollment::factory()->create([
            'user_id'             => $this->user->id,
            'course_id'           => $course2->id,
            'status'              => 'active',
            'progress_percentage' => 60,
            'updated_at'          => now(), // Most recent
        ]);

        $enrollment3 = Enrollment::factory()->create([
            'user_id'             => $this->user->id,
            'course_id'           => $course3->id,
            'status'              => 'active',
            'progress_percentage' => 90,
            'updated_at'          => now()->subDay(),
        ]);

        $response = $this->actingAs($this->user)->get('/learner/dashboard');

        $response->assertOk();
        $response->assertInertia(fn($page) => $page
                ->component('learner/Dashboard')
                ->has('myLearning', 3)
                ->where('myLearning.0.course_id', $course2->id) // Most recent should be first
                ->where('myLearning.1.course_id', $course3->id)
                ->where('myLearning.2.course_id', $course1->id)
        );
    }

    public function test_my_learning_section_not_shown_when_no_enrollments(): void
    {
        $response = $this->actingAs($this->user)->get('/learner/dashboard');

        $response->assertOk();
        $response->assertInertia(fn($page) => $page
                ->component('learner/Dashboard')
                ->has('myLearning', 0)
        );
    }

    public function test_my_learning_includes_course_details_for_card_display(): void
    {
        $course = Course::factory()->published()->create([
            'title'                      => 'Vue.js Mastery',
            'short_description'          => 'Learn Vue.js from beginner to advanced',
            'difficulty_level'           => 'intermediate',
            'estimated_duration_minutes' => 120,
        ]);

        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson1 = Lesson::factory()->create(['course_section_id' => $section->id]);
        $lesson2 = Lesson::factory()->create(['course_section_id' => $section->id]);

        $enrollment = Enrollment::factory()->create([
            'user_id'             => $this->user->id,
            'course_id'           => $course->id,
            'status'              => 'active',
            'progress_percentage' => 50,
            'last_lesson_id'      => $lesson2->id,
        ]);

        $response = $this->actingAs($this->user)->get('/learner/dashboard');

        $response->assertOk();
        $response->assertInertia(fn($page) => $page
                ->component('learner/Dashboard')
                ->has('myLearning', 1)
                ->where('myLearning.0.title', 'Vue.js Mastery')
                ->where('myLearning.0.slug', $course->slug)
                ->where('myLearning.0.short_description', 'Learn Vue.js from beginner to advanced')
                ->where('myLearning.0.instructor', $course->user->name)
                ->where('myLearning.0.progress_percentage', 50)
                ->where('myLearning.0.last_lesson_id', $lesson2->id)
                ->where('myLearning.0.duration', 120)
                ->where('myLearning.0.difficulty_level', 'intermediate')
                ->where('myLearning.0.lessons_count', 2)
        );
    }

    public function test_my_learning_shows_completed_courses(): void
    {
        $course = Course::factory()->published()->create();

        $enrollment = Enrollment::factory()->create([
            'user_id'             => $this->user->id,
            'course_id'           => $course->id,
            'status'              => 'completed',
            'progress_percentage' => 100,
            'completed_at'        => now(),
        ]);

        $response = $this->actingAs($this->user)->get('/learner/dashboard');

        $response->assertOk();
        $response->assertInertia(fn($page) => $page
                ->component('learner/Dashboard')
                ->has('myLearning', 1)
                ->where('myLearning.0.progress_percentage', 100)
                ->where('myLearning.0.status', 'completed')
        );
    }

    public function test_my_learning_does_not_show_dropped_enrollments(): void
    {
        $course = Course::factory()->published()->create();

        $enrollment = Enrollment::factory()->create([
            'user_id'             => $this->user->id,
            'course_id'           => $course->id,
            'status'              => 'dropped',
            'progress_percentage' => 25,
        ]);

        $response = $this->actingAs($this->user)->get('/learner/dashboard');

        $response->assertOk();
        $response->assertInertia(fn($page) => $page
                ->component('learner/Dashboard')
                ->has('myLearning', 0) // Dropped enrollments should not be shown
        );
    }

    public function test_learner_dashboard_includes_featured_courses(): void
    {
        // Create multiple courses with enrollments to make them "featured"
        $featuredCourse1 = Course::factory()->published()->create(['visibility' => 'public']);
        $featuredCourse2 = Course::factory()->published()->create(['visibility' => 'public']);

        // Add some enrollments to make them popular
        Enrollment::factory()->count(5)->create(['course_id' => $featuredCourse1->id]);
        Enrollment::factory()->count(3)->create(['course_id' => $featuredCourse2->id]);

        $response = $this->actingAs($this->user)->get('/learner/dashboard');

        $response->assertOk();
        $response->assertInertia(fn($page) => $page
                ->component('learner/Dashboard')
                ->has('featuredCourses', 2) // Should show top 5, but we only have 2
                ->where('featuredCourses.0.enrollments_count', 5)
                ->where('featuredCourses.1.enrollments_count', 3)
        );
    }

    public function test_guest_cannot_access_learner_dashboard(): void
    {
        $response = $this->get('/learner/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_non_learner_cannot_access_learner_dashboard(): void
    {
        $instructor = User::factory()->create(['role' => 'content_manager']);

        $response = $this->actingAs($instructor)->get('/learner/dashboard');
        $response->assertForbidden();
    }

    public function test_my_learning_card_continue_button_links_to_last_lesson(): void
    {
        $course  = Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson  = Lesson::factory()->create(['course_section_id' => $section->id]);

        $enrollment = Enrollment::factory()->create([
            'user_id'             => $this->user->id,
            'course_id'           => $course->id,
            'status'              => 'active',
            'progress_percentage' => 40,
            'last_lesson_id'      => $lesson->id,
        ]);

        $response = $this->actingAs($this->user)->get('/learner/dashboard');

        $response->assertOk();
        $response->assertInertia(fn($page) => $page
                ->component('learner/Dashboard')
                ->has('myLearning', 1)
                ->where('myLearning.0.last_lesson_id', $lesson->id)
        );

        // The card should provide a link to continue to the last lesson
        // This is tested by verifying the last_lesson_id is present in the data
    }

    public function test_my_learning_card_shows_zero_progress_when_no_lessons_completed(): void
    {
        $course = Course::factory()->published()->create();

        $enrollment = Enrollment::factory()->create([
            'user_id'             => $this->user->id,
            'course_id'           => $course->id,
            'status'              => 'active',
            'progress_percentage' => 0,
        ]);

        $response = $this->actingAs($this->user)->get('/learner/dashboard');

        $response->assertOk();
        $response->assertInertia(fn($page) => $page
                ->component('learner/Dashboard')
                ->has('myLearning', 1)
                ->where('myLearning.0.progress_percentage', 0)
        );
    }
}