<?php

namespace App\Http\Controllers;

use App\Domain\LearningPath\Contracts\PathEnrollmentServiceContract;
use App\Domain\LearningPath\Contracts\PathProgressServiceContract;
use App\Domain\LearningPath\Exceptions\AlreadyEnrolledInPathException;
use App\Domain\LearningPath\Exceptions\PathNotPublishedException;
use App\Http\Resources\Enrollment\PathEnrollmentBasicResource;
use App\Http\Resources\Enrollment\PathEnrollmentIndexResource;
use App\Http\Resources\LearningPath\LearningPathBrowseResource;
use App\Http\Resources\LearningPath\LearningPathShowResource;
use App\Http\Resources\Progress\PathProgressResource;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Support\Helpers\DatabaseHelper;
use Illuminate\Database\QueryException;
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

        $enrollmentsQuery = LearningPathEnrollment::query()
            ->forUser($user)
            ->with(['learningPath.courses', 'learningPath.creator', 'courseProgress'])
            ->when($request->status, function ($query, $status) {
                $query->where('state', $status);
            })
            ->orderByDesc('updated_at');

        $paginatedEnrollments = $enrollmentsQuery->paginate(12)->withQueryString();

        return Inertia::render('learner/learning-paths/Index', [
            'enrollments' => $paginatedEnrollments->through(
                fn ($enrollment) => (new PathEnrollmentIndexResource($enrollment))->resolve()
            ),
            'filters' => $request->only(['status']),
        ]);
    }

    /**
     * Browse available learning paths for enrollment.
     */
    public function browse(Request $request): Response
    {
        $user = Auth::user();

        $learningPathsQuery = LearningPath::query()
            ->published()
            ->with(['courses', 'creator'])
            ->withCount(['courses', 'learnerEnrollments as enrollments_count'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->difficulty, function ($query, $difficulty) {
                $query->where('difficulty_level', $difficulty);
            })
            ->orderByDesc('published_at');

        $paginatedPaths = $learningPathsQuery->paginate(12)->withQueryString();

        // Mark which paths the user is already enrolled in
        $enrolledPathIds = $this->enrollmentService
            ->getActiveEnrollments($user)
            ->pluck('learning_path_id')
            ->toArray();

        return Inertia::render('learner/learning-paths/Browse', [
            'learningPaths' => $paginatedPaths->through(
                fn ($path) => (new LearningPathBrowseResource($path))->resolve()
            ),
            'enrolledPathIds' => $enrolledPathIds,
            'filters' => $request->only(['search', 'difficulty']),
        ]);
    }

    /**
     * Show a specific learning path with enrollment option.
     */
    public function show(LearningPath $learningPath): Response
    {
        $this->authorize('view', $learningPath);

        $user = Auth::user();

        $learningPath->load(['courses' => function ($query) {
            $query->withCount('lessons')
                ->orderBy('learning_path_course.position');
        }, 'creator']);
        $learningPath->loadCount(['courses', 'learnerEnrollments as enrollments_count']);

        $enrollment = $this->enrollmentService->getActiveEnrollment($user, $learningPath);
        $progress = null;

        if ($enrollment) {
            $progress = $this->progressService->getProgress($enrollment);
        }

        return Inertia::render('learner/learning-paths/Show', [
            'learningPath' => new LearningPathShowResource($learningPath),
            'enrollment' => $enrollment ? new PathEnrollmentBasicResource($enrollment) : null,
            'progress' => $progress ? new PathProgressResource($progress) : null,
            'canEnroll' => $this->enrollmentService->canEnroll($user, $learningPath),
        ]);
    }

    /**
     * Enroll in a learning path.
     */
    public function enroll(Request $request, LearningPath $learningPath): RedirectResponse
    {
        $user = Auth::user();
        $preserveProgress = $request->boolean('preserve_progress', false);

        try {
            $this->enrollmentService->enroll($user, $learningPath, $preserveProgress);

            return redirect()
                ->route('learner.learning-paths.show', $learningPath)
                ->with('success', 'Anda berhasil mendaftar di learning path ini.');
        } catch (AlreadyEnrolledInPathException) {
            return redirect()
                ->route('learner.learning-paths.show', $learningPath)
                ->with('warning', 'Anda sudah terdaftar di learning path ini.');
        } catch (PathNotPublishedException) {
            return redirect()
                ->route('learner.learning-paths.browse')
                ->with('error', 'Learning path ini tidak tersedia untuk pendaftaran.');
        } catch (QueryException $e) {
            // Handle duplicate key violation (race condition fallback)
            if (DatabaseHelper::isDuplicateKeyException($e)) {
                return redirect()
                    ->route('learner.learning-paths.show', $learningPath)
                    ->with('warning', 'Anda sudah terdaftar di learning path ini.');
            }
            throw $e;
        }
    }

    /**
     * Show detailed progress for a learning path.
     */
    public function progress(LearningPath $learningPath): Response|RedirectResponse
    {
        $user = Auth::user();

        $enrollment = $this->enrollmentService->getActiveEnrollment($user, $learningPath);

        if (! $enrollment) {
            // Also check for completed/dropped enrollments
            $enrollment = LearningPathEnrollment::query()
                ->forUser($user)
                ->forPath($learningPath)
                ->latest()
                ->first();
        }

        if (! $enrollment) {
            return redirect()
                ->route('learner.learning-paths.show', $learningPath)
                ->with('error', 'Anda belum terdaftar di learning path ini.');
        }

        $this->authorize('view', $enrollment);

        $enrollment->load([
            'learningPath.courses' => fn ($query) => $query->withCount('lessons'),
            'courseProgress.course',
        ]);

        $progress = $this->progressService->getProgress($enrollment);

        return Inertia::render('learner/learning-paths/Progress', [
            'learningPath' => new LearningPathShowResource($enrollment->learningPath),
            'enrollment' => new PathEnrollmentBasicResource($enrollment),
            'progress' => new PathProgressResource($progress),
        ]);
    }

    /**
     * Drop from a learning path.
     */
    public function drop(Request $request, LearningPath $learningPath): RedirectResponse
    {
        $user = Auth::user();

        $enrollment = $this->enrollmentService->getActiveEnrollment($user, $learningPath);

        if (! $enrollment) {
            return redirect()
                ->route('learner.learning-paths.show', $learningPath)
                ->with('error', 'Anda tidak memiliki pendaftaran aktif di learning path ini.');
        }

        $this->authorize('drop', $enrollment);

        $reason = $request->input('reason');

        $this->enrollmentService->drop($enrollment, $reason);

        return redirect()
            ->route('learner.learning-paths.index')
            ->with('success', 'Anda telah keluar dari learning path.');
    }
}
