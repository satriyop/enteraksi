<?php

use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();
    $this->user = User::factory()->create();
});

describe('Learning Path Enrollment Feature', function () {
    describe('GET /learner/learning-paths', function () {
        it('shows enrolled learning paths', function () {
            $this->actingAs($this->user);

            LearningPathEnrollment::factory()->active()->count(3)->create([
                'user_id' => $this->user->id,
            ]);

            $response = $this->get(route('learner.learning-paths.index'));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->has('enrollments.data', 3)
            );
        });

        it('requires authentication', function () {
            $response = $this->get(route('learner.learning-paths.index'));

            $response->assertRedirect(route('login'));
        });
    });

    describe('GET /learner/learning-paths/browse', function () {
        it('shows published learning paths', function () {
            $this->actingAs($this->user);

            LearningPath::factory()->published()->count(5)->create();
            LearningPath::factory()->unpublished()->count(2)->create();

            $response = $this->get(route('learner.learning-paths.browse'));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->has('learningPaths.data', 5)
            );
        });

        it('marks enrolled paths', function () {
            $this->actingAs($this->user);

            $enrolledPath = LearningPath::factory()->published()->create();
            LearningPathEnrollment::factory()->active()->create([
                'user_id' => $this->user->id,
                'learning_path_id' => $enrolledPath->id,
            ]);

            LearningPath::factory()->published()->count(2)->create();

            $response = $this->get(route('learner.learning-paths.browse'));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->has('enrolledPathIds', 1)
                ->where('enrolledPathIds.0', $enrolledPath->id)
            );
        });
    });

    describe('POST /learner/learning-paths/{learningPath}/enroll', function () {
        it('enrolls user in learning path', function () {
            $this->actingAs($this->user);

            $path = LearningPath::factory()->published()->create();
            $courses = Course::factory()->published()->count(3)->create();

            foreach ($courses as $index => $course) {
                $path->courses()->attach($course->id, [
                    'position' => $index + 1,
                    'is_required' => true,
                ]);
            }

            $response = $this->post(route('learner.learning-paths.enroll', $path));

            $response->assertRedirect(route('learner.learning-paths.show', $path));
            $response->assertSessionHas('success');

            $this->assertDatabaseHas('learning_path_enrollments', [
                'user_id' => $this->user->id,
                'learning_path_id' => $path->id,
                'state' => 'active',
            ]);
        });

        it('prevents duplicate enrollment', function () {
            $this->actingAs($this->user);

            $path = LearningPath::factory()->published()->create();

            LearningPathEnrollment::factory()->active()->create([
                'user_id' => $this->user->id,
                'learning_path_id' => $path->id,
            ]);

            $response = $this->post(route('learner.learning-paths.enroll', $path));

            $response->assertRedirect();
            $response->assertSessionHas('warning');
        });

        it('prevents enrollment in unpublished path', function () {
            $this->actingAs($this->user);

            $path = LearningPath::factory()->unpublished()->create();

            $response = $this->post(route('learner.learning-paths.enroll', $path));

            $response->assertRedirect(route('learner.learning-paths.browse'));
            $response->assertSessionHas('error');
        });
    });

    describe('DELETE /learner/learning-paths/enrollment/{enrollment}', function () {
        it('allows user to drop their enrollment', function () {
            $this->actingAs($this->user);

            $enrollment = LearningPathEnrollment::factory()->active()->create([
                'user_id' => $this->user->id,
            ]);

            $response = $this->delete(route('learner.learning-paths.drop', $enrollment), [
                'reason' => 'Lost interest',
            ]);

            $response->assertRedirect(route('learner.learning-paths.index'));
            $response->assertSessionHas('success');

            $this->assertDatabaseHas('learning_path_enrollments', [
                'id' => $enrollment->id,
                'state' => 'dropped',
            ]);
        });

        it('prevents dropping other users enrollment', function () {
            $this->actingAs($this->user);

            $otherUser = User::factory()->create();
            $enrollment = LearningPathEnrollment::factory()->active()->create([
                'user_id' => $otherUser->id,
            ]);

            $response = $this->delete(route('learner.learning-paths.drop', $enrollment));

            $response->assertForbidden();
        });
    });

    describe('GET /learner/learning-paths/{learningPath}', function () {
        it('shows learning path details with enrollment status', function () {
            $this->actingAs($this->user);

            $path = LearningPath::factory()->published()->create();
            $courses = Course::factory()->published()->count(3)->create();

            foreach ($courses as $index => $course) {
                $path->courses()->attach($course->id, [
                    'position' => $index + 1,
                    'is_required' => true,
                ]);
            }

            $response = $this->get(route('learner.learning-paths.show', $path));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->has('learningPath')
                ->where('canEnroll', true)
                ->where('enrollment', null)
            );
        });

        it('shows progress when enrolled', function () {
            $this->actingAs($this->user);

            $path = LearningPath::factory()->published()->create();
            $courses = Course::factory()->published()->count(3)->create();

            foreach ($courses as $index => $course) {
                $path->courses()->attach($course->id, [
                    'position' => $index + 1,
                    'is_required' => true,
                ]);
            }

            $enrollment = LearningPathEnrollment::factory()->active()->create([
                'user_id' => $this->user->id,
                'learning_path_id' => $path->id,
            ]);

            foreach ($courses as $index => $course) {
                $enrollment->courseProgress()->create([
                    'course_id' => $course->id,
                    'state' => $index === 0 ? 'available' : 'locked',
                    'position' => $index + 1,
                ]);
            }

            $this->withoutExceptionHandling();

            $response = $this->get(route('learner.learning-paths.show', $path));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->has('enrollment')
                ->has('progress')
                ->where('canEnroll', false)
            );
        });
    });
});
