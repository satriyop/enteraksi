<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lesson\StoreLessonRequest;
use App\Http\Requests\Lesson\UpdateLessonRequest;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Services\LessonViewPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    public function __construct(
        protected LessonViewPresenter $presenter
    ) {}

    /**
     * Display the specified lesson for enrolled learners.
     */
    public function show(Request $request, Course $course, Lesson $lesson): Response
    {
        $lessonCourse = $lesson->section->course;
        if ($lessonCourse->id !== $course->id) {
            abort(404);
        }

        Gate::authorize('view', $lesson);

        $course->load(['category', 'user', 'sections.lessons']);
        $lesson->load(['section', 'media']);

        $user = $request->user();
        $enrollment = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        $navigationData = $this->presenter->getLessonViewData($course, $lesson, $enrollment);

        return Inertia::render('lessons/Show', [
            'course' => $course,
            'lesson' => $lesson,
            'enrollment' => $enrollment,
            ...$navigationData,
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

        $maxOrder = $section->lessons()->max('order') ?? 0;
        $validated['order'] = $maxOrder + 1;

        $lesson = $section->lessons()->create($validated);
        $lesson->updateDurations();

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
        $lesson->updateDurations();

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

        $course = $lesson->section->course;
        $lessonId = $lesson->id;
        $lessonTitle = $lesson->title;

        $lesson->delete();

        return redirect()
            ->route('courses.edit', $course)
            ->with('success', 'Pelajaran berhasil dihapus.');
    }
}
