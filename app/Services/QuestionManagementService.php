<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Question;

class QuestionManagementService
{
    /**
     * Bulk update questions and their options for an assessment.
     *
     * @param  array<int, array{
     *     id?: int|null,
     *     question_text: string,
     *     question_type: string,
     *     points: int,
     *     feedback?: string|null,
     *     order?: int,
     *     options?: array<int, array{
     *         id?: int|null,
     *         option_text: string,
     *         is_correct: bool,
     *         feedback?: string|null,
     *         order?: int
     *     }>
     * }>  $questionsData
     */
    public function bulkUpdate(Assessment $assessment, array $questionsData): void
    {
        $existingQuestionIds = $assessment->questions()->pluck('id')->toArray();
        $submittedQuestionIds = [];

        foreach ($questionsData as $questionData) {
            /** @var Question|null $question */
            $question = null;

            if (isset($questionData['id']) && $questionData['id'] > 0) {
                $question = Question::where('id', $questionData['id'])
                    ->where('assessment_id', $assessment->id)
                    ->first();
            }

            if ($question instanceof Question) {
                $question->update([
                    'question_text' => $questionData['question_text'],
                    'question_type' => $questionData['question_type'],
                    'points' => $questionData['points'],
                    'feedback' => $questionData['feedback'] ?? null,
                    'order' => $questionData['order'] ?? 0,
                ]);

                $submittedQuestionIds[] = $question->id;

                if (isset($questionData['options']) && is_array($questionData['options'])) {
                    $question->syncOptions($questionData['options']);
                }
            } else {
                /** @var Question $question */
                $question = $assessment->questions()->create([
                    'question_text' => $questionData['question_text'],
                    'question_type' => $questionData['question_type'],
                    'points' => $questionData['points'],
                    'feedback' => $questionData['feedback'] ?? null,
                    'order' => $questionData['order'] ?? 0,
                ]);

                $submittedQuestionIds[] = $question->id;

                if (isset($questionData['options']) && is_array($questionData['options'])) {
                    $question->createOptions($questionData['options']);
                }
            }
        }

        $questionsToDelete = array_diff($existingQuestionIds, $submittedQuestionIds);
        if (! empty($questionsToDelete)) {
            Question::whereIn('id', $questionsToDelete)->delete();
        }
    }
}
