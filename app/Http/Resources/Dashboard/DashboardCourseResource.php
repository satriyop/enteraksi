<?php

namespace App\Http\Resources\Dashboard;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for courses displayed on the learner dashboard.
 *
 * Used for: featuredCourses, browseCourses
 *
 * @mixin Course
 */
class DashboardCourseResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'thumbnail_path' => $this->thumbnail_url,
            'difficulty_level' => $this->difficulty_level,
            'duration' => $this->duration,
            'instructor' => $this->whenLoaded('user', fn () => $this->user->name),
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'enrollments_count' => $this->enrollments_count ?? null,
        ];
    }
}
