<?php

use App\Domain\Assessment\Services\AssessmentSubmissionService;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;

beforeEach(function () {
    $this->service = app(AssessmentSubmissionService::class);
});

describe('AssessmentSubmissionService', function () {
    describe('submitAttempt - Multiple Choice', function () {
        it('auto-grades multiple choice with correct answer', function () {
            $user = User::factory()->create();
            $assessment = Assessment::factory()->published()->create([
                'passing_score' => 70,
            ]);

            $question = Question::factory()->multipleChoice()->create([
                'assessment_id' => $assessment->id,
                'points' => 10,
            ]);

            $correctOption = QuestionOption::factory()->correct()->create([
                'question_id' => $question->id,
            ]);

            QuestionOption::factory()->incorrect()->count(3)->create([
                'question_id' => $question->id,
            ]);

            $attempt = AssessmentAttempt::factory()->inProgress()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $user->id,
            ]);

            $answers = [
                [
                    'question_id' => $question->id,
                    'selected_options' => [$correctOption->id],
                    'answer_text' => null,
                ],
            ];

            $result = $this->service->submitAttempt($attempt, $answers, $assessment);

            expect($result['totalScore'])->toEqual(10);
            expect($result['maxScore'])->toBe(10);
            expect($result['percentage'])->toEqual(100.0);
            expect($result['passed'])->toBeTrue();
            expect($result['status'])->toBe('graded');

            $attempt->refresh();
            expect($attempt->status)->toBe('graded');
            expect($attempt->score)->toEqual(10);
            expect($attempt->passed)->toBeTrue();
        });

        it('auto-grades multiple choice with incorrect answer', function () {
            $user = User::factory()->create();
            $assessment = Assessment::factory()->published()->create([
                'passing_score' => 70,
            ]);

            $question = Question::factory()->multipleChoice()->create([
                'assessment_id' => $assessment->id,
                'points' => 10,
            ]);

            $correctOption = QuestionOption::factory()->correct()->create([
                'question_id' => $question->id,
            ]);

            $incorrectOption = QuestionOption::factory()->incorrect()->create([
                'question_id' => $question->id,
            ]);

            $attempt = AssessmentAttempt::factory()->inProgress()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $user->id,
            ]);

            $answers = [
                [
                    'question_id' => $question->id,
                    'selected_options' => [$incorrectOption->id],
                    'answer_text' => null,
                ],
            ];

            $result = $this->service->submitAttempt($attempt, $answers, $assessment);

            expect($result['totalScore'])->toEqual(0);
            expect($result['maxScore'])->toBe(10);
            expect($result['percentage'])->toEqual(0);
            expect($result['passed'])->toBeFalse();
            expect($result['status'])->toBe('graded');
        });
    });

    describe('submitAttempt - True/False', function () {
        it('auto-grades true/false with correct answer', function () {
            $user = User::factory()->create();
            $assessment = Assessment::factory()->published()->create([
                'passing_score' => 60,
            ]);

            $question = Question::factory()->trueFalse()->create([
                'assessment_id' => $assessment->id,
                'points' => 5,
            ]);

            QuestionOption::factory()->correct()->create([
                'question_id' => $question->id,
                'option_text' => 'true',
            ]);

            QuestionOption::factory()->incorrect()->create([
                'question_id' => $question->id,
                'option_text' => 'false',
            ]);

            $attempt = AssessmentAttempt::factory()->inProgress()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $user->id,
            ]);

            $answers = [
                [
                    'question_id' => $question->id,
                    'answer_text' => 'true',
                ],
            ];

            $result = $this->service->submitAttempt($attempt, $answers, $assessment);

            expect($result['totalScore'])->toEqual(5);
            expect($result['percentage'])->toEqual(100.0);
            expect($result['passed'])->toBeTrue();
            expect($result['status'])->toBe('graded');
        });

        it('auto-grades true/false with incorrect answer', function () {
            $user = User::factory()->create();
            $assessment = Assessment::factory()->published()->create([
                'passing_score' => 60,
            ]);

            $question = Question::factory()->trueFalse()->create([
                'assessment_id' => $assessment->id,
                'points' => 5,
            ]);

            QuestionOption::factory()->correct()->create([
                'question_id' => $question->id,
                'option_text' => 'true',
            ]);

            QuestionOption::factory()->incorrect()->create([
                'question_id' => $question->id,
                'option_text' => 'false',
            ]);

            $attempt = AssessmentAttempt::factory()->inProgress()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $user->id,
            ]);

            $answers = [
                [
                    'question_id' => $question->id,
                    'answer_text' => 'false',
                ],
            ];

            $result = $this->service->submitAttempt($attempt, $answers, $assessment);

            expect($result['totalScore'])->toEqual(0);
            expect($result['passed'])->toBeFalse();
        });
    });

    describe('submitAttempt - Essay (Manual Grading)', function () {
        it('marks essay as submitted with manual grading flag', function () {
            $user = User::factory()->create();
            $assessment = Assessment::factory()->published()->create([
                'passing_score' => 70,
            ]);

            $question = Question::factory()->essay()->create([
                'assessment_id' => $assessment->id,
                'points' => 20,
            ]);

            $attempt = AssessmentAttempt::factory()->inProgress()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $user->id,
            ]);

            $answers = [
                [
                    'question_id' => $question->id,
                    'answer_text' => 'This is my essay answer about the topic...',
                ],
            ];

            $result = $this->service->submitAttempt($attempt, $answers, $assessment);

            expect($result['totalScore'])->toEqual(0);
            expect($result['status'])->toBe('submitted');
            expect($result['passed'])->toBeFalse();

            $attempt->refresh();
            expect($attempt->status)->toBe('submitted');
        });
    });

    describe('submitAttempt - Mixed Questions', function () {
        it('auto-grades auto-gradable questions and flags manual grading', function () {
            $user = User::factory()->create();
            $assessment = Assessment::factory()->published()->create([
                'passing_score' => 70,
            ]);

            $mcQuestion = Question::factory()->multipleChoice()->create([
                'assessment_id' => $assessment->id,
                'points' => 10,
            ]);

            $correctOption = QuestionOption::factory()->correct()->create([
                'question_id' => $mcQuestion->id,
            ]);

            QuestionOption::factory()->incorrect()->count(3)->create([
                'question_id' => $mcQuestion->id,
            ]);

            $tfQuestion = Question::factory()->trueFalse()->create([
                'assessment_id' => $assessment->id,
                'points' => 10,
            ]);

            QuestionOption::factory()->correct()->create([
                'question_id' => $tfQuestion->id,
                'option_text' => 'true',
            ]);

            $essayQuestion = Question::factory()->essay()->create([
                'assessment_id' => $assessment->id,
                'points' => 10,
            ]);

            $attempt = AssessmentAttempt::factory()->inProgress()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $user->id,
            ]);

            $answers = [
                [
                    'question_id' => $mcQuestion->id,
                    'selected_options' => [$correctOption->id],
                    'answer_text' => null,
                ],
                [
                    'question_id' => $tfQuestion->id,
                    'answer_text' => 'true',
                ],
                [
                    'question_id' => $essayQuestion->id,
                    'answer_text' => 'Essay answer here...',
                ],
            ];

            $result = $this->service->submitAttempt($attempt, $answers, $assessment);

            expect($result['totalScore'])->toEqual(20);
            expect($result['maxScore'])->toBe(30);
            expect($result['status'])->toBe('submitted');
            expect($result['percentage'])->toEqual(66.67);
        });
    });

    describe('submitAttempt - Score Calculation', function () {
        it('calculates percentage correctly', function () {
            $user = User::factory()->create();
            $assessment = Assessment::factory()->published()->create([
                'passing_score' => 75,
            ]);

            $question = Question::factory()->multipleChoice()->create([
                'assessment_id' => $assessment->id,
                'points' => 100,
            ]);

            $correctOption = QuestionOption::factory()->correct()->create([
                'question_id' => $question->id,
            ]);

            $attempt = AssessmentAttempt::factory()->inProgress()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $user->id,
            ]);

            $answers = [
                [
                    'question_id' => $question->id,
                    'selected_options' => [$correctOption->id],
                    'answer_text' => null,
                ],
            ];

            $result = $this->service->submitAttempt($attempt, $answers, $assessment);

            expect($result['percentage'])->toEqual(100.0);
            expect($result['passed'])->toBeTrue();
        });

        it('determines pass/fail based on passing score', function () {
            $user = User::factory()->create();
            $assessment = Assessment::factory()->published()->create([
                'passing_score' => 80,
            ]);

            $question1 = Question::factory()->multipleChoice()->create([
                'assessment_id' => $assessment->id,
                'points' => 50,
            ]);

            $correctOption1 = QuestionOption::factory()->correct()->create([
                'question_id' => $question1->id,
            ]);

            $question2 = Question::factory()->multipleChoice()->create([
                'assessment_id' => $assessment->id,
                'points' => 50,
            ]);

            QuestionOption::factory()->correct()->create([
                'question_id' => $question2->id,
            ]);

            $incorrectOption2 = QuestionOption::factory()->incorrect()->create([
                'question_id' => $question2->id,
            ]);

            $attempt = AssessmentAttempt::factory()->inProgress()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $user->id,
            ]);

            $answers = [
                [
                    'question_id' => $question1->id,
                    'selected_options' => [$correctOption1->id],
                    'answer_text' => null,
                ],
                [
                    'question_id' => $question2->id,
                    'selected_options' => [$incorrectOption2->id],
                    'answer_text' => null,
                ],
            ];

            $result = $this->service->submitAttempt($attempt, $answers, $assessment);

            expect($result['totalScore'])->toEqual(50);
            expect($result['percentage'])->toEqual(50);
            expect($result['passed'])->toBeFalse();
        });
    });

    describe('submitBulkGrades', function () {
        it('updates answer scores for manual grading', function () {
            $user = User::factory()->create();
            $grader = User::factory()->create(['role' => 'content_manager']);
            $assessment = Assessment::factory()->published()->create([
                'passing_score' => 70,
            ]);

            $essayQuestion = Question::factory()->essay()->create([
                'assessment_id' => $assessment->id,
                'points' => 20,
            ]);

            $attempt = AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $user->id,
            ]);

            $answer = $attempt->answers()->create([
                'question_id' => $essayQuestion->id,
                'answer_text' => 'Essay answer...',
                'score' => 0,
            ]);

            $this->actingAs($grader);

            $grades = [
                [
                    'answer_id' => $answer->id,
                    'score' => 18,
                    'feedback' => 'Excellent work!',
                ],
            ];

            $result = $this->service->submitBulkGrades($attempt, $grades, $assessment);

            expect($result['totalScore'])->toEqual(18);
            expect($result['maxScore'])->toBe(20);
            expect($result['percentage'])->toEqual(90.0);
            expect($result['passed'])->toBeTrue();

            $answer->refresh();
            expect($answer->score)->toEqual(18);
            expect($answer->feedback)->toBe('Excellent work!');
            expect($answer->graded_by)->toBe($grader->id);

            $attempt->refresh();
            expect($attempt->status)->toBe('graded');
            expect($attempt->graded_by)->toBe($grader->id);
        });
    });
});
