<?php

namespace App\Http\Controllers;

use App\Http\Requests\Section\StoreSectionRequest;
use App\Http\Requests\Section\UpdateSectionRequest;
use App\Models\Course;
use App\Models\CourseSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CourseSectionController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSectionRequest $request, Course $course): RedirectResponse
    {
        $validated = $request->validated();

        // Get the next order number
        $maxOrder = $course->sections()->max('order') ?? 0;
        $validated['order'] = $maxOrder + 1;

        $course->sections()->create($validated);

        return redirect()
            ->route('courses.edit', $course)
            ->with('success', 'Bagian berhasil ditambahkan.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSectionRequest $request, CourseSection $section): RedirectResponse
    {
        $section->update($request->validated());

        // Update course duration if section duration changed
        $section->course->updateEstimatedDuration();

        return redirect()
            ->route('courses.edit', $section->course)
            ->with('success', 'Bagian berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CourseSection $section): RedirectResponse
    {
        Gate::authorize('delete', $section);

        $course = $section->course;

        $section->delete();

        // Reorder remaining sections
        $course->sections()
            ->where('order', '>', $section->order)
            ->decrement('order');

        // Update course duration
        $course->updateEstimatedDuration();

        return redirect()
            ->route('courses.edit', $course)
            ->with('success', 'Bagian berhasil dihapus.');
    }
}
