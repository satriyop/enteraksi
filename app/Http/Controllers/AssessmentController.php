<?php

namespace App\Http\Controllers;

use App\Domain\Assessment\Contracts\GradingStrategyResolverContract;
use App\Domain\Assessment\DTOs\GradingResult;
use App\Domain\Assessment\Exceptions\MaxAttemptsReachedException;
use App\Domain\Enrollment\DTOs\EnrollmentContext;
use App\Http\Requests\Assessment\StoreAssessmentRequest;
use App\Http\Requests\Assessment\UpdateAssessmentRequest;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function __construct(
        protected GradingStrategyResolverContract $gradingResolver
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Course $course): Response
    {
        $user = $request->user();

        // Pre-fetch enrollment context for authorization
        $context = $user->isLearner()
            ? EnrollmentContext::for($user, $course)
            : null;

        Gate::authorize('viewAny', [Assessment::class, $course, $context]);

        $query = Assessment::query()
            ->where('course_id', $course->id)
            ->with(['user', 'publishedBy'])
            ->withCount(['questions', 'attempts']);

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $assessments = $query->latest()->paginate(10)->withQueryString();

        return Inertia::render('assessments/Index', [
            'course' => $course,
            'assessments' => $assessments,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Course $course): Response
    {
        Gate::authorize('create', [Assessment::class, $course]);

        return Inertia::render('assessments/Create', [
            'course' => $course,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAssessmentRequest $request, Course $course): RedirectResponse
    {
        $validated = $request->validated();
        $validated['course_id'] = $course->id;
        $validated['user_id'] = $request->user()->id;
        $validated['slug'] = Str::slug($validated['title']).'-'.Str::random(6);

        $assessment = Assessment::create($validated);

        return redirect()
            ->route('assessments.edit', [$course, $assessment])
            ->with('success', 'Penilaian berhasil dibuat.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Course $course, Assessment $assessment): Response
    {
        $user = $request->user();

        // Pre-fetch enrollment context for authorization
        $context = $user->isLearner()
            ? EnrollmentContext::for($user, $course)
            : null;

        Gate::authorize('view', [$assessment, $course, $context]);

        $assessment->load(['user', 'publishedBy', 'questions.options']);
        $assessment->loadCount(['attempts', 'questions']);
        $assessment->loadSum('questions', 'points');

        $canAttempt = $assessment->canBeAttemptedBy($user);
        $latestAttempt = $assessment->attempts()->where('user_id', $user->id)->latest()->first();

        return Inertia::render('assessments/Show', [
            'course' => $course,
            'assessment' => $assessment,
            'canAttempt' => $canAttempt,
            'latestAttempt' => $latestAttempt,
            'can' => [
                'update' => Gate::allows('update', [$assessment, $course]),
                'delete' => Gate::allows('delete', [$assessment, $course]),
                'publish' => Gate::allows('publish', [$assessment, $course]),
                'attempt' => $canAttempt,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Course $course, Assessment $assessment): Response
    {
        Gate::authorize('update', [$assessment, $course]);

        $assessment->load(['questions.options']);

        return Inertia::render('assessments/Edit', [
            'course' => $course,
            'assessment' => $assessment,
            'can' => [
                'publish' => Gate::allows('publish', [$assessment, $course]),
                'delete' => Gate::allows('delete', [$assessment, $course]),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAssessmentRequest $request, Course $course, Assessment $assessment): RedirectResponse
    {
        $validated = $request->validated();

        $assessment->update($validated);

        return redirect()
            ->route('assessments.edit', [$course, $assessment])
            ->with('success', 'Penilaian berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('delete', [$assessment, $course]);

        $assessment->delete();

        return redirect()
            ->route('assessments.index', $course)
            ->with('success', 'Penilaian berhasil dihapus.');
    }

    /**
     * Publish the assessment.
     */
    public function publish(Course $course, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('publish', [$assessment, $course]);

        $assessment->update([
            'status' => 'published',
            'published_at' => now(),
            'published_by' => auth()->id(),
        ]);

        return redirect()
            ->route('assessments.show', [$course, $assessment])
            ->with('success', 'Penilaian berhasil dipublikasikan.');
    }

    /**
     * Unpublish the assessment.
     */
    public function unpublish(Course $course, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('publish', [$assessment, $course]);

        $assessment->update([
            'status' => 'draft',
            'published_at' => null,
            'published_by' => null,
        ]);

        return redirect()
            ->route('assessments.show', [$course, $assessment])
            ->with('success', 'Penilaian berhasil dibatalkan publikasinya.');
    }

    /**
     * Archive the assessment.
     */
    public function archive(Course $course, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('publish', [$assessment, $course]);

        $assessment->update([
            'status' => 'archived',
        ]);

        return redirect()
            ->route('assessments.show', [$course, $assessment])
            ->with('success', 'Penilaian berhasil diarsipkan.');
    }

    /**
     * Start a new assessment attempt.
     */
    public function startAttempt(Request $request, Course $course, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('attempt', [$assessment, $course]);

        $user = $request->user();

        // Check if user can attempt this assessment
        if (! $assessment->canBeAttemptedBy($user)) {
            return back()->with('error', 'Anda tidak dapat mengikuti penilaian ini.');
        }

        // Validate attempt limits - throws MaxAttemptsReachedException if exceeded
        try {
            $assessment->validateAttemptOrFail($user);
        } catch (MaxAttemptsReachedException $e) {
            return back()->with('error', sprintf(
                'Anda telah mencapai batas maksimal percobaan (%d/%d) untuk penilaian ini.',
                $e->getContext()['completed_attempts'],
                $e->getContext()['max_attempts']
            ));
        }

        // Get the next attempt number
        $nextAttemptNumber = $assessment->attempts()->where('user_id', $user->id)->max('attempt_number') + 1;

        // Create new attempt
        $attempt = $assessment->attempts()->create([
            'user_id' => $user->id,
            'attempt_number' => $nextAttemptNumber,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return redirect()
            ->route('assessments.attempt', [$course, $assessment, $attempt])
            ->with('success', 'Penilaian dimulai. Silakan jawab semua pertanyaan.');
    }

    /**
     * Show assessment attempt page.
     */
    public function attempt(Request $request, Course $course, Assessment $assessment, AssessmentAttempt $attempt): Response
    {
        Gate::authorize('viewAttempt', [$attempt, $assessment, $course]);

        $assessment->load(['questions.options']);
        $attempt->load(['answers']);

        return Inertia::render('assessments/Attempt', [
            'course' => $course,
            'assessment' => $assessment,
            'attempt' => $attempt,
            'can' => [
                'submit' => $attempt->isInProgress(),
            ],
        ]);
    }

    /**
     * Submit assessment attempt.
     */
    public function submitAttempt(Request $request, Course $course, Assessment $assessment, AssessmentAttempt $attempt): RedirectResponse
    {
        Gate::authorize('submitAttempt', [$attempt, $assessment, $course]);

        if (! $attempt->isInProgress()) {
            return back()->with('error', 'Penilaian ini tidak dapat diserahkan.');
        }

        // Validate answers - question must belong to THIS assessment
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => [
                'required',
                'integer',
                Rule::exists('questions', 'id')->where('assessment_id', $assessment->id),
            ],
            'answers.*.answer_text' => 'nullable|string',
            'answers.*.selected_options' => 'nullable|array',  // For multiple choice
            'answers.*.selected_options.*' => 'integer',
            'answers.*.file' => 'nullable|file|max:10240', // 10MB max
        ]);

        // Ensure total_points is loaded efficiently
        $assessment->loadSum('questions', 'points');

        // Process answers
        $totalScore = 0;
        $maxScore = $assessment->total_points;

        foreach ($validated['answers'] as $answerData) {
            // Use scoped query to ensure question belongs to this assessment
            /** @var Question|null $question */
            $question = $assessment->questions()->find($answerData['question_id']);

            if (! $question instanceof Question) {
                continue;
            }

            // Handle file upload
            $filePath = null;
            if (isset($answerData['file'])) {
                $filePath = $answerData['file']->store('assessment_answers', 'public');
            }

            // Determine answer value based on question type
            $answerValue = $this->extractAnswerValue($question, $answerData);
            $answerTextForStorage = $this->getAnswerTextForStorage($question, $answerData);

            // Create answer record
            $answer = $attempt->answers()->create([
                'question_id' => $question->id,
                'answer_text' => $answerTextForStorage,
                'file_path' => $filePath,
            ]);

            // Auto-grade using domain strategies (if not manual grading required)
            if (! $question->requiresManualGrading()) {
                $result = $this->gradeQuestion($question, $answerValue);

                $answer->update([
                    'is_correct' => $result->isCorrect,
                    'score' => $result->score,
                    'feedback' => $result->feedback,
                ]);

                $totalScore += $result->score;
            }
        }

        // Update attempt status
        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;
        $passed = $percentage >= $assessment->passing_score;
        $status = $assessment->requiresManualGrading() ? 'submitted' : 'graded';

        $attempt->update([
            'status' => $status,
            'score' => $totalScore,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'passed' => $passed,
            'submitted_at' => now(),
        ]);

        return redirect()
            ->route('assessments.attempt.complete', [$course, $assessment, $attempt])
            ->with('success', 'Penilaian berhasil diserahkan.');
    }

    /**
     * Show attempt completion page.
     */
    public function attemptComplete(Course $course, Assessment $assessment, AssessmentAttempt $attempt): Response
    {
        Gate::authorize('viewAttempt', [$attempt, $assessment, $course]);

        $attempt->load(['answers.question']);

        return Inertia::render('assessments/AttemptComplete', [
            'course' => $course,
            'assessment' => $assessment,
            'attempt' => $attempt,
        ]);
    }

    /**
     * Show grading page for an assessment attempt.
     */
    public function grade(Course $course, Assessment $assessment, AssessmentAttempt $attempt): Response
    {
        Gate::authorize('grade', [$attempt, $assessment, $course]);

        $assessment->load(['questions.options']);
        $attempt->load(['answers.question', 'user']);

        return Inertia::render('assessments/Grade', [
            'course' => $course,
            'assessment' => $assessment,
            'attempt' => $attempt,
            'can' => [
                'submit' => $attempt->isSubmitted(),
            ],
        ]);
    }

    /**
     * Submit grading for an assessment attempt.
     */
    public function submitGrade(Request $request, Course $course, Assessment $assessment, AssessmentAttempt $attempt): RedirectResponse
    {
        Gate::authorize('grade', [$attempt, $assessment, $course]);

        $validated = $request->validate([
            'grades' => 'required|array',
            'grades.*.answer_id' => [
                'required',
                'integer',
                Rule::exists('attempt_answers', 'id')->where('attempt_id', $attempt->id),
            ],
            'grades.*.score' => 'required|numeric|min:0',
            'grades.*.feedback' => 'nullable|string|max:1000',
        ]);

        $totalScore = 0;

        foreach ($validated['grades'] as $gradeData) {
            // Scoped query (validation already ensures ownership)
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

        // Ensure total_points is loaded efficiently
        $assessment->loadSum('questions', 'points');

        // Update attempt with final score
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

        return redirect()
            ->route('assessments.grade', [$course, $assessment, $attempt])
            ->with('success', 'Penilaian berhasil disimpan.');
    }

    /**
     * Grade a question using the appropriate domain strategy.
     */
    protected function gradeQuestion(Question $question, mixed $answer): GradingResult
    {
        $strategy = $this->gradingResolver->resolve($question);

        if ($strategy === null) {
            // No strategy found - return zero score, needs manual grading
            return GradingResult::partial(
                score: 0,
                maxScore: $question->points,
                feedback: 'Tipe soal tidak didukung untuk penilaian otomatis.',
                metadata: ['requires_manual_grading' => true]
            );
        }

        return $strategy->grade($question, $answer);
    }

    /**
     * Extract the appropriate answer value based on question type.
     *
     * @param  array{question_id: int, answer_text?: string|null, selected_options?: array<int>|null, file?: mixed}  $answerData
     */
    protected function extractAnswerValue(Question $question, array $answerData): mixed
    {
        // For multiple choice, use selected options (array of option IDs)
        if ($question->isMultipleChoice()) {
            return $answerData['selected_options'] ?? [];
        }

        // For all other types, use answer_text
        return $answerData['answer_text'] ?? '';
    }

    /**
     * Get the answer text to store in database.
     * For multiple choice, store selected option IDs as JSON.
     *
     * @param  array{question_id: int, answer_text?: string|null, selected_options?: array<int>|null, file?: mixed}  $answerData
     */
    protected function getAnswerTextForStorage(Question $question, array $answerData): ?string
    {
        if ($question->isMultipleChoice() && ! empty($answerData['selected_options'])) {
            return json_encode($answerData['selected_options']);
        }

        return $answerData['answer_text'] ?? null;
    }
}
