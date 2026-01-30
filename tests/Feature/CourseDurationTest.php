<?php

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\User;

describe('CourseDurationController', function () {
    describe('POST /courses/{course}/recalculate-duration', function () {
        it('allows content manager to recalculate own draft course duration', function () {
            $contentManager = User::factory()->create(['role' => 'content_manager']);
            $this->actingAs($contentManager);

            $course = Course::factory()->draft()->create([
                'user_id' => $contentManager->id,
                'estimated_duration_minutes' => 0,
            ]);

            $section = CourseSection::factory()->create([
                'course_id' => $course->id,
            ]);

            Lesson::factory()->create([
                'course_section_id' => $section->id,
                'estimated_duration_minutes' => 30,
            ]);

            Lesson::factory()->create([
                'course_section_id' => $section->id,
                'estimated_duration_minutes' => 45,
            ]);

            Lesson::factory()->create([
                'course_section_id' => $section->id,
                'estimated_duration_minutes' => 60,
            ]);

            $response = $this->post(route('courses.recalculate-duration', $course));

            $response->assertRedirect(route('courses.edit', $course));
            $response->assertSessionHas('success', 'Durasi kursus berhasil diperbarui berdasarkan durasi materi.');

            $course->refresh();
            expect($course->estimated_duration_minutes)->toBe(135);
        });

        it('allows admin to recalculate any course duration', function () {
            asAdmin();

            $otherUser = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->published()->create([
                'user_id' => $otherUser->id,
                'estimated_duration_minutes' => 0,
            ]);

            $section = CourseSection::factory()->create([
                'course_id' => $course->id,
            ]);

            Lesson::factory()->create([
                'course_section_id' => $section->id,
                'estimated_duration_minutes' => 20,
            ]);

            Lesson::factory()->create([
                'course_section_id' => $section->id,
                'estimated_duration_minutes' => 40,
            ]);

            $response = $this->post(route('courses.recalculate-duration', $course));

            $response->assertRedirect(route('courses.edit', $course));

            $course->refresh();
            expect($course->estimated_duration_minutes)->toBe(60);
        });

        it('prevents learner from recalculating duration', function () {
            asLearner();

            $course = Course::factory()->published()->create();

            $response = $this->post(route('courses.recalculate-duration', $course));

            $response->assertForbidden();
        });

        it('prevents content manager from recalculating others course duration', function () {
            asContentManager();

            $otherUser = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->draft()->create([
                'user_id' => $otherUser->id,
            ]);

            $response = $this->post(route('courses.recalculate-duration', $course));

            $response->assertForbidden();
        });

        it('correctly sums lesson durations from multiple sections', function () {
            $contentManager = User::factory()->create(['role' => 'content_manager']);
            $this->actingAs($contentManager);

            $course = Course::factory()->draft()->create([
                'user_id' => $contentManager->id,
                'estimated_duration_minutes' => 0,
            ]);

            $section1 = CourseSection::factory()->create([
                'course_id' => $course->id,
            ]);

            Lesson::factory()->create([
                'course_section_id' => $section1->id,
                'estimated_duration_minutes' => 15,
            ]);

            Lesson::factory()->create([
                'course_section_id' => $section1->id,
                'estimated_duration_minutes' => 25,
            ]);

            $section2 = CourseSection::factory()->create([
                'course_id' => $course->id,
            ]);

            Lesson::factory()->create([
                'course_section_id' => $section2->id,
                'estimated_duration_minutes' => 35,
            ]);

            Lesson::factory()->create([
                'course_section_id' => $section2->id,
                'estimated_duration_minutes' => 45,
            ]);

            $response = $this->post(route('courses.recalculate-duration', $course));

            $response->assertRedirect(route('courses.edit', $course));

            $course->refresh();
            expect($course->estimated_duration_minutes)->toBe(120);
        });

        it('handles course with no lessons', function () {
            $contentManager = User::factory()->create(['role' => 'content_manager']);
            $this->actingAs($contentManager);

            $course = Course::factory()->draft()->create([
                'user_id' => $contentManager->id,
                'estimated_duration_minutes' => 100,
            ]);

            $response = $this->post(route('courses.recalculate-duration', $course));

            $response->assertRedirect(route('courses.edit', $course));

            $course->refresh();
            expect($course->estimated_duration_minutes)->toBe(0);
        });

        it('redirects to courses.edit with success flash message', function () {
            $contentManager = User::factory()->create(['role' => 'content_manager']);
            $this->actingAs($contentManager);

            $course = Course::factory()->draft()->create([
                'user_id' => $contentManager->id,
            ]);

            $section = CourseSection::factory()->create([
                'course_id' => $course->id,
            ]);

            Lesson::factory()->create([
                'course_section_id' => $section->id,
                'estimated_duration_minutes' => 30,
            ]);

            $response = $this->post(route('courses.recalculate-duration', $course));

            $response->assertRedirect(route('courses.edit', $course));
            $response->assertSessionHas('success');
            expect(session('success'))->toBe('Durasi kursus berhasil diperbarui berdasarkan durasi materi.');
        });

        it('ignores soft-deleted lessons in calculation', function () {
            $contentManager = User::factory()->create(['role' => 'content_manager']);
            $this->actingAs($contentManager);

            $course = Course::factory()->draft()->create([
                'user_id' => $contentManager->id,
                'estimated_duration_minutes' => 0,
            ]);

            $section = CourseSection::factory()->create([
                'course_id' => $course->id,
            ]);

            Lesson::factory()->create([
                'course_section_id' => $section->id,
                'estimated_duration_minutes' => 30,
            ]);

            $deletedLesson = Lesson::factory()->create([
                'course_section_id' => $section->id,
                'estimated_duration_minutes' => 100,
            ]);

            $deletedLesson->delete();

            $response = $this->post(route('courses.recalculate-duration', $course));

            $response->assertRedirect(route('courses.edit', $course));

            $course->refresh();
            expect($course->estimated_duration_minutes)->toBe(30);
        });
    });
});
