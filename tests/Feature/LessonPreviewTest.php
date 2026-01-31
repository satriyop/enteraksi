<?php

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;

describe('LessonPreviewController', function () {
    describe('GET /courses/{course}/lessons/{lesson}/preview', function () {
        it('redirects guest to login', function () {
            $course = Course::factory()->published()->create();

            $section = CourseSection::factory()->create([
                'course_id' => $course->id,
            ]);

            $lesson = Lesson::factory()->create([
                'course_section_id' => $section->id,
                'is_free_preview' => true,
            ]);

            $response = $this->get(route('courses.lessons.preview', [$course, $lesson]));

            $response->assertRedirect(route('login'));
        });

        it('allows authenticated user to access free preview lesson', function () {
            $user = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->create();

            $section = CourseSection::factory()->create([
                'course_id' => $course->id,
            ]);

            $lesson = Lesson::factory()->create([
                'course_section_id' => $section->id,
                'is_free_preview' => true,
            ]);

            $response = $this
                ->actingAs($user)
                ->get(route('courses.lessons.preview', [$course, $lesson]));

            $response->assertSuccessful();
            $response->assertInertia(fn ($page) => $page
                ->component('courses/LessonPreview')
                ->has('course')
                ->has('lesson')
            );
        });

        it('returns 403 for non-free preview lesson', function () {
            $user = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->create();

            $section = CourseSection::factory()->create([
                'course_id' => $course->id,
            ]);

            $lesson = Lesson::factory()->create([
                'course_section_id' => $section->id,
                'is_free_preview' => false,
            ]);

            $response = $this
                ->actingAs($user)
                ->get(route('courses.lessons.preview', [$course, $lesson]));

            $response->assertForbidden();
        });

        it('returns 404 for unpublished course', function () {
            $user = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->draft()->create();

            $section = CourseSection::factory()->create([
                'course_id' => $course->id,
            ]);

            $lesson = Lesson::factory()->create([
                'course_section_id' => $section->id,
                'is_free_preview' => true,
            ]);

            $response = $this
                ->actingAs($user)
                ->get(route('courses.lessons.preview', [$course, $lesson]));

            $response->assertNotFound();
        });

        it('returns 404 when lesson does not belong to course', function () {
            $user = User::factory()->create(['role' => 'learner']);

            $course1 = Course::factory()->published()->create();
            $course2 = Course::factory()->published()->create();

            $section = CourseSection::factory()->create([
                'course_id' => $course2->id,
            ]);

            $lesson = Lesson::factory()->create([
                'course_section_id' => $section->id,
                'is_free_preview' => true,
            ]);

            $response = $this
                ->actingAs($user)
                ->get(route('courses.lessons.preview', [$course1, $lesson]));

            $response->assertNotFound();
        });

        it('includes enrollment data when user is enrolled', function () {
            $user = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->create();

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            $section = CourseSection::factory()->create([
                'course_id' => $course->id,
            ]);

            $lesson = Lesson::factory()->create([
                'course_section_id' => $section->id,
                'is_free_preview' => true,
            ]);

            $response = $this
                ->actingAs($user)
                ->get(route('courses.lessons.preview', [$course, $lesson]));

            $response->assertSuccessful();
            $response->assertInertia(fn ($page) => $page
                ->component('courses/LessonPreview')
                ->has('enrollment')
                ->where('enrollment.id', $enrollment->id)
            );
        });

        it('has null enrollment when user is not enrolled', function () {
            $user = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->create();

            $section = CourseSection::factory()->create([
                'course_id' => $course->id,
            ]);

            $lesson = Lesson::factory()->create([
                'course_section_id' => $section->id,
                'is_free_preview' => true,
            ]);

            $response = $this
                ->actingAs($user)
                ->get(route('courses.lessons.preview', [$course, $lesson]));

            $response->assertSuccessful();
            $response->assertInertia(fn ($page) => $page
                ->component('courses/LessonPreview')
                ->where('enrollment', null)
            );
        });

        it('loads course relationships for preview page', function () {
            $user = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->create();

            $section = CourseSection::factory()->create([
                'course_id' => $course->id,
            ]);

            Lesson::factory()->count(3)->create([
                'course_section_id' => $section->id,
            ]);

            $lesson = Lesson::factory()->create([
                'course_section_id' => $section->id,
                'is_free_preview' => true,
            ]);

            $response = $this
                ->actingAs($user)
                ->get(route('courses.lessons.preview', [$course, $lesson]));

            $response->assertSuccessful();
            $response->assertInertia(fn ($page) => $page
                ->component('courses/LessonPreview')
                ->has('course.category')
                ->has('course.user')
                ->has('course.sections')
                ->has('lesson.section')
            );
        });
    });
});
