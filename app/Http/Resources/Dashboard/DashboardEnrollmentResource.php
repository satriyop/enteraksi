<?php

namespace App\Http\Resources\Dashboard;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for enrollments displayed on the learner dashboard.
 *
 * Used for: myLearning
 *
 * @mixin Enrollment
 */
class DashboardEnrollmentResource extends JsonResource
{
    /**
     * Disable wrapping for Inertia compatibility.
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course->id,
            'title' => $this->course->title,
            'slug' => $this->course->slug,
            'short_description' => $this->course->short_description,
            'thumbnail_path' => $this->course->thumbnail_url,
            'difficulty_level' => $this->course->difficulty_level,
            'duration' => $this->course->duration,
            'instructor' => $this->course->user->name,
            'category' => $this->course->category?->name,
            'progress_percentage' => $this->progress_percentage,
            'enrolled_at' => $this->enrolled_at->toDateTimeString(),
            'last_lesson_id' => $this->last_lesson_id,
            'lessons_count' => $this->course->lessons_count,
            'status' => $this->status,
        ];
    }
}
