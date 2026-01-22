<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuestionController extends Controller
{
    /**
     * Display a listing of the questions.
     */
    public function index(Request $request, Course $course, Assessment $assessment): Response
    {
        Gate::authorize('update', [$assessment, $course]);

        $assessment->load(['questions.options']);

        return Inertia::render('assessments/Questions', [
            'course' => $course,
            'assessment' => $assessment,
        ]);
    }

    /**
     * Bulk update questions for an assessment.
     */
    public function bulkUpdate(Request $request, Course $course, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('update', [$assessment, $course]);

        // Handle JSON string from form submission BEFORE validation
        $questionsData = $request->input('questions');

        if (is_string($questionsData)) {
            $questionsData = json_decode($questionsData, true);

            // Replace the string with the decoded array in the request
            $request->merge(['questions' => $questionsData]);
        }

        // Check if questionsData is empty after processing
        if (empty($questionsData)) {
            // This should not happen, but let's handle it gracefully
            return redirect()
                ->route('assessments.questions.index', [$course, $assessment])
                ->with('error', 'Tidak ada pertanyaan yang dikirimkan.');
        }

        $validQuestionTypes = ['multiple_choice', 'true_false', 'matching', 'short_answer', 'essay', 'file_upload'];

        $validated = $request->validate([
            'questions' => ['required', 'array'],
            'questions.*.id' => ['sometimes', 'integer', 'nullable', function ($attribute, $value, $fail) {
                // Allow 0 for new questions, but validate positive IDs exist in database
                if ($value !== null && $value !== 0 && ! Question::where('id', $value)->exists()) {
                    $fail('The selected '.$attribute.' is invalid.');
                }
            }],
            'questions.*.question_text' => ['required', 'string'],
            'questions.*.question_type' => ['required', 'string', Rule::in($validQuestionTypes)],
            'questions.*.points' => ['required', 'integer', 'min:1'],
            'questions.*.feedback' => ['nullable', 'string'],
            'questions.*.order' => ['sometimes', 'integer', 'min:0'],
            'questions.*.options' => ['sometimes', 'array', function ($attribute, $value, $fail) use ($request) {
                // Extract the question index from the attribute (e.g., "questions.0.options" -> 0)
                preg_match('/questions\.(\d+)\.options/', $attribute, $matches);
                $index = $matches[1] ?? null;

                if ($index !== null) {
                    $questionType = $request->input("questions.{$index}.question_type");

                    // Multiple choice and matching questions require at least 2 options
                    if (in_array($questionType, ['multiple_choice', 'matching'])) {
                        if (! is_array($value) || count($value) < 2) {
                            $fail('Pertanyaan pilihan ganda dan pencocokan memerlukan minimal 2 opsi.');
                        }
                    }
                }
            }],
            'questions.*.options.*.id' => ['sometimes', 'integer'],
            'questions.*.options.*.option_text' => ['required', 'string'],
            'questions.*.options.*.is_correct' => ['required', 'boolean'],
            'questions.*.options.*.feedback' => ['nullable', 'string'],
            'questions.*.options.*.order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $existingQuestionIds = $assessment->questions()->pluck('id')->toArray();
        $submittedQuestionIds = [];

        foreach ($validated['questions'] as $questionData) {
            if (isset($questionData['id']) && $questionData['id'] > 0) {
                // Update existing question - must belong to THIS assessment
                $question = $assessment->questions()->find($questionData['id']);
                if ($question) {
                    $question->update([
                        'question_text' => $questionData['question_text'],
                        'question_type' => $questionData['question_type'],
                        'points' => $questionData['points'],
                        'feedback' => $questionData['feedback'] ?? null,
                        'order' => $questionData['order'] ?? 0,
                    ]);

                    $submittedQuestionIds[] = $question->id;

                    // Sync options for this question
                    if (isset($questionData['options']) && is_array($questionData['options'])) {
                        $existingOptionIds = $question->options()->pluck('id')->toArray();
                        $submittedOptionIds = [];

                        foreach ($questionData['options'] as $optionData) {
                            if (isset($optionData['id']) && $optionData['id'] > 0) {
                                // Update existing option
                                $option = $question->options()->find($optionData['id']);
                                if ($option) {
                                    $option->update([
                                        'option_text' => $optionData['option_text'],
                                        'is_correct' => $optionData['is_correct'] ?? false,
                                        'feedback' => $optionData['feedback'] ?? null,
                                        'order' => $optionData['order'] ?? 0,
                                    ]);
                                    $submittedOptionIds[] = $option->id;
                                }
                            } else {
                                // Create new option
                                $option = $question->options()->create([
                                    'option_text' => $optionData['option_text'],
                                    'is_correct' => $optionData['is_correct'] ?? false,
                                    'feedback' => $optionData['feedback'] ?? null,
                                    'order' => $optionData['order'] ?? 0,
                                ]);
                                $submittedOptionIds[] = $option->id;
                            }
                        }

                        // Delete options that were not submitted
                        $optionsToDelete = array_diff($existingOptionIds, $submittedOptionIds);
                        if (! empty($optionsToDelete)) {
                            $question->options()->whereIn('id', $optionsToDelete)->delete();
                        }
                    }
                }
            } else {
                // Create new question
                $question = $assessment->questions()->create([
                    'question_text' => $questionData['question_text'],
                    'question_type' => $questionData['question_type'],
                    'points' => $questionData['points'],
                    'feedback' => $questionData['feedback'] ?? null,
                    'order' => $questionData['order'] ?? 0,
                ]);

                $submittedQuestionIds[] = $question->id;

                // Create options for this question
                if (isset($questionData['options']) && is_array($questionData['options'])) {
                    foreach ($questionData['options'] as $optionData) {
                        $question->options()->create([
                            'option_text' => $optionData['option_text'],
                            'is_correct' => $optionData['is_correct'] ?? false,
                            'feedback' => $optionData['feedback'] ?? null,
                            'order' => $optionData['order'] ?? 0,
                        ]);
                    }
                }
            }
        }

        // Delete questions that were not submitted (removed by user)
        $questionsToDelete = array_diff($existingQuestionIds, $submittedQuestionIds);
        if (! empty($questionsToDelete)) {
            Question::whereIn('id', $questionsToDelete)->delete();
        }

        return redirect()
            ->route('assessments.questions.index', [$course, $assessment])
            ->with('success', 'Pertanyaan berhasil diperbarui.');
    }

    /**
     * Remove the specified question.
     */
    public function destroy(Course $course, Assessment $assessment, Question $question): RedirectResponse
    {
        Gate::authorize('update', [$assessment, $course]);

        // Verify question belongs to this assessment
        if ($question->assessment_id !== $assessment->id) {
            abort(404);
        }

        $question->delete();

        return redirect()
            ->route('assessments.edit', [$course, $assessment])
            ->with('success', 'Pertanyaan berhasil dihapus.');
    }

    /**
     * Reorder questions.
     */
    public function reorder(Request $request, Course $course, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('update', [$assessment, $course]);

        // Validate question IDs belong to THIS assessment
        $validated = $request->validate([
            'question_ids' => 'required|array',
            'question_ids.*' => [
                'integer',
                Rule::exists('questions', 'id')->where('assessment_id', $assessment->id),
            ],
        ]);

        foreach ($validated['question_ids'] as $index => $questionId) {
            // Use scoped query to ensure we only update this assessment's questions
            $assessment->questions()->where('id', $questionId)->update(['order' => $index]);
        }

        return back()->with('success', 'Urutan pertanyaan berhasil diperbarui.');
    }
}
