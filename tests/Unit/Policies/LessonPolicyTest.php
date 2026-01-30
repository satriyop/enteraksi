<?php

namespace Tests\Unit\Policies;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Policies\LessonPolicy;
use Illuminate\Support\Facades\Gate;

/**
 * Unit tests for LessonPolicy.
 *
 * Tests verify authorization logic for lesson management operations.
 * Managers and course owners can manage lessons, while enrolled learners
 * can only view lessons in courses they have access to.
 *
 * Note: Methods that delegate to CoursePolicy via Gate::allows() require
 * actingAs() so the inner Gate call resolves the correct user.
 */
beforeEach(function () {
    $this->policy = new LessonPolicy;

    $this->lmsAdmin = User::factory()->create(['role' => 'lms_admin']);
    $this->contentManager = User::factory()->create(['role' => 'content_manager']);
    $this->trainer = User::factory()->create(['role' => 'trainer']);
    $this->learner = User::factory()->create(['role' => 'learner']);
    $this->courseOwner = User::factory()->create(['role' => 'content_manager']);

    // Create DRAFT course for owner to be able to update
    $this->course = Course::factory()->draft()->create(['user_id' => $this->courseOwner->id]);
    $this->section = CourseSection::factory()->create(['course_id' => $this->course->id]);
    $this->lesson = Lesson::factory()->create(['course_section_id' => $this->section->id]);

    // Separate published course for view tests with enrollments
    $this->publishedCourse = Course::factory()->published()->create(['user_id' => $this->courseOwner->id]);
    $this->publishedSection = CourseSection::factory()->create(['course_id' => $this->publishedCourse->id]);
    $this->publishedLesson = Lesson::factory()->create(['course_section_id' => $this->publishedSection->id]);
});

// ========== viewAny ==========

it('allows lms_admin to view any lessons', function () {
    expect($this->policy->viewAny($this->lmsAdmin))->toBeTrue();
});

it('allows content_manager to view any lessons', function () {
    expect($this->policy->viewAny($this->contentManager))->toBeTrue();
});

it('allows trainer to view any lessons', function () {
    expect($this->policy->viewAny($this->trainer))->toBeTrue();
});

it('denies learner to view any lessons', function () {
    expect($this->policy->viewAny($this->learner))->toBeFalse();
});

// ========== view ==========

it('allows lms_admin to view lesson', function () {
    expect($this->policy->view($this->lmsAdmin, $this->lesson))->toBeTrue();
});

it('allows content_manager to view lesson', function () {
    expect($this->policy->view($this->contentManager, $this->lesson))->toBeTrue();
});

it('allows trainer to view lesson', function () {
    expect($this->policy->view($this->trainer, $this->lesson))->toBeTrue();
});

it('allows course owner to view their lesson', function () {
    expect($this->policy->view($this->courseOwner, $this->lesson))->toBeTrue();
});

it('allows actively enrolled learner to view lesson', function () {
    Enrollment::factory()->active()->create([
        'user_id' => $this->learner->id,
        'course_id' => $this->publishedCourse->id,
    ]);

    expect($this->policy->view($this->learner, $this->publishedLesson))->toBeTrue();
});

it('allows completed learner to view lesson', function () {
    Enrollment::factory()->completed()->create([
        'user_id' => $this->learner->id,
        'course_id' => $this->publishedCourse->id,
    ]);

    expect($this->policy->view($this->learner, $this->publishedLesson))->toBeTrue();
});

it('denies dropped learner to view lesson', function () {
    Enrollment::factory()->dropped()->create([
        'user_id' => $this->learner->id,
        'course_id' => $this->publishedCourse->id,
    ]);

    expect($this->policy->view($this->learner, $this->publishedLesson))->toBeFalse();
});

it('denies unenrolled learner to view lesson', function () {
    expect($this->policy->view($this->learner, $this->lesson))->toBeFalse();
});

// ========== create (delegates to CoursePolicy::update via Gate) ==========

it('allows course owner to create lesson in their section', function () {
    $this->actingAs($this->courseOwner);
    expect(Gate::check('create', [Lesson::class, $this->section]))->toBeTrue();
});

it('allows lms_admin to create lesson in any section', function () {
    $this->actingAs($this->lmsAdmin);
    expect(Gate::check('create', [Lesson::class, $this->section]))->toBeTrue();
});

it('denies learner to create lesson', function () {
    $this->actingAs($this->learner);
    expect(Gate::denies('create', [Lesson::class, $this->section]))->toBeTrue();
});

// ========== update (delegates to CoursePolicy::update via Gate) ==========

it('allows course owner to update their lesson', function () {
    $this->actingAs($this->courseOwner);
    expect(Gate::check('update', $this->lesson))->toBeTrue();
});

it('allows lms_admin to update any lesson', function () {
    $this->actingAs($this->lmsAdmin);
    expect(Gate::check('update', $this->lesson))->toBeTrue();
});

it('denies non-owner content_manager to update lesson', function () {
    $otherManager = User::factory()->create(['role' => 'content_manager']);
    $this->actingAs($otherManager);
    expect(Gate::denies('update', $this->lesson))->toBeTrue();
});

it('denies learner to update lesson', function () {
    $this->actingAs($this->learner);
    expect(Gate::denies('update', $this->lesson))->toBeTrue();
});

// ========== delete (delegates to CoursePolicy::update via Gate) ==========

it('allows course owner to delete their lesson', function () {
    $this->actingAs($this->courseOwner);
    expect(Gate::check('delete', $this->lesson))->toBeTrue();
});

it('allows lms_admin to delete any lesson', function () {
    $this->actingAs($this->lmsAdmin);
    expect(Gate::check('delete', $this->lesson))->toBeTrue();
});

it('denies learner to delete lesson', function () {
    $this->actingAs($this->learner);
    expect(Gate::denies('delete', $this->lesson))->toBeTrue();
});

// ========== reorder (delegates to CoursePolicy::update via Gate) ==========

it('allows course owner to reorder lessons in their section', function () {
    $this->actingAs($this->courseOwner);
    expect(Gate::check('reorder', [Lesson::class, $this->section]))->toBeTrue();
});

it('allows lms_admin to reorder lessons in any section', function () {
    $this->actingAs($this->lmsAdmin);
    expect(Gate::check('reorder', [Lesson::class, $this->section]))->toBeTrue();
});

it('denies learner to reorder lessons', function () {
    $this->actingAs($this->learner);
    expect(Gate::denies('reorder', [Lesson::class, $this->section]))->toBeTrue();
});
