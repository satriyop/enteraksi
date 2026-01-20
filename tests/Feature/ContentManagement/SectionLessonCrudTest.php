<?php

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\User;

/**
 * Section and Lesson CRUD Tests
 *
 * These tests verify that content managers can properly manage
 * course content (sections and lessons) with proper authorization.
 */
describe('Section and Lesson CRUD', function () {

    describe('Section CRUD Operations', function () {

        describe('Creating Sections', function () {

            it('content manager can create section in own draft course', function () {
                $cm = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

                $this->actingAs($cm)
                    ->post(route('courses.sections.store', $course), [
                        'title' => 'Bab 1: Pengantar',
                        'description' => 'Deskripsi bab pertama',
                    ])
                    ->assertRedirect();

                $this->assertDatabaseHas('course_sections', [
                    'course_id' => $course->id,
                    'title' => 'Bab 1: Pengantar',
                ]);
            });

            it('section gets correct order when added', function () {
                $cm = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

                // Create first section
                $this->actingAs($cm)
                    ->post(route('courses.sections.store', $course), ['title' => 'Section 1']);

                // Create second section
                $this->actingAs($cm)
                    ->post(route('courses.sections.store', $course), ['title' => 'Section 2']);

                $sections = $course->sections()->orderBy('order')->get();

                expect($sections)->toHaveCount(2);
                expect($sections[0]->title)->toBe('Section 1');
                expect($sections[0]->order)->toBe(1);
                expect($sections[1]->title)->toBe('Section 2');
                expect($sections[1]->order)->toBe(2);
            });

            it('validates section title is required', function () {
                $cm = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

                $this->actingAs($cm)
                    ->post(route('courses.sections.store', $course), ['title' => ''])
                    ->assertSessionHasErrors('title');
            });

        });

        describe('Updating Sections', function () {

            it('content manager can update section in own draft course', function () {
                $cm = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
                $section = CourseSection::factory()->create([
                    'course_id' => $course->id,
                    'title' => 'Original Title',
                ]);

                $this->actingAs($cm)
                    ->patch(route('sections.update', $section), [
                        'title' => 'Updated Title',
                        'description' => 'New description',
                    ])
                    ->assertRedirect();

                expect($section->refresh()->title)->toBe('Updated Title');
            });

            it('updates only provided fields', function () {
                $cm = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
                $section = CourseSection::factory()->create([
                    'course_id' => $course->id,
                    'title' => 'Original Title',
                    'description' => 'Original Description',
                ]);

                $this->actingAs($cm)
                    ->patch(route('sections.update', $section), [
                        'title' => 'Updated Title',
                    ]);

                $section->refresh();
                expect($section->title)->toBe('Updated Title');
                expect($section->description)->toBe('Original Description');
            });

        });

        describe('Deleting Sections', function () {

            it('content manager can delete section from own draft course', function () {
                $cm = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
                $section = CourseSection::factory()->create(['course_id' => $course->id]);

                $this->actingAs($cm)
                    ->delete(route('sections.destroy', $section))
                    ->assertRedirect();

                // Section uses soft delete
                $this->assertSoftDeleted('course_sections', ['id' => $section->id]);
            });

            it('deleting section cascades to lessons', function () {
                $cm = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
                $section = CourseSection::factory()->create(['course_id' => $course->id]);
                $lesson1 = Lesson::factory()->create(['course_section_id' => $section->id]);
                $lesson2 = Lesson::factory()->create(['course_section_id' => $section->id]);

                $this->actingAs($cm)
                    ->delete(route('sections.destroy', $section));

                // Section and lessons use soft delete
                $this->assertSoftDeleted('course_sections', ['id' => $section->id]);
                $this->assertSoftDeleted('lessons', ['id' => $lesson1->id]);
                $this->assertSoftDeleted('lessons', ['id' => $lesson2->id]);
            });

        });

        describe('Reordering Sections', function () {

            it('content manager can reorder sections in own draft course', function () {
                $cm = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

                $section1 = CourseSection::factory()->create([
                    'course_id' => $course->id,
                    'order' => 1,
                ]);
                $section2 = CourseSection::factory()->create([
                    'course_id' => $course->id,
                    'order' => 2,
                ]);
                $section3 = CourseSection::factory()->create([
                    'course_id' => $course->id,
                    'order' => 3,
                ]);

                // Reorder: move section3 to first position
                $this->actingAs($cm)
                    ->post(route('courses.sections.reorder', $course), [
                        'sections' => [$section3->id, $section1->id, $section2->id],
                    ])
                    ->assertOk();

                expect($section3->refresh()->order)->toBe(1);
                expect($section1->refresh()->order)->toBe(2);
                expect($section2->refresh()->order)->toBe(3);
            });

        });

    });

    describe('Lesson CRUD Operations', function () {

        describe('Creating Lessons', function () {

            it('content manager can create lesson in own section', function () {
                $cm = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
                $section = CourseSection::factory()->create(['course_id' => $course->id]);

                $this->actingAs($cm)
                    ->post(route('sections.lessons.store', $section), [
                        'title' => 'Pelajaran 1: Dasar-dasar',
                        'content_type' => 'text',
                    ])
                    ->assertRedirect();

                $this->assertDatabaseHas('lessons', [
                    'course_section_id' => $section->id,
                    'title' => 'Pelajaran 1: Dasar-dasar',
                    'content_type' => 'text',
                ]);
            });

            it('lesson gets correct order when added', function () {
                $cm = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
                $section = CourseSection::factory()->create(['course_id' => $course->id]);

                $this->actingAs($cm)
                    ->post(route('sections.lessons.store', $section), [
                        'title' => 'Lesson 1',
                        'content_type' => 'text',
                    ]);

                $this->actingAs($cm)
                    ->post(route('sections.lessons.store', $section), [
                        'title' => 'Lesson 2',
                        'content_type' => 'video',
                    ]);

                $lessons = $section->lessons()->orderBy('order')->get();

                expect($lessons)->toHaveCount(2);
                expect($lessons[0]->order)->toBe(1);
                expect($lessons[1]->order)->toBe(2);
            });

            it('validates lesson title is required', function () {
                $cm = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
                $section = CourseSection::factory()->create(['course_id' => $course->id]);

                $this->actingAs($cm)
                    ->post(route('sections.lessons.store', $section), [
                        'title' => '',
                        'content_type' => 'text',
                    ])
                    ->assertSessionHasErrors('title');
            });

            it('validates content type is valid', function () {
                $cm = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
                $section = CourseSection::factory()->create(['course_id' => $course->id]);

                $this->actingAs($cm)
                    ->post(route('sections.lessons.store', $section), [
                        'title' => 'Test Lesson',
                        'content_type' => 'invalid_type',
                    ])
                    ->assertSessionHasErrors('content_type');
            });

        });

        describe('Updating Lessons', function () {

            it('content manager can update lesson in own course', function () {
                $cm = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
                $section = CourseSection::factory()->create(['course_id' => $course->id]);
                $lesson = Lesson::factory()->create([
                    'course_section_id' => $section->id,
                    'title' => 'Original',
                ]);

                $this->actingAs($cm)
                    ->patch(route('lessons.update', $lesson), [
                        'title' => 'Updated',
                        'description' => 'New description',
                        'content_type' => $lesson->content_type,
                    ])
                    ->assertRedirect();

                expect($lesson->refresh()->title)->toBe('Updated');
            });

            it('can update lesson content', function () {
                $cm = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
                $section = CourseSection::factory()->create(['course_id' => $course->id]);
                $lesson = Lesson::factory()->create([
                    'course_section_id' => $section->id,
                    'content_type' => 'text',
                ]);

                // rich_content expects Tiptap editor format (array/JSON)
                $newContent = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Updated content']]]]];

                $this->actingAs($cm)
                    ->patch(route('lessons.update', $lesson), [
                        'title' => $lesson->title,
                        'content_type' => $lesson->content_type,
                        'rich_content' => $newContent,
                    ])
                    ->assertRedirect();

                expect($lesson->refresh()->rich_content)->toBe($newContent);
            });

        });

        describe('Viewing Lesson Edit Page', function () {

            it('content manager can view edit page for own lesson', function () {
                $cm = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
                $section = CourseSection::factory()->create(['course_id' => $course->id]);
                $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

                $this->actingAs($cm)
                    ->get(route('lessons.edit', $lesson))
                    ->assertOk()
                    ->assertInertia(fn ($page) => $page
                        ->component('lessons/Edit')
                        ->has('lesson')
                        ->has('section')
                    );
            });

            it('returns 403 for another users lesson', function () {
                $cm1 = User::factory()->create(['role' => 'content_manager']);
                $cm2 = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm2->id]);
                $section = CourseSection::factory()->create(['course_id' => $course->id]);
                $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

                $this->actingAs($cm1)
                    ->get(route('lessons.edit', $lesson))
                    ->assertForbidden();
            });

        });

        describe('Deleting Lessons', function () {

            it('content manager can delete lesson from own draft course', function () {
                $cm = User::factory()->create(['role' => 'content_manager']);
                $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
                $section = CourseSection::factory()->create(['course_id' => $course->id]);
                $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

                $this->actingAs($cm)
                    ->delete(route('lessons.destroy', $lesson))
                    ->assertRedirect();

                // Lesson uses soft delete
                $this->assertSoftDeleted('lessons', ['id' => $lesson->id]);
            });

        });

    });

    describe('Admin Capabilities', function () {

        it('admin can create section in any course', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($admin)
                ->post(route('courses.sections.store', $course), ['title' => 'Admin Section'])
                ->assertRedirect();

            $this->assertDatabaseHas('course_sections', [
                'course_id' => $course->id,
                'title' => 'Admin Section',
            ]);
        });

        it('admin can update any section', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create([
                'course_id' => $course->id,
                'title' => 'Original',
            ]);

            $this->actingAs($admin)
                ->patch(route('sections.update', $section), ['title' => 'Admin Updated'])
                ->assertRedirect();

            expect($section->refresh()->title)->toBe('Admin Updated');
        });

        it('admin can delete any section', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            $this->actingAs($admin)
                ->delete(route('sections.destroy', $section))
                ->assertRedirect();

            // Section uses soft delete
            $this->assertSoftDeleted('course_sections', ['id' => $section->id]);
        });

        it('admin can create lesson in any section', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            $this->actingAs($admin)
                ->post(route('sections.lessons.store', $section), [
                    'title' => 'Admin Lesson',
                    'content_type' => 'text',
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('lessons', ['title' => 'Admin Lesson']);
        });

        it('admin can update any lesson', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create([
                'course_section_id' => $section->id,
                'title' => 'Original',
            ]);

            $this->actingAs($admin)
                ->patch(route('lessons.update', $lesson), [
                    'title' => 'Admin Updated',
                    'content_type' => $lesson->content_type,
                ])
                ->assertRedirect();

            expect($lesson->refresh()->title)->toBe('Admin Updated');
        });

        it('admin can delete any lesson', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $this->actingAs($admin)
                ->delete(route('lessons.destroy', $lesson))
                ->assertRedirect();

            // Lesson uses soft delete
            $this->assertSoftDeleted('lessons', ['id' => $lesson->id]);
        });

    });

    describe('Content Type Specific Operations', function () {

        it('creates text lesson with content', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            // rich_content expects Tiptap editor format (array/JSON)
            $richContent = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'This is the lesson content']]]]];

            $this->actingAs($cm)
                ->post(route('sections.lessons.store', $section), [
                    'title' => 'Text Lesson',
                    'content_type' => 'text',
                    'rich_content' => $richContent,
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('lessons', [
                'title' => 'Text Lesson',
                'content_type' => 'text',
            ]);
        });

        it('creates video lesson with media URL', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            $this->actingAs($cm)
                ->post(route('sections.lessons.store', $section), [
                    'title' => 'Video Lesson',
                    'content_type' => 'video',
                    'media_url' => 'https://example.com/video.mp4',
                    'duration_minutes' => 15,
                ]);

            $this->assertDatabaseHas('lessons', [
                'title' => 'Video Lesson',
                'content_type' => 'video',
            ]);
        });

    });

});
