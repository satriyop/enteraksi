<?php

use App\Domain\Enrollment\Events\UserReenrolled;
use App\Domain\Enrollment\States\ActiveState;
use App\Domain\Enrollment\States\DroppedState;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\Event;

describe('Re-enrollment Flow', function () {
    describe('reenroll endpoint', function () {
        it('allows dropped user to re-enroll with preserved progress', function () {
            Event::fake([UserReenrolled::class]);

            $user = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            // Create a dropped enrollment with progress
            $enrollment = Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'status' => DroppedState::$name,
                'progress_percentage' => 50,
            ]);

            $response = $this->actingAs($user)
                ->post("/courses/{$course->id}/reenroll", [
                    'preserve_progress' => true,
                ]);

            $response->assertRedirect("/courses/{$course->id}");
            $response->assertSessionHas('success');

            $enrollment->refresh();
            expect($enrollment->status->getValue())->toBe(ActiveState::$name);
            expect((float) $enrollment->progress_percentage)->toBe(50.0);

            Event::assertDispatched(UserReenrolled::class, function ($event) use ($enrollment) {
                return $event->enrollment->id === $enrollment->id
                    && $event->progressPreserved === true;
            });
        });

        it('allows dropped user to re-enroll with reset progress', function () {
            Event::fake([UserReenrolled::class]);

            $user = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            // Create a dropped enrollment with progress
            $enrollment = Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'status' => DroppedState::$name,
                'progress_percentage' => 50,
                'started_at' => now()->subDays(5),
                'last_lesson_id' => 1,
            ]);

            $response = $this->actingAs($user)
                ->post("/courses/{$course->id}/reenroll", [
                    'preserve_progress' => false,
                ]);

            $response->assertRedirect("/courses/{$course->id}");
            $response->assertSessionHas('success');

            $enrollment->refresh();
            expect($enrollment->status->getValue())->toBe(ActiveState::$name);
            expect((float) $enrollment->progress_percentage)->toBe(0.0);
            expect($enrollment->started_at)->toBeNull();
            expect($enrollment->last_lesson_id)->toBeNull();

            Event::assertDispatched(UserReenrolled::class, function ($event) {
                return $event->progressPreserved === false;
            });
        });

        it('returns error when no dropped enrollment exists', function () {
            $user = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            // No enrollment exists
            $response = $this->actingAs($user)
                ->post("/courses/{$course->id}/reenroll", [
                    'preserve_progress' => true,
                ]);

            $response->assertRedirect();
            $response->assertSessionHas('error');
        });

        it('returns error when enrollment is not dropped', function () {
            $user = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            // Create an active enrollment
            Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            $response = $this->actingAs($user)
                ->post("/courses/{$course->id}/reenroll", [
                    'preserve_progress' => true,
                ]);

            $response->assertRedirect();
            $response->assertSessionHas('error');
        });

        it('defaults to preserving progress when not specified', function () {
            Event::fake([UserReenrolled::class]);

            $user = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            $enrollment = Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'status' => DroppedState::$name,
                'progress_percentage' => 75,
            ]);

            $response = $this->actingAs($user)
                ->post("/courses/{$course->id}/reenroll");

            $enrollment->refresh();
            expect($enrollment->status->getValue())->toBe(ActiveState::$name);
            expect((float) $enrollment->progress_percentage)->toBe(75.0);

            Event::assertDispatched(UserReenrolled::class, function ($event) {
                return $event->progressPreserved === true;
            });
        });
    });

    describe('regular enroll endpoint handles dropped enrollment', function () {
        it('automatically reactivates dropped enrollment', function () {
            Event::fake([UserReenrolled::class]);

            $user = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            // Create a dropped enrollment
            $enrollment = Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'status' => DroppedState::$name,
                'progress_percentage' => 30,
            ]);

            $response = $this->actingAs($user)
                ->post("/courses/{$course->id}/enroll");

            $response->assertRedirect("/courses/{$course->id}");

            $enrollment->refresh();
            expect($enrollment->status->getValue())->toBe(ActiveState::$name);
            // Regular enroll preserves progress by default
            expect((float) $enrollment->progress_percentage)->toBe(30.0);
        });
    });

    describe('course detail page shows correct enrollment state', function () {
        it('shows dropped enrollment data', function () {
            $user = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            $enrollment = Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'status' => DroppedState::$name,
                'progress_percentage' => 40,
            ]);

            $response = $this->actingAs($user)
                ->get("/courses/{$course->id}");

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->component('courses/Detail')
                ->has('enrollment')
                ->where('enrollment.status', DroppedState::$name)
                ->where('enrollment.progress_percentage', 40)
            );
        });

        it('shows completed enrollment data', function () {
            $user = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            $enrollment = Enrollment::factory()->completed()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'progress_percentage' => 100,
            ]);

            $response = $this->actingAs($user)
                ->get("/courses/{$course->id}");

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->component('courses/Detail')
                ->has('enrollment')
                ->where('enrollment.status', 'completed')
                ->where('enrollment.progress_percentage', 100)
            );
        });
    });
});
