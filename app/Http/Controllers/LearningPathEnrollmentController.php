<?php

namespace App\Http\Controllers;

use App\Domain\LearningPath\Contracts\PathEnrollmentServiceContract;
use App\Domain\LearningPath\Contracts\PathProgressServiceContract;
use App\Domain\LearningPath\Exceptions\AlreadyEnrolledInPathException;
use App\Domain\LearningPath\Exceptions\PathNotPublishedException;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LearningPathEnrollmentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PathEnrollmentServiceContract $enrollmentService,
        protected PathProgressServiceContract $progressService
    ) {}

    /**
     * Display learner's enrolled learning paths.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $enrollments = LearningPathEnrollment::query()
            ->forUser($user)
            ->with(['learningPath.courses', 'courseProgress'])
            ->when($request->status, function ($query, $status) {
                $query->where('state', $status);
            })
            ->orderByDesc('updated_at')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('learner/learning-paths/Index', [
            'enrollments' => $enrollments,
            'filters' => $request->only(['status']),
        ]);
    }

    /**
     * Browse available learning paths for enrollment.
     */
    public function browse(Request $request): Response
    {
        $user = Auth::user();

        $learningPaths = LearningPath::query()
            ->published()
            ->with(['courses', 'creator'])
            ->withCount('courses')
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->when($request->difficulty, function ($query, $difficulty) {
                $query->where('difficulty_level', $difficulty);
            })
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        // Mark which paths the user is already enrolled in
        $enrolledPathIds = $this->enrollmentService
            ->getActiveEnrollments($user)
            ->pluck('learning_path_id')
            ->toArray();

        return Inertia::render('learner/learning-paths/Browse', [
            'learningPaths' => $learningPaths,
            'enrolledPathIds' => $enrolledPathIds,
            'filters' => $request->only(['search', 'difficulty']),
        ]);
    }

    /**
     * Show a specific learning path with enrollment option.
     */
    public function show(LearningPath $learningPath): Response
    {
        $user = Auth::user();

        $learningPath->load(['courses' => function ($query) {
            $query->orderBy('learning_path_course.position');
        }, 'creator']);

        $enrollment = $this->enrollmentService->getActiveEnrollment($user, $learningPath);
        $progress = null;

        if ($enrollment) {
            $progress = $this->progressService->getProgress($enrollment);
        }

        return Inertia::render('learner/learning-paths/Show', [
            'learningPath' => $learningPath,
            'enrollment' => $enrollment,
            'progress' => $progress?->toResponse(),
            'canEnroll' => $this->enrollmentService->canEnroll($user, $learningPath),
        ]);
    }

    /**
     * Enroll in a learning path.
     */
    public function enroll(LearningPath $learningPath): RedirectResponse
    {
        $user = Auth::user();

        try {
            $result = $this->enrollmentService->enroll($user, $learningPath);

            return redirect()
                ->route('learner.learning-paths.show', $learningPath)
                ->with('success', 'Anda berhasil mendaftar di learning path ini.');
        } catch (AlreadyEnrolledInPathException $e) {
            return redirect()
                ->route('learner.learning-paths.show', $learningPath)
                ->with('warning', 'Anda sudah terdaftar di learning path ini.');
        } catch (PathNotPublishedException $e) {
            return redirect()
                ->route('learner.learning-paths.browse')
                ->with('error', 'Learning path ini tidak tersedia untuk pendaftaran.');
        }
    }

    /**
     * Show detailed progress for a learning path enrollment.
     */
    public function progress(LearningPathEnrollment $enrollment): Response
    {
        $this->authorize('view', $enrollment);

        $enrollment->load(['learningPath.courses', 'courseProgress.course']);

        $progress = $this->progressService->getProgress($enrollment);

        return Inertia::render('learner/learning-paths/Progress', [
            'enrollment' => $enrollment,
            'progress' => $progress->toResponse(),
        ]);
    }

    /**
     * Drop from a learning path.
     */
    public function drop(Request $request, LearningPathEnrollment $enrollment): RedirectResponse
    {
        $this->authorize('drop', $enrollment);

        $reason = $request->input('reason');

        $this->enrollmentService->drop($enrollment, $reason);

        return redirect()
            ->route('learner.learning-paths.index')
            ->with('success', 'Anda telah keluar dari learning path.');
    }
}
