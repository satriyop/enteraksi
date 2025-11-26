<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lesson\StoreLessonRequest;
use App\Http\Requests\Lesson\UpdateLessonRequest;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    /**
     * Display the specified lesson for enrolled learners.
     */
    public function show(Request $request, Course $course, Lesson $lesson): Response
    {
        // Verify the lesson belongs to this course
        $lessonCourse = $lesson->section->course;
        if ($lessonCourse->id !== $course->id) {
            abort(404);
        }

        // Check if user can view this lesson (enrolled or course manager)
        Gate::authorize('view', $lesson);

        $course->load([
            'category',
            'user',
            'sections.lessons',
        ]);

        $lesson->load(['section', 'media']);

        // Get user enrollment
        $user = $request->user();
        $enrollment = $user->enrollments()->where('course_id', $course->id)->first();

        // Get all lessons for navigation
        $allLessons = collect();
        foreach ($course->sections as $section) {
            foreach ($section->lessons as $l) {
                $allLessons->push([
                    'id' => $l->id,
                    'title' => $l->title,
                    'section_title' => $section->title,
                    'order' => $section->order * 1000 + $l->order,
                ]);
            }
        }
        $allLessons = $allLessons->sortBy('order')->values();

        $currentIndex = $allLessons->search(fn ($l) => $l['id'] === $lesson->id);
        $prevLesson = $currentIndex > 0 ? $allLessons[$currentIndex - 1] : null;
        $nextLesson = $currentIndex < $allLessons->count() - 1 ? $allLessons[$currentIndex + 1] : null;

        return Inertia::render('lessons/Show', [
            'course' => $course,
            'lesson' => $lesson,
            'enrollment' => $enrollment,
            'prevLesson' => $prevLesson,
            'nextLesson' => $nextLesson,
            'allLessons' => $allLessons,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(CourseSection $section): Response
    {
        Gate::authorize('create', [Lesson::class, $section]);

        return Inertia::render('lessons/Edit', [
            'section' => $section->load('course'),
            'lesson' => null,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLessonRequest $request, CourseSection $section): RedirectResponse
    {
        $validated = $request->validated();

        // Get the next order number
        $maxOrder = $section->lessons()->max('order') ?? 0;
        $validated['order'] = $maxOrder + 1;

        $lesson = $section->lessons()->create($validated);

        // Update section and course duration
        $section->updateEstimatedDuration();
        $section->course->updateEstimatedDuration();

        return redirect()
            ->route('lessons.edit', $lesson)
            ->with('success', 'Pelajaran berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Lesson $lesson): Response
    {
        Gate::authorize('update', $lesson);

        $lesson->load(['section.course', 'media']);

        return Inertia::render('lessons/Edit', [
            'section' => $lesson->section,
            'lesson' => $lesson,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLessonRequest $request, Lesson $lesson): RedirectResponse
    {
        $lesson->update($request->validated());

        // Update section and course duration
        $lesson->section->updateEstimatedDuration();
        $lesson->section->course->updateEstimatedDuration();

        return redirect()
            ->route('lessons.edit', $lesson)
            ->with('success', 'Pelajaran berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Lesson $lesson): RedirectResponse
    {
        Gate::authorize('delete', $lesson);

        $section = $lesson->section;
        $course = $section->course;

        $lesson->delete();

        // Reorder remaining lessons
        $section->lessons()
            ->where('order', '>', $lesson->order)
            ->decrement('order');

        // Update section and course duration
        $section->updateEstimatedDuration();
        $course->updateEstimatedDuration();

        return redirect()
            ->route('courses.edit', $course)
            ->with('success', 'Pelajaran berhasil dihapus.');
    }
}
