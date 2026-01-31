<?php

namespace Tests\Unit\Policies;

use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\User;
use App\Policies\CourseInvitationPolicy;

/**
 * Unit tests for CourseInvitationPolicy.
 *
 * Tests verify authorization logic for course invitation management.
 * Course owners, admins, and trainers can manage invitations.
 * Invitees can view their own invitations.
 */
beforeEach(function () {
    $this->policy = new CourseInvitationPolicy;

    $this->lmsAdmin = User::factory()->create(['role' => 'lms_admin']);
    $this->contentManager = User::factory()->create(['role' => 'content_manager']);
    $this->trainer = User::factory()->create(['role' => 'trainer']);
    $this->learner = User::factory()->create(['role' => 'learner']);
    $this->courseOwner = User::factory()->create(['role' => 'content_manager']);
    $this->invitee = User::factory()->create(['role' => 'learner']);

    $this->course = Course::factory()->published()->create(['user_id' => $this->courseOwner->id]);
    $this->invitation = CourseInvitation::factory()->pending()->create([
        'course_id' => $this->course->id,
        'user_id' => $this->invitee->id,
        'invited_by' => $this->courseOwner->id,
    ]);
});

// ========== viewAny ==========

it('allows course owner to view any invitations for their course', function () {
    expect($this->policy->viewAny($this->courseOwner, $this->course))->toBeTrue();
});

it('allows lms_admin to view any invitations', function () {
    expect($this->policy->viewAny($this->lmsAdmin, $this->course))->toBeTrue();
});

it('allows trainer to view any invitations', function () {
    expect($this->policy->viewAny($this->trainer, $this->course))->toBeTrue();
});

it('denies non-owner content_manager to view invitations', function () {
    expect($this->policy->viewAny($this->contentManager, $this->course))->toBeFalse();
});

it('denies learner to view any invitations', function () {
    expect($this->policy->viewAny($this->learner, $this->course))->toBeFalse();
});

// ========== view ==========

it('allows invitee to view their own invitation', function () {
    expect($this->policy->view($this->invitee, $this->invitation))->toBeTrue();
});

it('allows course owner to view invitations for their course', function () {
    expect($this->policy->view($this->courseOwner, $this->invitation))->toBeTrue();
});

it('allows lms_admin to view any invitation', function () {
    expect($this->policy->view($this->lmsAdmin, $this->invitation))->toBeTrue();
});

it('allows trainer to view any invitation', function () {
    expect($this->policy->view($this->trainer, $this->invitation))->toBeTrue();
});

it('denies other learners to view invitation', function () {
    $otherLearner = User::factory()->create(['role' => 'learner']);
    expect($this->policy->view($otherLearner, $this->invitation))->toBeFalse();
});

it('denies non-owner content_manager to view invitation', function () {
    expect($this->policy->view($this->contentManager, $this->invitation))->toBeFalse();
});

// ========== create ==========

it('allows course owner to create invitations for their course', function () {
    expect($this->policy->create($this->courseOwner, $this->course))->toBeTrue();
});

it('allows lms_admin to create invitations for any course', function () {
    expect($this->policy->create($this->lmsAdmin, $this->course))->toBeTrue();
});

it('allows trainer to create invitations', function () {
    expect($this->policy->create($this->trainer, $this->course))->toBeTrue();
});

it('denies non-owner content_manager to create invitations', function () {
    expect($this->policy->create($this->contentManager, $this->course))->toBeFalse();
});

it('denies learner to create invitations', function () {
    expect($this->policy->create($this->learner, $this->course))->toBeFalse();
});

// ========== delete ==========

it('allows inviter to delete their pending invitation', function () {
    expect($this->policy->delete($this->courseOwner, $this->invitation))->toBeTrue();
});

it('allows lms_admin to delete any pending invitation', function () {
    expect($this->policy->delete($this->lmsAdmin, $this->invitation))->toBeTrue();
});

it('denies deleting accepted invitation', function () {
    $acceptedInvitation = CourseInvitation::factory()->accepted()->create([
        'course_id' => $this->course->id,
        'invited_by' => $this->courseOwner->id,
    ]);

    expect($this->policy->delete($this->courseOwner, $acceptedInvitation))->toBeFalse();
});

it('denies deleting declined invitation', function () {
    $declinedInvitation = CourseInvitation::factory()->declined()->create([
        'course_id' => $this->course->id,
        'invited_by' => $this->courseOwner->id,
    ]);

    expect($this->policy->delete($this->courseOwner, $declinedInvitation))->toBeFalse();
});

it('denies non-inviter to delete invitation', function () {
    $otherManager = User::factory()->create(['role' => 'content_manager']);
    expect($this->policy->delete($otherManager, $this->invitation))->toBeFalse();
});

it('denies invitee to delete their own invitation', function () {
    expect($this->policy->delete($this->invitee, $this->invitation))->toBeFalse();
});

it('denies trainer who is not inviter to delete invitation', function () {
    $otherTrainer = User::factory()->create(['role' => 'trainer']);
    expect($this->policy->delete($otherTrainer, $this->invitation))->toBeFalse();
});
