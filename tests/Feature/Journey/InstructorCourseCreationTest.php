<?php

/**
 * Instructor Course Creation End-to-End Tests
 *
 * Tests the complete instructor workflow:
 * - Course creation with metadata
 * - Section and lesson management
 * - Assessment creation
 * - Publishing workflow
 */

use App\Models\Assessment;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;

describe('Instructor Course Creation Journey', function () {

    describe('Full Course Creation Workflow', function () {

        it('content manager can create a complete course from scratch', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);

            // Step 1: Create Course
            $this->actingAs($cm)
                ->post(route('courses.store'), [
                    'title' => 'Dasar-Dasar Perbankan Syariah',
                    'short_description' => 'Mempelajari prinsip-prinsip perbankan syariah',
                    'difficulty_level' => 'beginner',
                ])
                ->assertRedirect();

            $course = Course::where('title', 'Dasar-Dasar Perbankan Syariah')->first();
            expect($course)->not->toBeNull();
            expect($course->user_id)->toBe($cm->id);
            expect($course->isDraft())->toBeTrue();
            expect($course->difficulty_level)->toBe('beginner');

            // Step 2: Add First Section
            $this->actingAs($cm)
                ->post(route('courses.sections.store', $course), [
                    'title' => 'Pendahuluan',
                    'description' => 'Pengantar perbankan syariah',
                ])
                ->assertRedirect();

            $section1 = $course->sections()->where('title', 'Pendahuluan')->first();
            expect($section1)->not->toBeNull();

            // Step 3: Add Lessons to First Section
            $this->actingAs($cm)
                ->post(route('sections.lessons.store', $section1), [
                    'title' => 'Apa itu Perbankan Syariah?',
                    'content_type' => 'text',
                    'rich_content' => [
                        'type' => 'doc',
                        'content' => [[
                            'type' => 'paragraph',
                            'content' => [['type' => 'text', 'text' => 'Perbankan syariah adalah sistem perbankan yang beroperasi berdasarkan prinsip-prinsip syariah Islam.']],
                        ]],
                    ],
                ])
                ->assertRedirect();

            $lesson1 = $section1->lessons()->where('title', 'Apa itu Perbankan Syariah?')->first();
            expect($lesson1)->not->toBeNull();
            expect($lesson1->content_type)->toBe('text');

            // Step 4: Add Second Section
            $this->actingAs($cm)
                ->post(route('courses.sections.store', $course), [
                    'title' => 'Prinsip Dasar',
                    'description' => 'Prinsip-prinsip dasar perbankan syariah',
                ])
                ->assertRedirect();

            $section2 = $course->sections()->where('title', 'Prinsip Dasar')->first();
            expect($section2)->not->toBeNull();

            // Step 5: Add Video Lesson
            $this->actingAs($cm)
                ->post(route('sections.lessons.store', $section2), [
                    'title' => 'Video: Akad dalam Perbankan Syariah',
                    'content_type' => 'youtube',
                    'youtube_url' => 'https://www.youtube.com/watch?v=example123',
                ])
                ->assertRedirect();

            $videoLesson = $section2->lessons()->where('content_type', 'youtube')->first();
            expect($videoLesson)->not->toBeNull();

            // Verify final structure
            $course->refresh();
            expect($course->sections()->count())->toBe(2);
            expect($course->lessons()->count())->toBe(2);
        });

        it('content manager can create different content types', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            // Text lesson
            $this->actingAs($cm)
                ->post(route('sections.lessons.store', $section), [
                    'title' => 'Text Lesson',
                    'content_type' => 'text',
                    'rich_content' => ['type' => 'doc', 'content' => []],
                ])
                ->assertRedirect();

            // Video lesson (YouTube)
            $this->actingAs($cm)
                ->post(route('sections.lessons.store', $section), [
                    'title' => 'YouTube Video',
                    'content_type' => 'youtube',
                    'youtube_url' => 'https://youtube.com/watch?v=test',
                ])
                ->assertRedirect();

            // Conference lesson
            $this->actingAs($cm)
                ->post(route('sections.lessons.store', $section), [
                    'title' => 'Live Session',
                    'content_type' => 'conference',
                    'conference_url' => 'https://zoom.us/j/123456',
                    'conference_type' => 'zoom',
                ])
                ->assertRedirect();

            expect($section->lessons()->count())->toBe(3);

            $types = $section->lessons()->pluck('content_type')->toArray();
            expect($types)->toContain('text', 'youtube', 'conference');
        });

    });

    describe('Assessment Creation Workflow', function () {

        it('content manager can create assessment for own course', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($cm)
                ->post(route('assessments.store', $course), [
                    'title' => 'Ujian Tengah Semester',
                    'description' => 'Ujian untuk mengukur pemahaman dasar',
                    'passing_score' => 70,
                    'max_attempts' => 3,
                    'time_limit_minutes' => 60,
                    'status' => 'draft',
                    'visibility' => 'public',
                ])
                ->assertRedirect();

            $assessment = Assessment::where('title', 'Ujian Tengah Semester')->first();
            expect($assessment)->not->toBeNull();
            expect($assessment->course_id)->toBe($course->id);
            expect($assessment->user_id)->toBe($cm->id);
            expect($assessment->status)->toBe('draft');
            expect($assessment->passing_score)->toBe(70);
            expect($assessment->max_attempts)->toBe(3);
        });

        it('content manager can add questions via bulk update', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->draft()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            // Add questions via bulk update (PUT)
            $this->actingAs($cm)
                ->put(route('assessments.questions.bulkUpdate', [$course, $assessment]), [
                    'questions' => [
                        [
                            'id' => 0, // New question
                            'question_text' => 'Apa prinsip utama perbankan syariah?',
                            'question_type' => 'multiple_choice',
                            'points' => 10,
                            'order' => 1,
                            'options' => [
                                ['option_text' => 'Riba', 'is_correct' => false, 'order' => 1],
                                ['option_text' => 'Bagi hasil', 'is_correct' => true, 'order' => 2],
                                ['option_text' => 'Spekulasi', 'is_correct' => false, 'order' => 3],
                            ],
                        ],
                        [
                            'id' => 0,
                            'question_text' => 'Perbankan syariah bebas dari riba.',
                            'question_type' => 'true_false',
                            'points' => 5,
                            'order' => 2,
                            'options' => [
                                ['option_text' => 'Benar', 'is_correct' => true, 'order' => 1],
                                ['option_text' => 'Salah', 'is_correct' => false, 'order' => 2],
                            ],
                        ],
                    ],
                ])
                ->assertRedirect();

            expect($assessment->questions()->count())->toBe(2);

            $mcQuestion = $assessment->questions()->where('question_type', 'multiple_choice')->first();
            expect($mcQuestion)->not->toBeNull();
            expect($mcQuestion->options()->count())->toBe(3);

            $tfQuestion = $assessment->questions()->where('question_type', 'true_false')->first();
            expect($tfQuestion)->not->toBeNull();
        });

        it('content manager cannot publish assessment', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->draft()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            $this->actingAs($cm)
                ->post(route('assessments.publish', [$course, $assessment]))
                ->assertForbidden();

            expect($assessment->refresh()->status)->toBe('draft');
        });

        it('admin can publish assessment', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->draft()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            $this->actingAs($admin)
                ->post(route('assessments.publish', [$course, $assessment]))
                ->assertRedirect();

            expect($assessment->refresh()->status)->toBe('published');
        });

    });

    describe('Course Update and Edit Workflow', function () {

        it('owner can update draft course details', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($cm)
                ->patch(route('courses.update', $course), [
                    'title' => 'Updated Course Title',
                    'short_description' => 'Updated description',
                    'difficulty_level' => 'advanced',
                    'visibility' => 'public',
                ])
                ->assertRedirect();

            $course->refresh();
            expect($course->title)->toBe('Updated Course Title');
            expect($course->short_description)->toBe('Updated description');
            expect($course->difficulty_level)->toBe('advanced');
        });

        it('owner can update section details', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            $this->actingAs($cm)
                ->patch(route('sections.update', $section), [
                    'title' => 'Updated Section Title',
                    'description' => 'Updated section description',
                ])
                ->assertRedirect();

            expect($section->refresh()->title)->toBe('Updated Section Title');
        });

        it('owner can update lesson details', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create([
                'course_section_id' => $section->id,
                'content_type' => 'text',
            ]);

            $this->actingAs($cm)
                ->patch(route('lessons.update', $lesson), [
                    'title' => 'Updated Lesson Title',
                    'content_type' => 'text',
                    'rich_content' => [
                        'type' => 'doc',
                        'content' => [[
                            'type' => 'paragraph',
                            'content' => [['type' => 'text', 'text' => 'Updated content']],
                        ]],
                    ],
                ])
                ->assertRedirect();

            expect($lesson->refresh()->title)->toBe('Updated Lesson Title');
        });

        it('owner can delete lesson from draft course', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $lessonId = $lesson->id;

            $this->actingAs($cm)
                ->delete(route('lessons.destroy', $lesson))
                ->assertRedirect();

            // Lesson uses soft delete
            $this->assertSoftDeleted('lessons', ['id' => $lessonId]);
        });

        it('owner can delete section from draft course', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            $sectionId = $section->id;

            $this->actingAs($cm)
                ->delete(route('sections.destroy', $section))
                ->assertRedirect();

            // Section uses soft delete
            $this->assertSoftDeleted('course_sections', ['id' => $sectionId]);
        });

    });

    describe('Publishing Restrictions', function () {

        it('CM cannot modify structure of published course', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $admin = User::factory()->create(['role' => 'lms_admin']);

            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            Lesson::factory()->create(['course_section_id' => $section->id]);

            // Admin publishes the course
            $this->actingAs($admin)->post(route('courses.publish', $course));

            expect($course->refresh()->isPublished())->toBeTrue();

            // CM cannot add new sections
            $this->actingAs($cm)
                ->post(route('courses.sections.store', $course), [
                    'title' => 'New Section',
                ])
                ->assertForbidden();

            // CM cannot add new lessons
            $this->actingAs($cm)
                ->post(route('sections.lessons.store', $section), [
                    'title' => 'New Lesson',
                    'content_type' => 'text',
                ])
                ->assertForbidden();

            // CM cannot delete sections
            $this->actingAs($cm)
                ->delete(route('sections.destroy', $section))
                ->assertForbidden();
        });

        it('admin can modify structure of published course', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $admin = User::factory()->create(['role' => 'lms_admin']);

            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            CourseSection::factory()->create(['course_id' => $course->id]);

            // Admin publishes the course
            $this->actingAs($admin)->post(route('courses.publish', $course));

            // Admin CAN add new sections
            $this->actingAs($admin)
                ->post(route('courses.sections.store', $course), [
                    'title' => 'Admin Added Section',
                    'description' => 'Section added by admin',
                ])
                ->assertRedirect();

            expect($course->sections()->where('title', 'Admin Added Section')->exists())->toBeTrue();
        });

    });

    describe('Course Visibility and Access', function () {

        it('draft course is only visible to owner and admins', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $otherCm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $admin = User::factory()->create(['role' => 'lms_admin']);

            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            // Owner can view
            $this->actingAs($cm)
                ->get(route('courses.show', $course))
                ->assertOk();

            // Admin can view
            $this->actingAs($admin)
                ->get(route('courses.show', $course))
                ->assertOk();

            // Other CM can view (for collaboration purposes)
            $this->actingAs($otherCm)
                ->get(route('courses.show', $course))
                ->assertOk();

            // Learner cannot view draft course
            $this->actingAs($learner)
                ->get(route('courses.show', $course))
                ->assertForbidden();
        });

        it('course index shows appropriate courses per role', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $learner = User::factory()->create(['role' => 'learner']);

            // Create various courses
            Course::factory()->draft()->create(['user_id' => $cm->id, 'title' => 'CM Draft']);
            Course::factory()->published()->public()->create(['user_id' => $cm->id, 'title' => 'CM Published']);
            Course::factory()->published()->public()->create(['title' => 'Other Published']);

            // CM sees only their own courses
            $this->actingAs($cm)
                ->get(route('courses.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('courses.data', 2) // CM's 2 courses
                );

            // Admin sees all courses
            $this->actingAs($admin)
                ->get(route('courses.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('courses.data', 3) // All 3 courses
                );

            // Learner sees only published public courses
            $this->actingAs($learner)
                ->get(route('courses.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('courses.data', 2) // 2 published courses
                );
        });

    });

    describe('Assessment Required for Completion', function () {

        it('assessment can be marked as required', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($cm)
                ->post(route('assessments.store', $course), [
                    'title' => 'Required Assessment',
                    'passing_score' => 70,
                    'max_attempts' => 3,
                    'status' => 'draft',
                    'visibility' => 'public',
                    'is_required' => true,
                ])
                ->assertRedirect();

            $assessment = Assessment::where('title', 'Required Assessment')->first();
            expect($assessment->is_required)->toBeTrue();
        });

        it('assessment defaults to required when is_required not specified', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            // Create assessment without specifying is_required
            $this->actingAs($cm)
                ->post(route('assessments.store', $course), [
                    'title' => 'Default Assessment',
                    'passing_score' => 70,
                    'max_attempts' => 3,
                    'status' => 'draft',
                    'visibility' => 'public',
                    // is_required not specified - should default to true
                ])
                ->assertRedirect();

            $assessment = Assessment::where('title', 'Default Assessment')->first();
            // Database default is true
            expect($assessment->is_required)->toBeTrue();
        });

    });

});
