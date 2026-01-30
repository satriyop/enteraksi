<?php

namespace Tests\Unit\Policies;

use App\Domain\Enrollment\DTOs\EnrollmentContext;
use App\Models\Course;
use App\Models\CourseRating;
use App\Models\Enrollment;
use App\Models\User;
use App\Policies\CourseRatingPolicy;

/**
 * Unit tests for CourseRatingPolicy.
 *
 * Tests verify authorization logic for course rating operations.
 * Users must be enrolled to create ratings, and can only rate once per course.
 * Only rating owners or admins can update/delete ratings.
 */
beforeEach(function () {
    $this->policy = new CourseRatingPolicy;

    $this->lmsAdmin = User::factory()->create(['role' => 'lms_admin']);
    $this->learner = User::factory()->create(['role' => 'learner']);
    $this->otherLearner = User::factory()->create(['role' => 'learner']);
    $this->courseOwner = User::factory()->create(['role' => 'content_manager']);

    $this->course = Course::factory()->published()->create(['user_id' => $this->courseOwner->id]);

    // Enroll learner
    Enrollment::factory()->active()->create([
        'user_id' => $this->learner->id,
        'course_id' => $this->course->id,
    ]);

    // Create rating from learner
    $this->rating = CourseRating::factory()->create([
        'course_id' => $this->course->id,
        'user_id' => $this->learner->id,
        'rating' => 5,
    ]);
});

// ========== create ==========

it('allows enrolled user to create rating when no existing rating', function () {
    $context = EnrollmentContext::fromData(
        isActivelyEnrolled: true,
        hasPendingInvitation: false,
        hasAnyEnrollment: true,
    );

    expect($this->policy->create($this->learner, $this->course, $context, hasExistingRating: false))->toBeTrue();
});

it('denies creating rating when user is not enrolled', function () {
    $context = EnrollmentContext::fromData(
        isActivelyEnrolled: false,
        hasPendingInvitation: false,
        hasAnyEnrollment: false,
    );

    expect($this->policy->create($this->otherLearner, $this->course, $context, hasExistingRating: false))->toBeFalse();
});

it('denies creating rating when user already rated the course', function () {
    $context = EnrollmentContext::fromData(
        isActivelyEnrolled: true,
        hasPendingInvitation: false,
        hasAnyEnrollment: true,
    );

    expect($this->policy->create($this->learner, $this->course, $context, hasExistingRating: true))->toBeFalse();
});

it('allows creating rating when enrollment exists even if not currently active', function () {
    // Policy checks hasAnyEnrollment, not specifically isActivelyEnrolled
    // This allows completed users to rate
    $context = EnrollmentContext::fromData(
        isActivelyEnrolled: false,
        hasPendingInvitation: false,
        hasAnyEnrollment: true,
    );

    expect($this->policy->create($this->learner, $this->course, $context, hasExistingRating: false))->toBeTrue();
});

it('allows completed enrollment to create rating', function () {
    // Completed enrollment still has hasAnyEnrollment = true
    $context = EnrollmentContext::fromData(
        isActivelyEnrolled: false,
        hasPendingInvitation: false,
        hasAnyEnrollment: true,
    );

    // Policy checks hasAnyEnrollment, not specifically active
    expect($this->policy->create($this->learner, $this->course, $context, hasExistingRating: false))->toBeTrue();
});

// ========== update ==========

it('allows rating owner to update their rating', function () {
    expect($this->policy->update($this->learner, $this->rating))->toBeTrue();
});

it('denies other user to update rating', function () {
    expect($this->policy->update($this->otherLearner, $this->rating))->toBeFalse();
});

it('denies lms_admin to update other users rating', function () {
    expect($this->policy->update($this->lmsAdmin, $this->rating))->toBeFalse();
});

it('denies course owner to update ratings on their course', function () {
    expect($this->policy->update($this->courseOwner, $this->rating))->toBeFalse();
});

// ========== delete ==========

it('allows rating owner to delete their rating', function () {
    expect($this->policy->delete($this->learner, $this->rating))->toBeTrue();
});

it('allows lms_admin to delete any rating', function () {
    expect($this->policy->delete($this->lmsAdmin, $this->rating))->toBeTrue();
});

it('denies other user to delete rating', function () {
    expect($this->policy->delete($this->otherLearner, $this->rating))->toBeFalse();
});

it('denies course owner to delete ratings on their course', function () {
    expect($this->policy->delete($this->courseOwner, $this->rating))->toBeFalse();
});

it('allows lms_admin to delete rating from any user', function () {
    $adminRating = CourseRating::factory()->create([
        'course_id' => $this->course->id,
        'user_id' => $this->lmsAdmin->id,
    ]);

    expect($this->policy->delete($this->lmsAdmin, $adminRating))->toBeTrue();
});
