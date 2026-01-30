<?php

namespace App\Http\Controllers;

use App\Domain\Enrollment\DTOs\EnrollmentContext;
use App\Domain\Progress\Services\ProgressTrackingService;
use App\Http\Requests\Course\StoreCourseRequest;
use App\Http\Requests\Course\UpdateCourseRequest;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\Enrollment;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function __construct(
        protected ProgressTrackingService $progressService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Course::class);

        $user = $request->user();

        $query = Course::query()
            ->with(['category', 'user', 'tags'])
            ->withCount(['sections', 'lessons', 'enrollments', 'ratings'])
            ->withAvg('ratings', 'rating');

        if ($user->isLearner()) {
            $query->published()->visible();
        } elseif (! $user->isLmsAdmin()) {
            $query->where('user_id', $user->id);
        }

        $this->applyFilters($query, $request, $user);

        $courses = $query->latest()->paginate(12)->withQueryString();

        $viewName = $user->isLearner() ? 'courses/Browse' : 'courses/Index';

        $enrollmentMap = $this->getEnrollmentMapForCourses($user, $courses);

        return Inertia::render($viewName, [
            'courses' => $courses,
            'categories' => Category::orderBy('name')->get(),
            'filters' => $request->only(['search', 'status', 'category_id', 'difficulty_level']),
            'enrollmentMap' => $enrollmentMap,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        Gate::authorize('create', Course::class);

        return Inertia::render('courses/Create', [
            'categories' => Category::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $validated['slug'] = Str::slug($validated['title']).'-'.Str::random(6);
        $validated['user_id'] = $request->user()->id;

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail_path'] = $request->file('thumbnail')
                ->store('courses/thumbnails', 'public');
        }

        $course = Course::create($validated);

        if (isset($validated['tags'])) {
            $course->tags()->sync($validated['tags']);
        }

        return redirect()
            ->route('courses.edit', $course)
            ->with('success', 'Kursus berhasil dibuat. Silakan tambahkan konten.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Course $course): Response
    {
        $user = $request->user();

        $enrollment = Enrollment::query()
            ->forUserAndCourse($user, $course)
            ->first();

        $enrollmentContext = EnrollmentContext::fromData(
            isActivelyEnrolled: $enrollment && $enrollment->status === 'active',
            hasPendingInvitation: $user->courseInvitations()
                ->where('course_id', $course->id)
                ->where('status', 'pending')
                ->exists(),
            hasAnyEnrollment: $enrollment !== null,
        );

        Gate::authorize('view', [$course, $enrollmentContext]);

        $course->load(['category', 'user', 'tags', 'sections.lessons']);
        $course->loadCount(['lessons', 'enrollments', 'ratings']);
        $course->loadAvg('ratings', 'rating');

        $assessmentStats = $enrollment
            ? $this->progressService->getAssessmentStats($enrollment)->toResponse()
            : null;

        $ratings = $this->getRatingsForCourse($course);

        $viewName = $user->isLearner() ? 'courses/Detail' : 'courses/Show';

        $invitations = $user->isLearner()
            ? []
            : $this->getInvitationsForCourse($course);

        return Inertia::render($viewName, [
            'course' => $course,
            'enrollment' => $enrollment,
            'isUnderRevision' => $enrollment && $course->status === 'draft',
            'assessmentStats' => $assessmentStats,
            'userRating' => $user->courseRatings()->where('course_id', $course->id)->first(),
            'ratings' => $ratings['collection'],
            'averageRating' => $ratings['average'],
            'ratingsCount' => $ratings['count'],
            'invitations' => $invitations,
            'can' => $this->getCoursePermissions($user, $course, $enrollment, $enrollmentContext),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Course $course): Response
    {
        Gate::authorize('update', $course);

        $course->load(['category', 'tags', 'sections.lessons']);

        $activeEnrollmentsCount = $course->enrollments()->where('status', 'active')->count();

        return Inertia::render('courses/Edit', [
            'course' => $course,
            'categories' => Category::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'activeEnrollmentsCount' => $activeEnrollmentsCount,
            'can' => [
                'publish' => Gate::allows('publish', $course),
                'setStatus' => Gate::allows('setStatus', $course),
                'setVisibility' => Gate::allows('setVisibility', $course),
                'delete' => Gate::allows('delete', $course),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCourseRequest $request, Course $course): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('thumbnail')) {
            if ($course->thumbnail_path) {
                Storage::disk('public')->delete($course->thumbnail_path);
            }
            $validated['thumbnail_path'] = $request->file('thumbnail')
                ->store('courses/thumbnails', 'public');
        }

        $course->update($validated);

        if (isset($validated['tags'])) {
            $course->tags()->sync($validated['tags']);
        }

        return redirect()
            ->route('courses.edit', $course)
            ->with('success', 'Kursus berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course): RedirectResponse
    {
        Gate::authorize('delete', $course);

        if ($course->thumbnail_path) {
            Storage::disk('public')->delete($course->thumbnail_path);
        }

        $course->delete();

        return redirect()
            ->route('courses.index')
            ->with('success', 'Kursus berhasil dihapus.');
    }

    /**
     * Apply filters to the query.
     */
    protected function applyFilters($query, Request $request, $user): void
    {
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        if (! $user->isLearner() && ($status = $request->input('status'))) {
            $query->where('status', $status);
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($difficulty = $request->input('difficulty_level')) {
            $query->where('difficulty_level', $difficulty);
        }
    }

    /**
     * Get enrollment map for courses.
     */
    protected function getEnrollmentMapForCourses($user, $courses): array
    {
        if (! $user->isLearner()) {
            return [];
        }

        $courseIds = $courses->pluck('id')->toArray();

        return Enrollment::query()
            ->where('user_id', $user->id)
            ->whereIn('course_id', $courseIds)
            ->get()
            ->mapWithKeys(fn (Enrollment $e) => [
                $e->course_id => [
                    'status' => $e->status->getValue(),
                    'progress_percentage' => $e->progress_percentage,
                ],
            ])
            ->toArray();
    }

    /**
     * Get ratings for course.
     */
    protected function getRatingsForCourse(Course $course): array
    {
        return [
            'collection' => $course->ratings()
                ->with('user:id,name')
                ->latest()
                ->take(10)
                ->get(),
            'average' => $course->average_rating,
            'count' => $course->ratings_count,
        ];
    }

    /**
     * Get invitations for course.
     */
    protected function getInvitationsForCourse(Course $course): array
    {
        return CourseInvitation::query()
            ->where('course_id', $course->id)
            ->with(['user:id,name,email', 'inviter:id,name'])
            ->latest()
            ->get()
            ->map(fn (CourseInvitation $inv) => [
                'id' => $inv->id,
                'user' => [
                    'id' => $inv->user->id,
                    'name' => $inv->user->name,
                    'email' => $inv->user->email,
                ],
                'status' => $inv->status,
                'message' => $inv->message,
                'invited_by' => $inv->inviter->name,
                'invited_at' => $inv->created_at->toISOString(),
                'expires_at' => $inv->expires_at?->toISOString(),
                'responded_at' => $inv->responded_at?->toISOString(),
            ])
            ->toArray();
    }

    /**
     * Get course permissions.
     */
    protected function getCoursePermissions($user, Course $course, ?Enrollment $enrollment, EnrollmentContext $context): array
    {
        return [
            'update' => Gate::allows('update', $course),
            'delete' => Gate::allows('delete', $course),
            'publish' => Gate::allows('publish', $course),
            'enroll' => Gate::allows('enroll', [$course, $context]),
            'rate' => $enrollment && ! $user->courseRatings()->where('course_id', $course->id)->exists(),
            'invite' => Gate::allows('create', [CourseInvitation::class, $course]),
        ];
    }
}
