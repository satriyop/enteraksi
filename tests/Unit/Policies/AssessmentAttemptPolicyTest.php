<?php

namespace Tests\Unit\Policies;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\User;
use App\Policies\AssessmentAttemptPolicy;

/**
 * Unit tests for AssessmentAttemptPolicy.
 *
 * Tests verify authorization logic for assessment attempt operations.
 * Admins can do everything, content managers can view/grade their own assessments,
 * and learners can only create and view their own attempts.
 */
beforeEach(function () {
    $this->policy = new AssessmentAttemptPolicy;

    $this->lmsAdmin = User::factory()->create(['role' => 'lms_admin']);
    $this->contentManager = User::factory()->create(['role' => 'content_manager']);
    $this->otherContentManager = User::factory()->create(['role' => 'content_manager']);
    $this->trainer = User::factory()->create(['role' => 'trainer']);
    $this->learner = User::factory()->create(['role' => 'learner']);
    $this->otherLearner = User::factory()->create(['role' => 'learner']);

    // Create course, assessment, and attempt
    $this->course = Course::factory()->published()->create(['user_id' => $this->contentManager->id]);
    $this->assessment = Assessment::factory()->create([
        'course_id' => $this->course->id,
        'user_id' => $this->contentManager->id,
    ]);
    $this->attempt = AssessmentAttempt::factory()->inProgress()->create([
        'assessment_id' => $this->assessment->id,
        'user_id' => $this->learner->id,
    ]);
});

// ========== viewAny ==========

it('allows lms_admin to view any attempts', function () {
    expect($this->policy->viewAny($this->lmsAdmin))->toBeTrue();
});

it('allows content_manager to view any attempts', function () {
    expect($this->policy->viewAny($this->contentManager))->toBeTrue();
});

it('denies trainer to view any attempts', function () {
    expect($this->policy->viewAny($this->trainer))->toBeFalse();
});

it('denies learner to view any attempts', function () {
    expect($this->policy->viewAny($this->learner))->toBeFalse();
});

// ========== view ==========

it('allows lms_admin to view any attempt', function () {
    expect($this->policy->view($this->lmsAdmin, $this->attempt))->toBeTrue();
});

it('allows content_manager to view attempts for their own assessment', function () {
    expect($this->policy->view($this->contentManager, $this->attempt))->toBeTrue();
});

it('denies content_manager to view attempts for other assessments', function () {
    expect($this->policy->view($this->otherContentManager, $this->attempt))->toBeFalse();
});

it('allows learner to view their own attempt', function () {
    expect($this->policy->view($this->learner, $this->attempt))->toBeTrue();
});

it('denies learner to view other learners attempts', function () {
    expect($this->policy->view($this->otherLearner, $this->attempt))->toBeFalse();
});

// ========== create ==========

it('allows learner to create attempt', function () {
    expect($this->policy->create($this->learner))->toBeTrue();
});

it('denies lms_admin to create attempt', function () {
    expect($this->policy->create($this->lmsAdmin))->toBeFalse();
});

it('denies content_manager to create attempt', function () {
    expect($this->policy->create($this->contentManager))->toBeFalse();
});

it('denies trainer to create attempt', function () {
    expect($this->policy->create($this->trainer))->toBeFalse();
});

// ========== update ==========

it('allows lms_admin to update attempt', function () {
    expect($this->policy->update($this->lmsAdmin, $this->attempt))->toBeTrue();
});

it('denies content_manager to update attempt', function () {
    expect($this->policy->update($this->contentManager, $this->attempt))->toBeFalse();
});

it('denies learner to update their own attempt', function () {
    expect($this->policy->update($this->learner, $this->attempt))->toBeFalse();
});

// ========== delete ==========

it('allows lms_admin to delete attempt', function () {
    expect($this->policy->delete($this->lmsAdmin, $this->attempt))->toBeTrue();
});

it('denies content_manager to delete attempt', function () {
    expect($this->policy->delete($this->contentManager, $this->attempt))->toBeFalse();
});

it('denies learner to delete their own attempt', function () {
    expect($this->policy->delete($this->learner, $this->attempt))->toBeFalse();
});

// ========== restore/forceDelete ==========

it('allows lms_admin to restore attempt', function () {
    expect($this->policy->restore($this->lmsAdmin, $this->attempt))->toBeTrue();
});

it('allows lms_admin to force delete attempt', function () {
    expect($this->policy->forceDelete($this->lmsAdmin, $this->attempt))->toBeTrue();
});

it('denies content_manager to restore attempt', function () {
    expect($this->policy->restore($this->contentManager, $this->attempt))->toBeFalse();
});

// ========== viewAttempt (with context) ==========

it('denies viewing attempt when assessment does not belong to course', function () {
    $otherCourse = Course::factory()->create();
    expect($this->policy->viewAttempt($this->lmsAdmin, $this->attempt, $this->assessment, $otherCourse))->toBeFalse();
});

it('denies viewing attempt when attempt does not belong to assessment', function () {
    $otherAssessment = Assessment::factory()->create(['course_id' => $this->course->id]);
    expect($this->policy->viewAttempt($this->lmsAdmin, $this->attempt, $otherAssessment, $this->course))->toBeFalse();
});

it('allows lms_admin to view attempt with valid context', function () {
    expect($this->policy->viewAttempt($this->lmsAdmin, $this->attempt, $this->assessment, $this->course))->toBeTrue();
});

it('allows content_manager to view attempt for their assessment', function () {
    expect($this->policy->viewAttempt($this->contentManager, $this->attempt, $this->assessment, $this->course))->toBeTrue();
});

it('allows learner to view their own attempt with context', function () {
    expect($this->policy->viewAttempt($this->learner, $this->attempt, $this->assessment, $this->course))->toBeTrue();
});

// ========== submitAttempt ==========

it('allows learner to submit their own in-progress attempt', function () {
    expect($this->policy->submitAttempt($this->learner, $this->attempt, $this->assessment, $this->course))->toBeTrue();
});

it('denies submitting attempt that does not belong to user', function () {
    expect($this->policy->submitAttempt($this->otherLearner, $this->attempt, $this->assessment, $this->course))->toBeFalse();
});

it('denies submitting already submitted attempt', function () {
    $submittedAttempt = AssessmentAttempt::factory()->submitted()->create([
        'assessment_id' => $this->assessment->id,
        'user_id' => $this->learner->id,
    ]);

    expect($this->policy->submitAttempt($this->learner, $submittedAttempt, $this->assessment, $this->course))->toBeFalse();
});

it('denies submitting when assessment does not belong to course', function () {
    $otherCourse = Course::factory()->create();
    expect($this->policy->submitAttempt($this->learner, $this->attempt, $this->assessment, $otherCourse))->toBeFalse();
});

// ========== grade ==========

it('allows lms_admin to grade submitted attempt', function () {
    $submittedAttempt = AssessmentAttempt::factory()->submitted()->create([
        'assessment_id' => $this->assessment->id,
        'user_id' => $this->learner->id,
    ]);

    expect($this->policy->grade($this->lmsAdmin, $submittedAttempt, $this->assessment, $this->course))->toBeTrue();
});

it('allows content_manager to grade attempt for their assessment', function () {
    $submittedAttempt = AssessmentAttempt::factory()->submitted()->create([
        'assessment_id' => $this->assessment->id,
        'user_id' => $this->learner->id,
    ]);

    expect($this->policy->grade($this->contentManager, $submittedAttempt, $this->assessment, $this->course))->toBeTrue();
});

it('denies grading in-progress attempt', function () {
    expect($this->policy->grade($this->lmsAdmin, $this->attempt, $this->assessment, $this->course))->toBeFalse();
});

it('denies content_manager to grade attempt for other assessments', function () {
    $submittedAttempt = AssessmentAttempt::factory()->submitted()->create([
        'assessment_id' => $this->assessment->id,
        'user_id' => $this->learner->id,
    ]);

    expect($this->policy->grade($this->otherContentManager, $submittedAttempt, $this->assessment, $this->course))->toBeFalse();
});

it('denies learner to grade attempt', function () {
    $submittedAttempt = AssessmentAttempt::factory()->submitted()->create([
        'assessment_id' => $this->assessment->id,
        'user_id' => $this->learner->id,
    ]);

    expect($this->policy->grade($this->learner, $submittedAttempt, $this->assessment, $this->course))->toBeFalse();
});
