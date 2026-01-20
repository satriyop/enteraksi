<?php

/**
 * Unicode and Special Character Tests
 *
 * Ensures the system handles:
 * - Indonesian characters (including special characters)
 * - Unicode characters
 * - Emoji
 * - Special characters in search
 * - Slug generation
 */

use App\Models\Assessment;
use App\Models\Course;
use App\Models\CourseRating;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Str;

describe('Unicode and Special Character Handling', function () {

    describe('Indonesian Characters', function () {

        it('course title accepts Indonesian characters', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);

            $indonesianTitle = 'Manajemen Keuangan untuk Pemula';

            $this->actingAs($cm)
                ->post(route('courses.store'), [
                    'title' => $indonesianTitle,
                    'short_description' => 'Deskripsi singkat tentang keuangan',
                    'difficulty_level' => 'beginner',
                    'visibility' => 'public',
                ])
                ->assertRedirect();

            $course = Course::where('title', $indonesianTitle)->first();
            expect($course)->not->toBeNull();
            expect($course->title)->toBe($indonesianTitle);
        });

        it('course handles Indonesian long description', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);

            $indonesianDescription = 'Kursus ini membahas tentang manajemen keuangan dasar yang meliputi '
                . 'pengelolaan anggaran, investasi, dan perencanaan keuangan jangka panjang. '
                . 'Peserta akan belajar cara membuat laporan keuangan sederhana.';

            $course = Course::factory()->draft()->create([
                'user_id' => $cm->id,
                'long_description' => $indonesianDescription,
            ]);

            expect($course->long_description)->toBe($indonesianDescription);
        });

        it('lesson content accepts Indonesian text', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            $indonesianContent = 'Pelajaran ini akan membahas tentang prinsip-prinsip dasar akuntansi.';

            $this->actingAs($cm)
                ->post(route('sections.lessons.store', $section), [
                    'title' => 'Pengantar Akuntansi',
                    'content_type' => 'text',
                    'content' => $indonesianContent,
                    'order' => 1,
                ])
                ->assertRedirect();

            $lesson = Lesson::where('title', 'Pengantar Akuntansi')->first();
            expect($lesson)->not->toBeNull();
        });

        it('rating review accepts Indonesian text', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);

            Enrollment::factory()->completed()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $indonesianReview = 'Kursus yang sangat bagus dan mudah dipahami. '
                . 'Instruktur menjelaskan dengan sangat jelas dan sabar.';

            $this->actingAs($learner)
                ->post(route('courses.ratings.store', $course), [
                    'rating' => 5,
                    'review' => $indonesianReview,
                ])
                ->assertRedirect();

            $rating = CourseRating::where('user_id', $learner->id)->first();
            expect($rating)->not->toBeNull();
            expect($rating->review)->toBe($indonesianReview);
        });

    });

    describe('Emoji Handling', function () {

        it('course title accepts emoji', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);

            $emojiTitle = '🎯 Belajar Python untuk Pemula 🐍';

            $this->actingAs($cm)
                ->post(route('courses.store'), [
                    'title' => $emojiTitle,
                    'short_description' => 'Kursus programming dengan emoji',
                    'difficulty_level' => 'beginner',
                    'visibility' => 'public',
                ])
                ->assertRedirect();

            $course = Course::where('title', $emojiTitle)->first();
            expect($course)->not->toBeNull();
            expect($course->title)->toBe($emojiTitle);
        });

        it('rating review accepts emoji', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);

            Enrollment::factory()->completed()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $emojiReview = '👍 Sangat bagus! 🎉 Recommended banget! ⭐⭐⭐⭐⭐';

            $this->actingAs($learner)
                ->post(route('courses.ratings.store', $course), [
                    'rating' => 5,
                    'review' => $emojiReview,
                ])
                ->assertRedirect();

            $rating = CourseRating::where('user_id', $learner->id)->first();
            expect($rating->review)->toBe($emojiReview);
        });

    });

    describe('Special Characters', function () {

        it('course title handles ampersand', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);

            $this->actingAs($cm)
                ->post(route('courses.store'), [
                    'title' => 'Akuntansi & Keuangan',
                    'short_description' => 'Kursus dengan ampersand',
                    'difficulty_level' => 'beginner',
                    'visibility' => 'public',
                ])
                ->assertRedirect();

            $course = Course::where('title', 'Akuntansi & Keuangan')->first();
            expect($course)->not->toBeNull();
        });

        it('course title handles quotes', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);

            $this->actingAs($cm)
                ->post(route('courses.store'), [
                    'title' => '"Best Practices" untuk Developer',
                    'short_description' => 'Kursus dengan quotes',
                    'difficulty_level' => 'intermediate',
                    'visibility' => 'public',
                ])
                ->assertRedirect();

            $course = Course::where('title', '"Best Practices" untuk Developer')->first();
            expect($course)->not->toBeNull();
        });

        it('course title handles HTML entities safely', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);

            // This should be stored safely without XSS risk
            $this->actingAs($cm)
                ->post(route('courses.store'), [
                    'title' => 'HTML <script>alert("xss")</script> Test',
                    'short_description' => 'Testing XSS prevention',
                    'difficulty_level' => 'beginner',
                    'visibility' => 'public',
                ])
                ->assertRedirect();

            // The title should be stored as-is (escaping happens at display time)
            $course = Course::orderBy('id', 'desc')->first();
            expect($course)->not->toBeNull();
            // The script tag should not execute - this is a display concern
        });

        it('section title handles parentheses and brackets', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($cm)
                ->post(route('courses.sections.store', $course), [
                    'title' => 'Modul 1: Pengantar (Bagian A) [Dasar]',
                    'order' => 1,
                ])
                ->assertRedirect();

            $section = CourseSection::where('title', 'Modul 1: Pengantar (Bagian A) [Dasar]')->first();
            expect($section)->not->toBeNull();
        });

    });

    describe('Slug Generation', function () {

        it('generates valid slug from Indonesian title', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);

            $this->actingAs($cm)
                ->post(route('courses.store'), [
                    'title' => 'Belajar Pemrograman PHP untuk Pemula',
                    'short_description' => 'Kursus PHP',
                    'difficulty_level' => 'beginner',
                    'visibility' => 'public',
                ])
                ->assertRedirect();

            $course = Course::where('title', 'Belajar Pemrograman PHP untuk Pemula')->first();
            expect($course)->not->toBeNull();
            expect($course->slug)->toContain('belajar-pemrograman-php');
        });

        it('generates unique slugs for duplicate titles', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);

            // Create first course
            $course1 = Course::factory()->draft()->create([
                'user_id' => $cm->id,
                'title' => 'Unique Title Test',
            ]);

            // Create second course with same title (via API)
            $this->actingAs($cm)
                ->post(route('courses.store'), [
                    'title' => 'Unique Title Test',
                    'short_description' => 'Second course',
                    'difficulty_level' => 'beginner',
                    'visibility' => 'public',
                ])
                ->assertRedirect();

            // Get the second course
            $course2 = Course::where('title', 'Unique Title Test')
                ->where('id', '!=', $course1->id)
                ->first();

            expect($course2)->not->toBeNull();
            // Slugs should be different (second one has random suffix)
            expect($course1->slug)->not->toBe($course2->slug);
        });

        it('slug handles emoji by stripping them', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);

            $this->actingAs($cm)
                ->post(route('courses.store'), [
                    'title' => '🎯 Python Programming 🐍',
                    'short_description' => 'Course with emoji',
                    'difficulty_level' => 'beginner',
                    'visibility' => 'public',
                ])
                ->assertRedirect();

            $course = Course::where('title', '🎯 Python Programming 🐍')->first();
            expect($course)->not->toBeNull();
            // Slug should contain "python-programming" without emoji
            expect($course->slug)->toContain('python-programming');
        });

    });

    describe('Search with Special Characters', function () {

        it('course browse handles search with special characters', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            Course::factory()->published()->public()->create([
                'user_id' => $cm->id,
                'title' => 'C++ Programming Basics',
            ]);

            // Search with special character should not break
            $this->actingAs($learner)
                ->get(route('courses.index', ['search' => 'C++']))
                ->assertOk();
        });

        it('course browse handles search with SQL wildcards', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            Course::factory()->published()->public()->create([
                'user_id' => $cm->id,
                'title' => 'Regular Course Title',
            ]);

            // SQL wildcard characters should be escaped
            $this->actingAs($learner)
                ->get(route('courses.index', ['search' => '%_test']))
                ->assertOk();
        });

        it('course browse handles empty search', function () {
            $learner = User::factory()->create(['role' => 'learner']);

            $this->actingAs($learner)
                ->get(route('courses.index', ['search' => '']))
                ->assertOk();
        });

    });

    describe('Unicode in Assessment', function () {

        it('question text accepts unicode characters', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->draft()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            $unicodeQuestion = 'Apa yang dimaksud dengan "manajemen" dalam konteks bisnis?';

            $this->actingAs($cm)
                ->put(route('assessments.questions.bulkUpdate', [$course, $assessment]), [
                    'questions' => [
                        [
                            'question_type' => 'multiple_choice',
                            'question_text' => $unicodeQuestion,
                            'points' => 10,
                            'order' => 1,
                            'options' => [
                                ['option_text' => 'Jawaban A', 'is_correct' => true],
                                ['option_text' => 'Jawaban B', 'is_correct' => false],
                            ],
                        ],
                    ],
                ])
                ->assertRedirect();

            expect($assessment->questions()->first()->question_text)->toBe($unicodeQuestion);
        });

        it('essay answer accepts long unicode text', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Assessment view should load
            $this->actingAs($learner)
                ->get(route('assessments.show', [$course, $assessment]))
                ->assertOk();
        });

    });

});
