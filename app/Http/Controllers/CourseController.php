<?php

namespace App\Http\Controllers;

use App\Http\Requests\Course\StoreCourseRequest;
use App\Http\Requests\Course\UpdateCourseRequest;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseInvitation;
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
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Course::class);

        $user = $request->user();

        $query = Course::query()
            ->with(['category', 'user', 'tags'])
            ->withCount(['sections', 'lessons', 'enrollments']);

        // For learners, show only published public courses
        if ($user->isLearner()) {
            $query->published()->visible();
        } elseif (! $user->isLmsAdmin()) {
            // Content managers/trainers see their own courses
            $query->where('user_id', $user->id);
        }
        // LMS admin sees all courses

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        // Status filter (only for non-learners)
        if (! $user->isLearner() && ($status = $request->input('status'))) {
            $query->where('status', $status);
        }

        // Category filter
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        // Difficulty filter
        if ($difficulty = $request->input('difficulty_level')) {
            $query->where('difficulty_level', $difficulty);
        }

        $courses = $query->latest()->paginate(12)->withQueryString();

        // Use different view for learners
        $viewName = $user->isLearner() ? 'courses/Browse' : 'courses/Index';

        return Inertia::render($viewName, [
            'courses' => $courses,
            'categories' => Category::orderBy('name')->get(),
            'filters' => $request->only(['search', 'status', 'category_id', 'difficulty_level']),
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

        // Generate slug
        $validated['slug'] = Str::slug($validated['title']).'-'.Str::random(6);
        $validated['user_id'] = $request->user()->id;

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail_path'] = $request->file('thumbnail')
                ->store('courses/thumbnails', 'public');
        }

        $course = Course::create($validated);

        // Sync tags
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
        Gate::authorize('view', $course);

        $user = $request->user();

        $course->load([
            'category',
            'user',
            'tags',
            'sections.lessons',
        ]);

        $course->loadCount(['lessons', 'enrollments']);

        // Check enrollment status for the current user
        $enrollment = $user->enrollments()->where('course_id', $course->id)->first();

        // Check if course is under revision (enrolled user viewing draft course)
        $isUnderRevision = $enrollment && $course->status === 'draft';

        // Get ratings data
        $userRating = $user->courseRatings()->where('course_id', $course->id)->first();
        $ratings = $course->ratings()
            ->with('user:id,name')
            ->latest()
            ->take(10)
            ->get();
        $averageRating = $course->average_rating;
        $ratingsCount = $course->ratings_count;

        // Use different view for learners
        $viewName = $user->isLearner() ? 'courses/Detail' : 'courses/Show';

        // Load invitations for admin view
        $invitations = [];
        if (! $user->isLearner()) {
            $invitations = $course->invitations()
                ->with(['user:id,name,email', 'inviter:id,name'])
                ->latest()
                ->get()
                ->map(fn ($inv) => [
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
                ]);
        }

        return Inertia::render($viewName, [
            'course' => $course,
            'enrollment' => $enrollment,
            'isUnderRevision' => $isUnderRevision,
            'userRating' => $userRating,
            'ratings' => $ratings,
            'averageRating' => $averageRating,
            'ratingsCount' => $ratingsCount,
            'invitations' => $invitations,
            'can' => [
                'update' => Gate::allows('update', $course),
                'delete' => Gate::allows('delete', $course),
                'publish' => Gate::allows('publish', $course),
                'enroll' => Gate::allows('enroll', $course),
                'rate' => $enrollment && ! $userRating,
                'invite' => Gate::allows('create', [CourseInvitation::class, $course]),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Course $course): Response
    {
        Gate::authorize('update', $course);

        $course->load([
            'category',
            'tags',
            'sections.lessons',
        ]);

        return Inertia::render('courses/Edit', [
            'course' => $course,
            'categories' => Category::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
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

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail
            if ($course->thumbnail_path) {
                Storage::disk('public')->delete($course->thumbnail_path);
            }

            $validated['thumbnail_path'] = $request->file('thumbnail')
                ->store('courses/thumbnails', 'public');
        }

        $course->update($validated);

        // Sync tags
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

        // Delete thumbnail
        if ($course->thumbnail_path) {
            Storage::disk('public')->delete($course->thumbnail_path);
        }

        $course->delete();

        return redirect()
            ->route('courses.index')
            ->with('success', 'Kursus berhasil dihapus.');
    }
}
