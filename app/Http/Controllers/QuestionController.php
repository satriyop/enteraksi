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