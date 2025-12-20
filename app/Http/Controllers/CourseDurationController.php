<?php
namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CourseDurationController extends Controller
{
    /**
     * Recalculate the course duration based on lesson durations.
     */
    public function recalculate(Course $course): RedirectResponse
    {
        Gate::authorize('update', $course);

        $course->updateEstimatedDuration();

        return redirect()
            ->route('courses.edit', $course)
            ->with('success', 'Durasi kursus berhasil diperbarui berdasarkan durasi materi.');
    }
}