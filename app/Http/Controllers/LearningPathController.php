<?php

namespace App\Http\Controllers;

use App\Http\Requests\LearningPath\ReorderPathCoursesRequest;
use App\Http\Requests\LearningPath\StoreLearningPathRequest;
use App\Http\Requests\LearningPath\UpdateLearningPathRequest;
use App\Models\Course;
use App\Models\LearningPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LearningPathController extends Controller
{
    public function index(Request $request): Response
    {
        $learningPaths = LearningPath::with(['creator', 'courses'])
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('learning_paths/Index', [
            'learningPaths' => $learningPaths,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $courses = Course::published()->orderBy('title')->get();

        return Inertia::render('learning_paths/Create', [
            'courses' => $courses,
        ]);
    }

    public function store(StoreLearningPathRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();
        $validated['slug'] = Str::slug($validated['title']).'-'.Str::random(6);

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('learning_paths/thumbnails', 'public');
            $validated['thumbnail_url'] = $thumbnailPath;
        }

        $learningPath = LearningPath::create($validated);

        // Attach courses with their positions
        if ($request->has('courses')) {
            foreach ($request->courses as $index => $courseData) {
                $learningPath->courses()->attach($courseData['id'], [
                    'position' => $index,
                    'is_required' => $courseData['is_required'] ?? true,
                    'prerequisites' => $courseData['prerequisites'] ?? null,
                    'min_completion_percentage' => $courseData['min_completion_percentage'] ?? null,
                ]);
            }
        }

        return redirect()->route('learning-paths.index')
            ->with('success', 'Learning path created successfully.');
    }

    public function show(LearningPath $learning_path): Response
    {
        Gate::authorize('view', $learning_path);

        $learning_path->load(['creator', 'courses' => function ($query) {
            $query->with(['sections', 'enrollments'])->orderBy('position');
        }]);

        return Inertia::render('learning_paths/Show', [
            'learningPath' => $learning_path,
        ]);
    }

    public function edit(LearningPath $learning_path): Response
    {
        Gate::authorize('update', $learning_path);

        $learning_path->load(['courses' => function ($query) {
            $query->withPivot('position', 'is_required', 'prerequisites', 'min_completion_percentage');
        }]);

        $availableCourses = Course::published()->orderBy('title')->get();

        return Inertia::render('learning_paths/Edit', [
            'learningPath' => $learning_path,
            'availableCourses' => $availableCourses,
        ]);
    }

    public function update(UpdateLearningPathRequest $request, LearningPath $learning_path): RedirectResponse
    {
        \Log::info('Update request data:', $request->all());
        \Log::info('Validated data:', $request->validated());

        $validated = $request->validated();
        $validated['updated_by'] = Auth::id();

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('learning_paths/thumbnails', 'public');
            $validated['thumbnail_url'] = $thumbnailPath;
        }

        // Auto-generate slug from title if title changed
        if ($request->title !== $learning_path->title) {
            $validated['slug'] = Str::slug($request->title).'-'.Str::random(6);
        }

        $learning_path->update($validated);

        // Sync courses with their positions
        if ($request->has('courses')) {
            $syncData = [];
            foreach ($request->courses as $index => $courseData) {
                $syncData[$courseData['id']] = [
                    'position' => $index,
                    'is_required' => $courseData['is_required'] ?? true,
                    'prerequisites' => $courseData['prerequisites'] ?? null,
                    'min_completion_percentage' => $courseData['min_completion_percentage'] ?? null,
                ];
            }
            $learning_path->courses()->sync($syncData);
        }

        return redirect()->route('learning-paths.show', $learning_path)
            ->with('success', 'Learning path updated successfully.');
    }

    public function destroy(LearningPath $learning_path): RedirectResponse
    {
        Gate::authorize('delete', $learning_path);

        $learning_path->delete();

        return redirect()->route('learning-paths.index')
            ->with('success', 'Learning path deleted successfully.');
    }

    public function publish(LearningPath $learning_path): RedirectResponse
    {
        Gate::authorize('publish', $learning_path);

        $learning_path->update([
            'is_published' => true,
            'published_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('learning-paths.show', $learning_path)
            ->with('success', 'Learning path published successfully.');
    }

    public function unpublish(LearningPath $learning_path): RedirectResponse
    {
        Gate::authorize('publish', $learning_path);

        $learning_path->update([
            'is_published' => false,
            'published_at' => null,
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('learning-paths.show', $learning_path)
            ->with('success', 'Learning path unpublished successfully.');
    }

    public function reorder(ReorderPathCoursesRequest $request, LearningPath $learning_path): RedirectResponse
    {
        Gate::authorize('reorder', $learning_path);

        $validated = $request->validated();

        foreach ($validated['course_order'] as $item) {
            $learning_path->courses()->updateExistingPivot($item['id'], [
                'position' => $item['position'],
            ]);
        }

        return redirect()->route('learning-paths.show', $learning_path)
            ->with('success', 'Course order updated successfully.');
    }
}
