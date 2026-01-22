<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseRating;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseRatingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Course $course;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'learner']);
        $this->course = Course::factory()->published()->create();
        $this->enrollment = Enrollment::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);
    }

    public function test_enrolled_user_can_create_rating(): void
    {
        $response = $this->actingAs($this->user)->postJson("/courses/{$this->course->id}/ratings", [
            'rating' => 5,
            'review' => 'Kursus yang sangat bagus!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('course_ratings', [
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'rating' => 5,
            'review' => 'Kursus yang sangat bagus!',
        ]);
    }

    public function test_enrolled_user_can_create_rating_without_review(): void
    {
        $response = $this->actingAs($this->user)->postJson("/courses/{$this->course->id}/ratings", [
            'rating' => 4,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('course_ratings', [
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'rating' => 4,
            'review' => null,
        ]);
    }

    public function test_non_enrolled_user_cannot_create_rating(): void
    {
        $otherUser = User::factory()->create(['role' => 'learner']);

        $response = $this->actingAs($otherUser)->postJson("/courses/{$this->course->id}/ratings", [
            'rating' => 5,
        ]);

        $response->assertForbidden();
    }

    public function test_guest_cannot_create_rating(): void
    {
        $response = $this->postJson("/courses/{$this->course->id}/ratings", [
            'rating' => 5,
        ]);

        $response->assertUnauthorized();
    }

    public function test_user_cannot_rate_same_course_twice(): void
    {
        // Create first rating
        CourseRating::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'rating' => 4,
        ]);

        // Try to create second rating
        $response = $this->actingAs($this->user)->postJson("/courses/{$this->course->id}/ratings", [
            'rating' => 5,
        ]);

        $response->assertForbidden();

        // Should still have only one rating
        $this->assertEquals(1, CourseRating::where('user_id', $this->user->id)
            ->where('course_id', $this->course->id)
            ->count());
    }

    public function test_rating_validates_minimum_value(): void
    {
        $response = $this->actingAs($this->user)->postJson("/courses/{$this->course->id}/ratings", [
            'rating' => 0,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('rating');
    }

    public function test_rating_validates_maximum_value(): void
    {
        $response = $this->actingAs($this->user)->postJson("/courses/{$this->course->id}/ratings", [
            'rating' => 6,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('rating');
    }

    public function test_rating_validates_integer(): void
    {
        $response = $this->actingAs($this->user)->postJson("/courses/{$this->course->id}/ratings", [
            'rating' => 3.5,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('rating');
    }

    public function test_review_validates_max_length(): void
    {
        $response = $this->actingAs($this->user)->postJson("/courses/{$this->course->id}/ratings", [
            'rating' => 5,
            'review' => str_repeat('a', 1001),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('review');
    }

    public function test_user_can_update_own_rating(): void
    {
        $rating = CourseRating::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'rating' => 3,
            'review' => 'Biasa saja',
        ]);

        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/ratings/{$rating->id}", [
            'rating' => 5,
            'review' => 'Setelah selesai, ternyata sangat bagus!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('course_ratings', [
            'id' => $rating->id,
            'rating' => 5,
            'review' => 'Setelah selesai, ternyata sangat bagus!',
        ]);
    }

    public function test_user_cannot_update_other_user_rating(): void
    {
        $otherUser = User::factory()->create(['role' => 'learner']);
        $rating = CourseRating::factory()->create([
            'user_id' => $otherUser->id,
            'course_id' => $this->course->id,
            'rating' => 4,
        ]);

        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/ratings/{$rating->id}", [
            'rating' => 1,
        ]);

        $response->assertForbidden();
    }

    public function test_user_can_delete_own_rating(): void
    {
        $rating = CourseRating::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'rating' => 4,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/courses/{$this->course->id}/ratings/{$rating->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('course_ratings', [
            'id' => $rating->id,
        ]);
    }

    public function test_user_cannot_delete_other_user_rating(): void
    {
        $otherUser = User::factory()->create(['role' => 'learner']);
        $rating = CourseRating::factory()->create([
            'user_id' => $otherUser->id,
            'course_id' => $this->course->id,
            'rating' => 4,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/courses/{$this->course->id}/ratings/{$rating->id}");

        $response->assertForbidden();
    }

    public function test_admin_can_delete_any_rating(): void
    {
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $rating = CourseRating::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'rating' => 4,
        ]);

        $response = $this->actingAs($admin)->deleteJson("/courses/{$this->course->id}/ratings/{$rating->id}");

        $response->assertRedirect();

        $this->assertDatabaseMissing('course_ratings', [
            'id' => $rating->id,
        ]);
    }

    public function test_course_average_rating_is_calculated(): void
    {
        // Create multiple ratings
        CourseRating::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'rating' => 5,
        ]);

        $user2 = User::factory()->create(['role' => 'learner']);
        CourseRating::factory()->create([
            'user_id' => $user2->id,
            'course_id' => $this->course->id,
            'rating' => 3,
        ]);

        // Reload course with eager-loaded average to avoid N+1
        $course = Course::withAvg('ratings', 'rating')->find($this->course->id);
        $this->assertEquals(4.0, $course->average_rating);
    }

    public function test_course_ratings_count_is_correct(): void
    {
        CourseRating::factory()->count(5)->create([
            'course_id' => $this->course->id,
        ]);

        // Reload course with eager-loaded count to avoid N+1
        $course = Course::withCount('ratings')->find($this->course->id);
        $this->assertEquals(5, $course->ratings_count);
    }

    public function test_course_detail_shows_ratings(): void
    {
        // Create a rating
        CourseRating::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'rating' => 5,
            'review' => 'Test review',
        ]);

        $response = $this->actingAs($this->user)->get("/courses/{$this->course->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('ratings')
            ->has('averageRating')
            ->has('ratingsCount')
        );
    }

    public function test_course_detail_shows_user_rating(): void
    {
        $rating = CourseRating::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'rating' => 4,
        ]);

        $response = $this->actingAs($this->user)->get("/courses/{$this->course->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('userRating')
            ->where('userRating.id', $rating->id)
        );
    }

    public function test_can_rate_permission_is_false_when_already_rated(): void
    {
        CourseRating::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'rating' => 4,
        ]);

        $response = $this->actingAs($this->user)->get("/courses/{$this->course->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('can.rate', false)
        );
    }

    public function test_can_rate_permission_is_true_when_enrolled_and_not_rated(): void
    {
        $response = $this->actingAs($this->user)->get("/courses/{$this->course->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('can.rate', true)
        );
    }
}
