<?php

namespace App\Domain\Assessment\Services;

use App\Domain\Assessment\Contracts\GradingStrategyResolverContract;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Question;

class AssessmentSubmissionService
{
    public function __construct(
        protected GradingStrategyResolverContract $gradingResolver
    ) {}

    public function submitAttempt(AssessmentAttempt $attempt, array $answers, Assessment $assessment): array
    {
        $totalScore = 0;
        $maxScore = $assessment->total_points;
        $hasManualGrading = false;

        foreach ($answers as $answerData) {
            $question = $assessment->questions()->find($answerData['question_id']);

            if (! $question instanceof Question) {
                continue;
            }

            $filePath = $this->handleFileUpload($answerData);
            $answerText = $question->formatAnswerForStorage($answerData);

            $answer = $attempt->answers()->create([
                'question_id' => $question->id,
                'answer_text' => $answerText,
                'file_path' => $filePath,
            ]);

            if (! $question->requiresManualGrading()) {
                $answerValue = $question->extractAnswerValue($answerData);
                $result = $this->gradeQuestion($question, $answerValue);

                $answer->update([
                    'is_correct' => $result->isCorrect,
                    'score' => $result->score,
                    'feedback' => $result->feedback,
                ]);

                $totalScore += $result->score;
            } else {
                $hasManualGrading = true;
            }
        }

        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;
        $passed = $percentage >= $assessment->passing_score;
        $status = $hasManualGrading ? 'submitted' : 'graded';

        $attempt->update([
            'status' => $status,
            'score' => $totalScore,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'passed' => $passed,
            'submitted_at' => now(),
        ]);

        return [
            'totalScore' => $totalScore,
            'maxScore' => $maxScore,
            'percentage' => $percentage,
            'passed' => $passed,
            'status' => $status,
        ];
    }

    public function submitBulkGrades(AssessmentAttempt $attempt, array $grades, Assessment $assessment): array
    {
        $totalScore = 0;

        foreach ($grades as $gradeData) {
            $answer = $attempt->answers()->find($gradeData['answer_id']);

            if ($answer) {
                $answer->update([
                    'score' => $gradeData['score'],
                    'feedback' => $gradeData['feedback'] ?? null,
                    'graded_by' => auth()->id(),
                    'graded_at' => now(),
                ]);
                $totalScore += $gradeData['score'];
            }
        }

        $maxScore = $assessment->total_points;
        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;
        $passed = $percentage >= $assessment->passing_score;

        $attempt->update([
            'status' => 'graded',
            'score' => $totalScore,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'passed' => $passed,
            'graded_at' => now(),
            'graded_by' => auth()->id(),
        ]);

        return [
            'totalScore' => $totalScore,
            'maxScore' => $maxScore,
            'percentage' => $percentage,
            'passed' => $passed,
        ];
    }

    public function gradeQuestion(Question $question, mixed $answer): \App\Domain\Assessment\DTOs\GradingResult
    {
        $strategy = $this->gradingResolver->resolve($question);

        if ($strategy === null) {
            return \App\Domain\Assessment\DTOs\GradingResult::partial(
                score: 0,
                maxScore: $question->points,
                feedback: 'Tipe soal tidak didukung untuk penilaian otomatis.',
                metadata: ['requires_manual_grading' => true]
            );
        }

        return $strategy->grade($question, $answer);
    }

    protected function handleFileUpload(array $answerData): ?string
    {
        if (! isset($answerData['file'])) {
            return null;
        }

        return $answerData['file']->store('assessment_answers', 'public');
    }
}
