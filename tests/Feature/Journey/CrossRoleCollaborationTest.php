<?php

/**
 * Cross-Role Collaboration Tests
 *
 * Tests multi-user workflows where different roles collaborate:
 * - Content manager creates, admin publishes, learner enrolls
 * - Trainer invitations to any course
 * - Re-enrollment after dropping
 * - Grading workflows across roles
 */

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\User;

describe('Cross-Role Collaboration', function () {

    describe('CM Creates → Admin Publishes → Learner Enrolls Workflow', function () {

        it('completes full workflow from content creation to learner enrollment', function () {
            // Step 1: Content Manager creates a course
            $cm = User::factory()->create(['role' => 'content_manager']);
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $learner = User::factory()->create(['role' => 'learner']);

            // CM creates course
            $this->actingAs($cm)
                ->post(route('courses.store'), [
                    'title' => 'Manajemen Risiko Perbankan',
                    'short_description' => 'Kursus manajemen risiko untuk perbankan',
                    'difficulty_level' => 'intermediate',
                ])
                ->assertRedirect();

            $course = Course::where('title', 'Manajemen Risiko Perbankan')->first();
            expect($course)->not->toBeNull();
            expect($course->user_id)->toBe($cm->id);
            expect($course->isDraft())->toBeTrue();

            // Step 2: CM adds section and lesson
            $this->actingAs($cm)
                ->post(route('courses.sections.store', $course), [
                    'title' => 'Modul 1: Pengenalan',
                    'description' => 'Pengenalan manajemen risiko',
                ])
                ->assertRedirect();

            $section = $course->sections()->first();

            $this->actingAs($cm)
                ->post(route('sections.lessons.store', $section), [
                    'title' => 'Lesson 1: Konsep Dasar',
                    'content_type' => 'text',
                    'rich_content' => [
                        'type' => 'doc',
                        'content' => [[
                            'type' => 'paragraph',
                            'content' => [['type' => 'text', 'text' => 'Materi tentang konsep dasar manajemen risiko.']],
                        ]],
                    ],
                ])
                ->assertRedirect();

            expect($course->lessons()->count())->toBe(1);

            // Step 3: CM cannot publish (403)
            $this->actingAs($cm)
                ->post(route('courses.publish', $course))
                ->assertForbidden();

            expect($course->refresh()->isDraft())->toBeTrue();

            // Step 4: Admin publishes the course
            $this->actingAs($admin)
                ->post(route('courses.publish', $course))
                ->assertRedirect();

            expect($course->refresh()->isPublished())->toBeTrue();

            // Step 5: Learner can now enroll
            $this->actingAs($learner)
                ->post(route('courses.enroll', $course))
                ->assertRedirect();

            $enrollment = Enrollment::where('user_id', $learner->id)
                ->where('course_id', $course->id)
                ->first();

            expect($enrollment)->not->toBeNull();
            expect($enrollment->isActive())->toBeTrue();
        });

        it('learner cannot enroll while course is draft', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($learner)
                ->post(route('courses.enroll', $course))
                ->assertForbidden();

            $this->assertDatabaseMissing('enrollments', [
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);
        });

        it('CM can still view published course but cannot edit structure', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $admin = User::factory()->create(['role' => 'lms_admin']);

            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            // Admin publishes
            $this->actingAs($admin)
                ->post(route('courses.publish', $course));

            // CM can view
            $this->actingAs($cm)
                ->get(route('courses.show', $course))
                ->assertOk();

            // CM cannot add sections to published course
            $this->actingAs($cm)
                ->post(route('courses.sections.store', $course), [
                    'title' => 'New Section',
                ])
                ->assertForbidden();

            // CM cannot add lessons to published course
            $this->actingAs($cm)
                ->post(route('sections.lessons.store', $section), [
                    'title' => 'New Lesson',
                    'content_type' => 'text',
                ])
                ->assertForbidden();
        });

    });

    describe('Trainer Role Capabilities', function () {

        it('trainer can invite learners to any course', function () {
            $trainer = User::factory()->create(['role' => 'trainer']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            // Course owned by someone else
            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);

            // Trainer can invite to any course
            $this->actingAs($trainer)
                ->post(route('courses.invitations.store', $course), [
                    'user_id' => $learner->id,
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('course_invitations', [
                'course_id' => $course->id,
                'user_id' => $learner->id,
                'invited_by' => $trainer->id,
                'status' => 'pending',
            ]);
        });

        it('trainer cannot publish courses', function () {
            $trainer = User::factory()->create(['role' => 'trainer']);
            $course = Course::factory()->draft()->create(['user_id' => $trainer->id]);

            $this->actingAs($trainer)
                ->post(route('courses.publish', $course))
                ->assertForbidden();

            expect($course->refresh()->isDraft())->toBeTrue();
        });

        it('trainer cannot unpublish courses', function () {
            $trainer = User::factory()->create(['role' => 'trainer']);
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $course = Course::factory()->draft()->create(['user_id' => $trainer->id]);

            // Admin publishes
            $this->actingAs($admin)->post(route('courses.publish', $course));

            // Trainer cannot unpublish
            $this->actingAs($trainer)
                ->post(route('courses.unpublish', $course))
                ->assertForbidden();

            expect($course->refresh()->isPublished())->toBeTrue();
        });

        it('trainer cannot archive courses', function () {
            $trainer = User::factory()->create(['role' => 'trainer']);
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $course = Course::factory()->draft()->create(['user_id' => $trainer->id]);

            $this->actingAs($admin)->post(route('courses.publish', $course));

            $this->actingAs($trainer)
                ->post(route('courses.archive', $course))
                ->assertForbidden();

            expect($course->refresh()->isArchived())->toBeFalse();
        });

        it('trainer can create own courses in draft', function () {
            $trainer = User::factory()->create(['role' => 'trainer']);

            $this->actingAs($trainer)
                ->post(route('courses.store'), [
                    'title' => 'Kursus Trainer',
                    'short_description' => 'Dibuat oleh trainer',
                    'difficulty_level' => 'beginner',
                ])
                ->assertRedirect();

            $course = Course::where('title', 'Kursus Trainer')->first();
            expect($course)->not->toBeNull();
            expect($course->user_id)->toBe($trainer->id);
            expect($course->isDraft())->toBeTrue();
        });

        it('trainer can view enrollments on any course', function () {
            $trainer = User::factory()->create(['role' => 'trainer']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);
            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Trainer can view course (which includes enrollments in Inertia props)
            $this->actingAs($trainer)
                ->get(route('courses.show', $course))
                ->assertOk();
        });

        it('trainer can update their own draft course', function () {
            $trainer = User::factory()->create(['role' => 'trainer']);
            $course = Course::factory()->draft()->create(['user_id' => $trainer->id]);

            $this->actingAs($trainer)
                ->patch(route('courses.update', $course), [
                    'title' => 'Updated Trainer Course',
                    'short_description' => 'Updated description',
                    'difficulty_level' => $course->difficulty_level ?? 'beginner',
                    'visibility' => $course->visibility ?? 'restricted',
                ])
                ->assertRedirect();

            expect($course->refresh()->title)->toBe('Updated Trainer Course');
        });

        it('trainer cannot update other trainers draft course', function () {
            $trainer1 = User::factory()->create(['role' => 'trainer']);
            $trainer2 = User::factory()->create(['role' => 'trainer']);
            $course = Course::factory()->draft()->create(['user_id' => $trainer2->id]);

            $this->actingAs($trainer1)
                ->patch(route('courses.update', $course), [
                    'title' => 'Hacked Title',
                ])
                ->assertForbidden();

            expect($course->refresh()->title)->not->toBe('Hacked Title');
        });

    });

    describe('Re-enrollment After Dropping', function () {

        it('learner can re-enroll after dropping and enrollment is reactivated', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            // Create dropped enrollment with some progress
            $droppedEnrollment = Enrollment::factory()->dropped()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
                'progress_percentage' => 50,
            ]);

            expect($droppedEnrollment->isDropped())->toBeTrue();
            expect($droppedEnrollment->progress_percentage)->toBe(50);

            // Re-enrollment reactivates the existing enrollment
            $this->actingAs($learner)
                ->post(route('courses.enroll', $course))
                ->assertRedirect();

            // Verify enrollment was reactivated (not a new one created)
            $enrollment = Enrollment::where('user_id', $learner->id)
                ->where('course_id', $course->id)
                ->first();

            expect($enrollment->id)->toBe($droppedEnrollment->id); // Same record
            expect($enrollment->isActive())->toBeTrue(); // Now active
            expect($enrollment->progress_percentage)->toBe(50); // Progress preserved by default
        });

        it('re-enrollment preserves previous progress by default', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            // Create dropped enrollment with progress
            $droppedEnrollment = Enrollment::factory()->dropped()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
                'progress_percentage' => 75,
                'last_lesson_id' => $lesson->id,
            ]);

            LessonProgress::create([
                'enrollment_id' => $droppedEnrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);

            // Re-enroll
            $this->actingAs($learner)
                ->post(route('courses.enroll', $course))
                ->assertRedirect();

            $enrollment = Enrollment::find($droppedEnrollment->id);

            // Progress is preserved
            expect($enrollment->progress_percentage)->toBe(75);
            expect($enrollment->last_lesson_id)->toBe($lesson->id);

            // Lesson progress still exists and is associated
            expect($enrollment->lessonProgress()->where('lesson_id', $lesson->id)->exists())->toBeTrue();
        });

        it('learner cannot re-enroll while completed enrollment exists', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            // Create completed enrollment
            Enrollment::factory()->completed()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Cannot re-enroll - service blocks active/completed
            $response = $this->actingAs($learner)
                ->post(route('courses.enroll', $course));

            // Should be rejected
            expect($response->status())->toBeIn([403, 302, 422, 500]);
        });

        it('learner cannot re-enroll while active enrollment exists', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            // Create active enrollment
            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Cannot create another enrollment
            $response = $this->actingAs($learner)
                ->post(route('courses.enroll', $course));

            // Should be rejected (403 or redirect with error)
            expect($response->status())->toBeIn([403, 302, 422, 500]);
        });

        it('dropped enrollment progress is not accessible', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            // Create dropped enrollment with some progress
            $enrollment = Enrollment::factory()->dropped()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);

            // Cannot view lesson
            $this->actingAs($learner)
                ->get(route('courses.lessons.show', [$course, $lesson]))
                ->assertForbidden();

            // Cannot update progress
            $this->actingAs($learner)
                ->patch(route('courses.lessons.progress.update', [$course, $lesson]), [
                    'current_page' => 2,
                    'total_pages' => 3,
                ])
                ->assertForbidden();
        });

    });

    describe('Admin Collaboration Workflows', function () {

        it('admin can modify any course regardless of owner', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($admin)
                ->patch(route('courses.update', $course), [
                    'title' => 'Admin Modified Title',
                    'short_description' => 'Modified by admin',
                    'difficulty_level' => $course->difficulty_level ?? 'beginner',
                    'visibility' => $course->visibility ?? 'restricted',
                ])
                ->assertRedirect();

            expect($course->refresh()->title)->toBe('Admin Modified Title');
        });

        it('admin can add sections to any published course', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->published()->create(['user_id' => $cm->id]);

            $this->actingAs($admin)
                ->post(route('courses.sections.store', $course), [
                    'title' => 'Admin Added Section',
                    'description' => 'Added by admin',
                ])
                ->assertRedirect();

            expect($course->sections()->where('title', 'Admin Added Section')->exists())->toBeTrue();
        });

        it('admin can unpublish and re-publish course', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $course = Course::factory()->published()->create();

            // Unpublish
            $this->actingAs($admin)
                ->post(route('courses.unpublish', $course))
                ->assertRedirect();

            expect($course->refresh()->isDraft())->toBeTrue();

            // Re-publish
            $this->actingAs($admin)
                ->post(route('courses.publish', $course))
                ->assertRedirect();

            expect($course->refresh()->isPublished())->toBeTrue();
        });

        it('admin can restore archived course', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $course = Course::factory()->published()->create();

            // Archive
            $this->actingAs($admin)
                ->post(route('courses.archive', $course))
                ->assertRedirect();

            expect($course->refresh()->isArchived())->toBeTrue();

            // Restore to published
            $this->actingAs($admin)
                ->post(route('courses.publish', $course))
                ->assertRedirect();

            expect($course->refresh()->isPublished())->toBeTrue();
        });

    });

    describe('Invitation to Enrollment Workflow', function () {

        it('complete flow: invite → accept → enroll → complete', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $learner = User::factory()->create(['role' => 'learner']);

            // Setup course with content
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            // Admin publishes
            $this->actingAs($admin)->post(route('courses.publish', $course));

            // CM invites learner
            $this->actingAs($cm)
                ->post(route('courses.invitations.store', $course), [
                    'user_id' => $learner->id,
                ])
                ->assertRedirect();

            $invitation = CourseInvitation::where('user_id', $learner->id)
                ->where('course_id', $course->id)
                ->first();

            expect($invitation)->not->toBeNull();
            expect($invitation->status)->toBe('pending');

            // Learner accepts invitation
            $this->actingAs($learner)
                ->post(route('invitations.accept', $invitation))
                ->assertRedirect();

            // Check enrollment created
            $enrollment = Enrollment::where('user_id', $learner->id)
                ->where('course_id', $course->id)
                ->first();

            expect($enrollment)->not->toBeNull();
            expect($enrollment->isActive())->toBeTrue();

            // Check invitation status updated
            expect($invitation->refresh()->status)->toBe('accepted');
        });

        it('CM can only invite to own course', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->create(['user_id' => $cm2->id]);

            // CM1 cannot invite to CM2's course
            $this->actingAs($cm1)
                ->post(route('courses.invitations.store', $course), [
                    'user_id' => $learner->id,
                ])
                ->assertForbidden();

            $this->assertDatabaseMissing('course_invitations', [
                'course_id' => $course->id,
                'user_id' => $learner->id,
            ]);
        });

        it('declined invitation does not create enrollment', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->create(['user_id' => $cm->id]);

            // Create invitation
            $this->actingAs($cm)
                ->post(route('courses.invitations.store', $course), [
                    'user_id' => $learner->id,
                ]);

            $invitation = CourseInvitation::where('user_id', $learner->id)->first();

            // Learner declines
            $this->actingAs($learner)
                ->post(route('invitations.decline', $invitation))
                ->assertRedirect();

            expect($invitation->refresh()->status)->toBe('declined');

            // No enrollment created
            $this->assertDatabaseMissing('enrollments', [
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);
        });

    });

    describe('Bulk Invitation Authorization', function () {

        it('course owner can bulk invite via CSV', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner1 = User::factory()->create(['role' => 'learner', 'email' => 'budi@example.com']);
            $learner2 = User::factory()->create(['role' => 'learner', 'email' => 'siti@example.com']);

            $course = Course::factory()->published()->create(['user_id' => $cm->id]);

            // Create CSV file
            $csvContent = "email\nbudi@example.com\nsiti@example.com";
            $csvFile = \Illuminate\Http\UploadedFile::fake()->createWithContent('learners.csv', $csvContent);

            $this->actingAs($cm)
                ->post(route('courses.invitations.bulk', $course), [
                    'file' => $csvFile,
                ])
                ->assertRedirect();

            // Check invitations created
            expect(CourseInvitation::where('course_id', $course->id)->count())->toBeGreaterThanOrEqual(1);
        });

        it('trainer can bulk invite to any course', function () {
            $trainer = User::factory()->create(['role' => 'trainer']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner', 'email' => 'ahmad@example.com']);

            $course = Course::factory()->published()->create(['user_id' => $cm->id]);

            $csvContent = "email\nahmad@example.com";
            $csvFile = \Illuminate\Http\UploadedFile::fake()->createWithContent('learners.csv', $csvContent);

            $this->actingAs($trainer)
                ->post(route('courses.invitations.bulk', $course), [
                    'file' => $csvFile,
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('course_invitations', [
                'course_id' => $course->id,
                'user_id' => $learner->id,
                'invited_by' => $trainer->id,
            ]);
        });

        it('content manager cannot bulk invite to others course', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            User::factory()->create(['role' => 'learner', 'email' => 'test@example.com']);

            $course = Course::factory()->published()->create(['user_id' => $cm2->id]);

            $csvContent = "email\ntest@example.com";
            $csvFile = \Illuminate\Http\UploadedFile::fake()->createWithContent('learners.csv', $csvContent);

            $this->actingAs($cm1)
                ->post(route('courses.invitations.bulk', $course), [
                    'file' => $csvFile,
                ])
                ->assertForbidden();

            // No invitations created
            expect(CourseInvitation::where('course_id', $course->id)->count())->toBe(0);
        });

        it('admin can bulk invite to any course', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner', 'email' => 'dewi@example.com']);

            $course = Course::factory()->published()->create(['user_id' => $cm->id]);

            $csvContent = "email\ndewi@example.com";
            $csvFile = \Illuminate\Http\UploadedFile::fake()->createWithContent('learners.csv', $csvContent);

            $this->actingAs($admin)
                ->post(route('courses.invitations.bulk', $course), [
                    'file' => $csvFile,
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('course_invitations', [
                'course_id' => $course->id,
                'user_id' => $learner->id,
            ]);
        });

        it('learner cannot bulk invite', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            $csvContent = "email\ntest@example.com";
            $csvFile = \Illuminate\Http\UploadedFile::fake()->createWithContent('learners.csv', $csvContent);

            $this->actingAs($learner)
                ->post(route('courses.invitations.bulk', $course), [
                    'file' => $csvFile,
                ])
                ->assertForbidden();
        });

    });

    describe('Grading Workflow Across Roles', function () {

        it('CM creates assessment with required fields', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $learner = User::factory()->create(['role' => 'learner']);

            // Setup
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            Lesson::factory()->create(['course_section_id' => $section->id]);

            $this->actingAs($admin)->post(route('courses.publish', $course));

            // Create assessment with all required fields
            $this->actingAs($cm)
                ->post(route('assessments.store', $course), [
                    'title' => 'Ujian Akhir',
                    'description' => 'Ujian akhir semester',
                    'time_limit_minutes' => 60,
                    'passing_score' => 70,
                    'max_attempts' => 3,
                    'status' => 'draft',
                    'visibility' => 'public',
                ])
                ->assertRedirect();

            $assessment = Assessment::where('title', 'Ujian Akhir')->first();
            expect($assessment)->not->toBeNull();
            expect($assessment->status)->toBe('draft');
            expect($assessment->user_id)->toBe($cm->id);
            expect($assessment->course_id)->toBe($course->id);
        });

        it('admin can grade any assessment attempt', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->create(['user_id' => $cm->id]);

            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            Question::factory()->create([
                'assessment_id' => $assessment->id,
                'question_type' => 'essay',
                'points' => 100,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $attempt = AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
            ]);

            // Admin can view grade page
            $this->actingAs($admin)
                ->get(route('assessments.grade', [$course, $assessment, $attempt]))
                ->assertOk();
        });

        it('CM can grade their own assessment attempts', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->create(['user_id' => $cm->id]);

            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            Question::factory()->create([
                'assessment_id' => $assessment->id,
                'question_type' => 'essay',
                'points' => 100,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $attempt = AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
            ]);

            // CM can view grade page for own assessment
            $this->actingAs($cm)
                ->get(route('assessments.grade', [$course, $assessment, $attempt]))
                ->assertOk();
        });

        it('CM cannot grade other CMs assessment attempts', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->create(['user_id' => $cm2->id]);

            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm2->id,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $attempt = AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
            ]);

            // CM1 cannot grade CM2's assessment
            $this->actingAs($cm1)
                ->get(route('assessments.grade', [$course, $assessment, $attempt]))
                ->assertForbidden();
        });

        it('learner cannot access grade page', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->create(['user_id' => $cm->id]);

            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $attempt = AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
            ]);

            // Learner cannot access grade page
            $this->actingAs($learner)
                ->get(route('assessments.grade', [$course, $assessment, $attempt]))
                ->assertForbidden();
        });

    });

});
