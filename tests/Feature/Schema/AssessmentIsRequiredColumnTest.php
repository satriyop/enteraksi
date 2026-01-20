<?php

use App\Models\Assessment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Assessment is_required column', function () {

    it('allows creating required assessment', function () {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->id]);

        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'is_required' => true,
        ]);

        expect($assessment->is_required)->toBeTrue();
    });

    it('allows creating optional assessment', function () {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->id]);

        $assessment = Assessment::factory()->optional()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
        ]);

        expect($assessment->is_required)->toBeFalse();
    });

    it('defaults to required in factory', function () {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->id]);

        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
        ]);

        expect($assessment->is_required)->toBeTrue();
    });

    it('defaults to required in database', function () {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->id]);

        // Create without explicitly setting is_required
        $assessment = Assessment::create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'title' => 'Test Assessment',
            'slug' => 'test-assessment-'.uniqid(),
            'passing_score' => 70,
            'max_attempts' => 1,
        ]);

        // Refresh to get database defaults
        $assessment->refresh();

        expect($assessment->is_required)->toBeTrue();
    });

    it('can query required assessments', function () {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->id]);

        Assessment::factory()->count(3)->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'is_required' => true,
        ]);
        Assessment::factory()->count(2)->optional()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
        ]);

        expect(Assessment::where('is_required', true)->count())->toBe(3);
        expect(Assessment::where('is_required', false)->count())->toBe(2);
    });

    it('casts is_required to boolean', function () {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->id]);

        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'is_required' => true,
        ]);

        expect($assessment->is_required)->toBeBool();

        $assessment->update(['is_required' => false]);
        $assessment->refresh();

        expect($assessment->is_required)->toBeBool();
        expect($assessment->is_required)->toBeFalse();
    });

    it('supports required factory state', function () {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->id]);

        $assessment = Assessment::factory()->required()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
        ]);

        expect($assessment->is_required)->toBeTrue();
    });
});
