<?php
namespace App\Http\Controllers;

use App\Http\Requests\Question\StoreQuestionRequest;
use App\Http\Requests\Question\UpdateQuestionRequest;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
            'course'     => $course,
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

        $validated = $request->validate([
            'questions'                         => ['required', 'array'],
            'questions.*.id'                    => ['sometimes', 'integer', 'nullable', function ($attribute, $value, $fail) {
                // Allow 0 for new questions, but validate positive IDs exist in database
                if ($value !== null && $value !== 0 && ! Question::where('id', $value)->exists()) {
                    $fail('The selected ' . $attribute . ' is invalid.');
                }
            }],
            'questions.*.question_text'         => ['required', 'string'],
            'questions.*.question_type'         => ['required', 'string'],
            'questions.*.points'                => ['required', 'integer', 'min:1'],
            'questions.*.feedback'              => ['nullable', 'string'],
            'questions.*.order'                 => ['sometimes', 'integer', 'min:0'],
            'questions.*.options'               => ['sometimes', 'array'],
            'questions.*.options.*.id'          => ['sometimes', 'integer'],
            'questions.*.options.*.option_text' => ['required', 'string'],
            'questions.*.options.*.is_correct'  => ['required', 'boolean'],
            'questions.*.options.*.feedback'    => ['nullable', 'string'],
            'questions.*.options.*.order'       => ['sometimes', 'integer', 'min:0'],
        ]);

        $existingQuestionIds  = $assessment->questions()->pluck('id')->toArray();
        $submittedQuestionIds = [];

        foreach ($validated['questions'] as $questionData) {
            if (isset($questionData['id']) && $questionData['id'] > 0) {
                // Update existing question
                $question = Question::find($questionData['id']);
                if ($question) {
                    $question->update([
                        'question_text' => $questionData['question_text'],
                        'question_type' => $questionData['question_type'],
                        'points'        => $questionData['points'],
                        'feedback'      => $questionData['feedback'] ?? null,
                        'order'         => $questionData['order'] ?? 0,
                    ]);

                    $submittedQuestionIds[] = $question->id;

                    // Sync options for this question
                    if (isset($questionData['options']) && is_array($questionData['options'])) {
                        $existingOptionIds  = $question->options()->pluck('id')->toArray();
                        $submittedOptionIds = [];

                        foreach ($questionData['options'] as $optionData) {
                            if (isset($optionData['id']) && $optionData['id'] > 0) {
                                // Update existing option
                                $option = $question->options()->find($optionData['id']);
                                if ($option) {
                                    $option->update([
                                        'option_text' => $optionData['option_text'],
                                        'is_correct'  => $optionData['is_correct'] ?? false,
                                        'feedback'    => $optionData['feedback'] ?? null,
                                        'order'       => $optionData['order'] ?? 0,
                                    ]);
                                    $submittedOptionIds[] = $option->id;
                                }
                            } else {
                                // Create new option
                                $option = $question->options()->create([
                                    'option_text' => $optionData['option_text'],
                                    'is_correct'  => $optionData['is_correct'] ?? false,
                                    'feedback'    => $optionData['feedback'] ?? null,
                                    'order'       => $optionData['order'] ?? 0,
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
                    'points'        => $questionData['points'],
                    'feedback'      => $questionData['feedback'] ?? null,
                    'order'         => $questionData['order'] ?? 0,
                ]);

                $submittedQuestionIds[] = $question->id;

                // Create options for this question
                if (isset($questionData['options']) && is_array($questionData['options'])) {
                    foreach ($questionData['options'] as $optionData) {
                        $question->options()->create([
                            'option_text' => $optionData['option_text'],
                            'is_correct'  => $optionData['is_correct'] ?? false,
                            'feedback'    => $optionData['feedback'] ?? null,
                            'order'       => $optionData['order'] ?? 0,
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
     * Store a newly created question.
     */
    public function store(StoreQuestionRequest $request, Course $course, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('update', [$assessment, $course]);

        $validated                  = $request->validated();
        $validated['assessment_id'] = $assessment->id;

        $question = Question::create($validated);

        // Create options if provided
        if (isset($validated['options']) && is_array($validated['options'])) {
            foreach ($validated['options'] as $optionData) {
                $question->options()->create([
                    'option_text' => $optionData['text'],
                    'is_correct'  => $optionData['is_correct'] ?? false,
                    'feedback'    => $optionData['feedback'] ?? null,
                    'order'       => $optionData['order'] ?? 0,
                ]);
            }
        }

        return redirect()
            ->route('assessments.edit', [$course, $assessment])
            ->with('success', 'Pertanyaan berhasil ditambahkan.');
    }

    /**
     * Update the specified question.
     */
    public function update(UpdateQuestionRequest $request, Course $course, Assessment $assessment, Question $question): RedirectResponse
    {
        Gate::authorize('update', [$assessment, $course]);

        $validated = $request->validated();

        $question->update($validated);

        // Update options if provided
        if (isset($validated['options']) && is_array($validated['options'])) {
            // Delete existing options
            $question->options()->delete();

            // Create new options
            foreach ($validated['options'] as $optionData) {
                $question->options()->create([
                    'option_text' => $optionData['text'],
                    'is_correct'  => $optionData['is_correct'] ?? false,
                    'feedback'    => $optionData['feedback'] ?? null,
                    'order'       => $optionData['order'] ?? 0,
                ]);
            }
        }

        return redirect()
            ->route('assessments.edit', [$course, $assessment])
            ->with('success', 'Pertanyaan berhasil diperbarui.');
    }

    /**
     * Remove the specified question.
     */
    public function destroy(Course $course, Assessment $assessment, Question $question): RedirectResponse
    {
        Gate::authorize('update', [$assessment, $course]);

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

        $validated = $request->validate([
            'question_ids'   => 'required|array',
            'question_ids.*' => 'exists:questions,id',
        ]);

        foreach ($validated['question_ids'] as $index => $questionId) {
            Question::where('id', $questionId)->update(['order' => $index]);
        }

        return back()->with('success', 'Urutan pertanyaan berhasil diperbarui.');
    }

    /**
     * Show grading interface for manual grading.
     */
    public function grade(Request $request, Course $course, Assessment $assessment, AssessmentAttempt $attempt): Response
    {
        Gate::authorize('grade', [$attempt, $assessment, $course]);

        $attempt->load(['answers.question.options', 'user']);
        $assessment->load(['questions.options']);

        return Inertia::render('assessments/Grade', [
            'course'     => $course,
            'assessment' => $assessment,
            'attempt'    => $attempt,
        ]);
    }

    /**
     * Submit grading for an attempt.
     */
    public function submitGrade(Request $request, Course $course, Assessment $assessment, AssessmentAttempt $attempt): RedirectResponse
    {
        Gate::authorize('grade', [$attempt, $assessment, $course]);

        $validated = $request->validate([
            'answers'              => 'required|array',
            'answers.*.id'         => 'required|exists:attempt_answers,id',
            'answers.*.score'      => 'required|integer|min:0',
            'answers.*.is_correct' => 'required|boolean',
            'answers.*.feedback'   => 'nullable|string',
            'feedback'             => 'nullable|string',
        ]);

        $totalScore = 0;
        $maxScore   = $assessment->total_points;

        foreach ($validated['answers'] as $answerData) {
            $answer = $attempt->answers()->find($answerData['id']);

            if ($answer) {
                $answer->update([
                    'score'      => $answerData['score'],
                    'is_correct' => $answerData['is_correct'],
                    'feedback'   => $answerData['feedback'] ?? null,
                    'graded_by'  => auth()->id(),
                    'graded_at'  => now(),
                ]);

                $totalScore += $answerData['score'];
            }
        }

        // Update attempt
        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;
        $passed     = $percentage >= $assessment->passing_score;

        $attempt->update([
            'status'     => 'graded',
            'score'      => $totalScore,
            'max_score'  => $maxScore,
            'percentage' => $percentage,
            'passed'     => $passed,
            'feedback'   => $validated['feedback'] ?? null,
            'graded_by'  => auth()->id(),
            'graded_at'  => now(),
        ]);

        return redirect()
            ->route('assessments.attempt', [$course, $assessment, $attempt])
            ->with('success', 'Penilaian berhasil dinilai.');
    }
}