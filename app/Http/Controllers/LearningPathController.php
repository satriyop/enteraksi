<?php
namespace App\Http\Controllers;

use App\Http\Requests\LearningPath\StoreLearningPathRequest;
use App\Http\Requests\LearningPath\UpdateLearningPathRequest;
use App\Models\Course;
use App\Models\LearningPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LearningPathController extends Controller
{
    public function index(Request $request): Response
    {
        $learningPaths = LearningPath::with(['creator', 'courses'])
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('learning_paths/Index', [
            'learningPaths' => $learningPaths,
            'filters'       => $request->only(['search']),
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
        $validated               = $request->validated();
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $learningPath = LearningPath::create($validated);

        // Attach courses with their positions
        if ($request->has('courses')) {
            foreach ($request->courses as $index => $courseData) {
                $learningPath->courses()->attach($courseData['id'], [
                    'position'                  => $index,
                    'is_required'               => $courseData['is_required'] ?? true,
                    'prerequisites'             => $courseData['prerequisites'] ?? null,
                    'min_completion_percentage' => $courseData['min_completion_percentage'] ?? null,
                ]);
            }
        }

        return redirect()->route('learning-paths.index')
            ->with('success', 'Learning path created successfully.');
    }

    public function show(LearningPath $learningPath): Response
    {
        Gate::authorize('view', $learningPath);

        $learningPath->load(['creator', 'courses' => function ($query) {
            $query->with(['sections', 'enrollments'])->orderBy('position');
        }]);

        return Inertia::render('learning_paths/Show', [
            'learningPath' => $learningPath,
        ]);
    }

    public function edit(LearningPath $learningPath): Response
    {
        Gate::authorize('update', $learningPath);

        $learningPath->load(['courses' => function ($query) {
            $query->withPivot('position', 'is_required', 'prerequisites', 'min_completion_percentage');
        }]);

        $availableCourses = Course::published()->orderBy('title')->get();

        return Inertia::render('learning_paths/Edit', [
            'learningPath'     => $learningPath,
            'availableCourses' => $availableCourses,
        ]);
    }

    public function update(UpdateLearningPathRequest $request, LearningPath $learningPath): RedirectResponse
    {
        $validated               = $request->validated();
        $validated['updated_by'] = Auth::id();

        $learningPath->update($validated);

        // Sync courses with their positions
        if ($request->has('courses')) {
            $syncData = [];
            foreach ($request->courses as $index => $courseData) {
                $syncData[$courseData['id']] = [
                    'position'                  => $index,
                    'is_required'               => $courseData['is_required'] ?? true,
                    'prerequisites'             => $courseData['prerequisites'] ?? null,
                    'min_completion_percentage' => $courseData['min_completion_percentage'] ?? null,
                ];
            }
            $learningPath->courses()->sync($syncData);
        }

        return redirect()->route('learning-paths.show', $learningPath)
            ->with('success', 'Learning path updated successfully.');
    }

    public function destroy(LearningPath $learningPath): RedirectResponse
    {
        Gate::authorize('delete', $learningPath);

        $learningPath->delete();

        return redirect()->route('learning-paths.index')
            ->with('success', 'Learning path deleted successfully.');
    }

    public function publish(LearningPath $learningPath): RedirectResponse
    {
        Gate::authorize('publish', $learningPath);

        $learningPath->update([
            'is_published' => true,
            'published_at' => now(),
            'updated_by'   => Auth::id(),
        ]);

        return redirect()->route('learning-paths.show', $learningPath)
            ->with('success', 'Learning path published successfully.');
    }

    public function unpublish(LearningPath $learningPath): RedirectResponse
    {
        Gate::authorize('publish', $learningPath);

        $learningPath->update([
            'is_published' => false,
            'published_at' => null,
            'updated_by'   => Auth::id(),
        ]);

        return redirect()->route('learning-paths.show', $learningPath)
            ->with('success', 'Learning path unpublished successfully.');
    }

    public function reorder(Request $request, LearningPath $learningPath): RedirectResponse
    {
        Gate::authorize('reorder', $learningPath);

        $request->validate([
            'course_order'            => 'required|array',
            'course_order.*.id'       => 'required|exists:courses,id',
            'course_order.*.position' => 'required|integer|min:0',
        ]);

        foreach ($request->course_order as $item) {
            $learningPath->courses()->updateExistingPivot($item['id'], [
                'position' => $item['position'],
            ]);
        }

        return redirect()->route('learning-paths.show', $learningPath)
            ->with('success', 'Course order updated successfully.');
    }
}