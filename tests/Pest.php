<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create and authenticate a user with a specific role.
 */
function asRole(string $role): Tests\TestCase
{
    $user = App\Models\User::factory()->create(['role' => $role]);

    return test()->actingAs($user);
}

/**
 * Create an LMS admin and authenticate.
 */
function asAdmin(): Tests\TestCase
{
    return asRole('lms_admin');
}

/**
 * Create a content manager and authenticate.
 */
function asContentManager(): Tests\TestCase
{
    return asRole('content_manager');
}

/**
 * Create a learner and authenticate.
 */
function asLearner(): Tests\TestCase
{
    return asRole('learner');
}

/**
 * Create a published course with content.
 */
function createPublishedCourseWithContent(int $sectionCount = 1, int $lessonsPerSection = 3): App\Models\Course
{
    $course = App\Models\Course::factory()->published()->public()->create();

    for ($i = 0; $i < $sectionCount; $i++) {
        $section = App\Models\CourseSection::factory()->create([
            'course_id' => $course->id,
            'order' => $i + 1,
        ]);

        App\Models\Lesson::factory()->count($lessonsPerSection)->create([
            'course_section_id' => $section->id,
        ]);
    }

    return $course;
}

/**
 * Create an enrolled user for a course.
 *
 * @return array{user: App\Models\User, course: App\Models\Course, enrollment: App\Models\Enrollment}
 */
function createEnrolledLearner(?App\Models\Course $course = null): array
{
    $user = App\Models\User::factory()->create(['role' => 'learner']);
    $course = $course ?? App\Models\Course::factory()->published()->create();

    $enrollment = App\Models\Enrollment::factory()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'status' => 'active',
    ]);

    return [
        'user' => $user,
        'course' => $course,
        'enrollment' => $enrollment,
    ];
}

/**
 * Assert that an event was dispatched with specific properties.
 */
function assertEventDispatched(string $eventClass, ?callable $callback = null): void
{
    Illuminate\Support\Facades\Event::assertDispatched($eventClass, $callback);
}

/**
 * Get the ProgressTrackingService instance.
 */
function progressService(): App\Domain\Progress\Services\ProgressTrackingService
{
    return app(App\Domain\Progress\Services\ProgressTrackingService::class);
}
