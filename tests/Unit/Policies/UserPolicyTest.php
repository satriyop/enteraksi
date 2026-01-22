<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\UserPolicy;

/**
 * Unit tests for UserPolicy.
 *
 * These tests verify authorization logic for user management operations.
 * Only lms_admin role can manage users.
 */
beforeEach(function () {
    $this->policy = new UserPolicy;

    $this->lmsAdmin = User::factory()->create(['role' => 'lms_admin']);
    $this->otherAdmin = User::factory()->create(['role' => 'lms_admin']);
    $this->contentManager = User::factory()->create(['role' => 'content_manager']);
    $this->trainer = User::factory()->create(['role' => 'trainer']);
    $this->learner = User::factory()->create(['role' => 'learner']);
});

// ========== viewAny ==========

it('allows lms_admin to view any users', function () {
    expect($this->policy->viewAny($this->lmsAdmin))->toBeTrue();
});

it('denies content_manager to view any users', function () {
    expect($this->policy->viewAny($this->contentManager))->toBeFalse();
});

it('denies trainer to view any users', function () {
    expect($this->policy->viewAny($this->trainer))->toBeFalse();
});

it('denies learner to view any users', function () {
    expect($this->policy->viewAny($this->learner))->toBeFalse();
});

// ========== view ==========

it('allows lms_admin to view any user', function () {
    expect($this->policy->view($this->lmsAdmin, $this->contentManager))->toBeTrue();
    expect($this->policy->view($this->lmsAdmin, $this->learner))->toBeTrue();
});

it('denies content_manager to view user', function () {
    expect($this->policy->view($this->contentManager, $this->learner))->toBeFalse();
});

it('denies learner to view user', function () {
    expect($this->policy->view($this->learner, $this->contentManager))->toBeFalse();
});

// ========== create ==========

it('allows lms_admin to create user', function () {
    expect($this->policy->create($this->lmsAdmin))->toBeTrue();
});

it('denies content_manager to create user', function () {
    expect($this->policy->create($this->contentManager))->toBeFalse();
});

it('denies trainer to create user', function () {
    expect($this->policy->create($this->trainer))->toBeFalse();
});

it('denies learner to create user', function () {
    expect($this->policy->create($this->learner))->toBeFalse();
});

// ========== update ==========

it('allows lms_admin to update any user', function () {
    expect($this->policy->update($this->lmsAdmin, $this->contentManager))->toBeTrue();
    expect($this->policy->update($this->lmsAdmin, $this->learner))->toBeTrue();
    expect($this->policy->update($this->lmsAdmin, $this->otherAdmin))->toBeTrue();
});

it('allows lms_admin to update self', function () {
    expect($this->policy->update($this->lmsAdmin, $this->lmsAdmin))->toBeTrue();
});

it('denies content_manager to update user', function () {
    expect($this->policy->update($this->contentManager, $this->learner))->toBeFalse();
});

it('denies learner to update user', function () {
    expect($this->policy->update($this->learner, $this->contentManager))->toBeFalse();
});

// ========== delete ==========

it('allows lms_admin to delete other users', function () {
    expect($this->policy->delete($this->lmsAdmin, $this->contentManager))->toBeTrue();
    expect($this->policy->delete($this->lmsAdmin, $this->learner))->toBeTrue();
    expect($this->policy->delete($this->lmsAdmin, $this->otherAdmin))->toBeTrue();
});

it('denies lms_admin to delete self', function () {
    expect($this->policy->delete($this->lmsAdmin, $this->lmsAdmin))->toBeFalse();
});

it('denies content_manager to delete user', function () {
    expect($this->policy->delete($this->contentManager, $this->learner))->toBeFalse();
});

it('denies trainer to delete user', function () {
    expect($this->policy->delete($this->trainer, $this->learner))->toBeFalse();
});

it('denies learner to delete user', function () {
    expect($this->policy->delete($this->learner, $this->contentManager))->toBeFalse();
});
