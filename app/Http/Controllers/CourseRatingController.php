<?php

namespace App\Http\Controllers;

use App\Http\Requests\CourseRating\StoreRatingRequest;
use App\Http\Requests\CourseRating\UpdateRatingRequest;
use App\Models\Course;
use App\Models\CourseRating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CourseRatingController extends Controller
{
    /**
     * Store a newly created rating.
     */
    public function store(StoreRatingRequest $request, Course $course): RedirectResponse
    {
        $course->ratings()->create([
            'user_id' => $request->user()->id,
            'rating' => $request->validated('rating'),
            'review' => $request->validated('review'),
        ]);

        return back()->with('success', 'Rating berhasil ditambahkan.');
    }

    /**
     * Update the specified rating.
     */
    public function update(UpdateRatingRequest $request, Course $course, CourseRating $rating): RedirectResponse
    {
        $rating->update($request->validated());

        return back()->with('success', 'Rating berhasil diperbarui.');
    }

    /**
     * Remove the specified rating.
     */
    public function destroy(Request $request, Course $course, CourseRating $rating): RedirectResponse
    {
        Gate::authorize('delete', $rating);

        $rating->delete();

        return back()->with('success', 'Rating berhasil dihapus.');
    }
}
