<?php

namespace App\Http\Controllers;

use App\Http\Requests\Question\BulkUpdateQuestionsRequest;
use App\Http\Requests\Question\ReorderQuestionsRequest;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Question;
use App\Services\QuestionManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QuestionController extends Controller
{
    public function __construct(
        protected QuestionManagementService $questionService
    ) {}

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
    public function bulkUpdate(BulkUpdateQuestionsRequest $request, Course $course, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('update', [$assessment, $course]);

        $validated = $request->validated();
        $questionsData = $validated['questions'];

        if (empty($questionsData)) {
            return redirect()
                ->route('assessments.questions.index', [$course, $assessment])
                ->with('error', 'Tidak ada pertanyaan yang dikirimkan.');
        }

        $this->questionService->bulkUpdate($assessment, $questionsData);

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
    public function reorder(ReorderQuestionsRequest $request, Course $course, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('update', [$assessment, $course]);

        $validated = $request->validated();

        foreach ($validated['question_ids'] as $index => $questionId) {
            $assessment->questions()->where('id', $questionId)->update(['order' => $index]);
        }

        return back()->with('success', 'Urutan pertanyaan berhasil diperbarui.');
    }
}
