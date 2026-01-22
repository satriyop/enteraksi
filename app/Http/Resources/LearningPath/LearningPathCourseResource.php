<?php

namespace App\Http\Resources\LearningPath;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Course as part of a learning path (with pivot data).
 *
 * @mixin Course
 */
class LearningPathCourseResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->pivot->id ?? $this->id,
            'course_id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'thumbnail_path' => $this->thumbnail_path,
            'difficulty_level' => $this->difficulty_level,
            'estimated_duration_minutes' => $this->manual_duration_minutes ?? $this->estimated_duration_minutes ?? 0,
            'position' => $this->pivot->position ?? 0,
            'is_required' => $this->pivot->is_required ?? true,
            'lessons_count' => $this->lessons_count ?? $this->lessons()->count(),
        ];
    }
}
