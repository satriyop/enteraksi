<?php

use App\Domain\LearningPath\Strategies\SequentialPrerequisiteEvaluator;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathCourseProgress;
use App\Models\LearningPathEnrollment;

beforeEach(function () {
    $this->evaluator = new SequentialPrerequisiteEvaluator;
});

describe('SequentialPrerequisiteEvaluator', function () {
    it('returns met for first course', function () {
        $path = LearningPath::factory()->published()->create();
        $course = Course::factory()->published()->create();
        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        $enrollment = LearningPathEnrollment::factory()->active()->create([
            'learning_path_id' => $path->id,
        ]);

        LearningPathCourseProgress::factory()->available()->create([
            'learning_path_enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'position' => 1,
        ]);

        $result = $this->evaluator->evaluate($enrollment, $course);

        expect($result->isMet)->toBeTrue();
        expect($result->missingPrerequisites)->toBeEmpty();
    });

    it('returns not met when previous courses incomplete', function () {
        $path = LearningPath::factory()->published()->create();
        $course1 = Course::factory()->published()->create(['title' => 'Course 1']);
        $course2 = Course::factory()->published()->create(['title' => 'Course 2']);

        $path->courses()->attach($course1->id, ['position' => 1, 'is_required' => true]);
        $path->courses()->attach($course2->id, ['position' => 2, 'is_required' => true]);

        $enrollment = LearningPathEnrollment::factory()->active()->create([
            'learning_path_id' => $path->id,
        ]);

        LearningPathCourseProgress::factory()->inProgress()->create([
            'learning_path_enrollment_id' => $enrollment->id,
            'course_id' => $course1->id,
            'position' => 1,
        ]);

        LearningPathCourseProgress::factory()->locked()->create([
            'learning_path_enrollment_id' => $enrollment->id,
            'course_id' => $course2->id,
            'position' => 2,
        ]);

        $result = $this->evaluator->evaluate($enrollment, $course2);

        expect($result->isMet)->toBeFalse();
        // missingPrerequisites contains arrays with 'id' and 'title' keys
        $titles = array_column($result->missingPrerequisites, 'title');
        expect($titles)->toContain('Course 1');
    });

    it('returns met when all previous courses completed', function () {
        $path = LearningPath::factory()->published()->create();
        $course1 = Course::factory()->published()->create();
        $course2 = Course::factory()->published()->create();
        $course3 = Course::factory()->published()->create();

        $path->courses()->attach($course1->id, ['position' => 1, 'is_required' => true]);
        $path->courses()->attach($course2->id, ['position' => 2, 'is_required' => true]);
        $path->courses()->attach($course3->id, ['position' => 3, 'is_required' => true]);

        $enrollment = LearningPathEnrollment::factory()->active()->create([
            'learning_path_id' => $path->id,
        ]);

        LearningPathCourseProgress::factory()->completed()->create([
            'learning_path_enrollment_id' => $enrollment->id,
            'course_id' => $course1->id,
            'position' => 1,
        ]);

        LearningPathCourseProgress::factory()->completed()->create([
            'learning_path_enrollment_id' => $enrollment->id,
            'course_id' => $course2->id,
            'position' => 2,
        ]);

        LearningPathCourseProgress::factory()->locked()->create([
            'learning_path_enrollment_id' => $enrollment->id,
            'course_id' => $course3->id,
            'position' => 3,
        ]);

        $result = $this->evaluator->evaluate($enrollment, $course3);

        expect($result->isMet)->toBeTrue();
    });

    it('returns not met for course not in path', function () {
        $path = LearningPath::factory()->published()->create();
        $courseInPath = Course::factory()->published()->create();
        $courseNotInPath = Course::factory()->published()->create();

        $path->courses()->attach($courseInPath->id, ['position' => 1, 'is_required' => true]);

        $enrollment = LearningPathEnrollment::factory()->active()->create([
            'learning_path_id' => $path->id,
        ]);

        $result = $this->evaluator->evaluate($enrollment, $courseNotInPath);

        expect($result->isMet)->toBeFalse();
        // When course is not in path, missingPrerequisites is empty and reason explains why
        expect($result->missingPrerequisites)->toBeEmpty();
        expect($result->reason)->toBe('Course not found in path');
    });
});
