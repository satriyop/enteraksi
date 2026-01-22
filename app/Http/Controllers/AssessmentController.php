<?php

namespace App\Http\Controllers;

use App\Domain\Assessment\Exceptions\MaxAttemptsReachedException;
use App\Domain\Enrollment\DTOs\EnrollmentContext;
use App\Http\Requests\Assessment\BulkGradeAnswersRequest;
use App\Http\Requests\Assessment\StoreAssessmentRequest;
use App\Http\Requests\Assessment\SubmitAssessmentAnswersRequest;
use App\Http\Requests\Assessment\UpdateAssessmentRequest;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Services\AssessmentSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function __construct(
        protected AssessmentSubmissionService $submissionService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Course $course): Response
    {
        $user = $request->user();

        $context = $user->isLearner()
            ? EnrollmentContext::for($user, $course)
            : null;

        Gate::authorize('viewAny', [Assessment::class, $course, $context]);

        $query = Assessment::query()
            ->where('course_id', $course->id)
            ->with(['user', 'publishedBy'])
            ->withCount(['questions', 'attempts']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

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

        if (! $assessment->canBeAttemptedBy($user)) {
            return back()->with('error', 'Anda tidak dapat mengikuti penilaian ini.');
        }

        try {
            $assessment->validateAttemptOrFail($user);
        } catch (MaxAttemptsReachedException $e) {
            return back()->with('error', sprintf(
                'Anda telah mencapai batas maksimal percobaan (%d/%d) untuk penilaian ini.',
                $e->getContext()['completed_attempts'],
                $e->getContext()['max_attempts']
            ));
        }

        $nextAttemptNumber = $assessment->attempts()->where('user_id', $user->id)->max('attempt_number') + 1;

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
    public function submitAttempt(SubmitAssessmentAnswersRequest $request, Course $course, Assessment $assessment, AssessmentAttempt $attempt): RedirectResponse
    {
        Gate::authorize('submitAttempt', [$attempt, $assessment, $course]);

        if (! $attempt->isInProgress()) {
            return back()->with('error', 'Penilaian ini tidak dapat diserahkan.');
        }

        $validated = $request->validated();

        $result = $this->submissionService->submitAttempt($attempt, $validated['answers'], $assessment);

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
    public function submitGrade(BulkGradeAnswersRequest $request, Course $course, Assessment $assessment, AssessmentAttempt $attempt): RedirectResponse
    {
        Gate::authorize('grade', [$attempt, $assessment, $course]);

        $validated = $request->validated();

        $result = $this->submissionService->submitBulkGrades($attempt, $validated['grades'], $assessment);

        return redirect()
            ->route('assessments.grade', [$course, $assessment, $attempt])
            ->with('success', 'Penilaian berhasil disimpan.');
    }
}
