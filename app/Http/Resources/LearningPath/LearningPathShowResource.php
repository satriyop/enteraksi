<?php

namespace App\Http\Resources\LearningPath;

use App\Models\LearningPath;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LearningPath
 */
class LearningPathShowResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'thumbnail_url' => $this->thumbnail_url,
            'difficulty_level' => $this->difficulty_level,
            'estimated_duration' => $this->estimated_duration ?? 0,
            'learning_objectives' => $this->objectives ?? [],
            'prerequisites' => null,
            'courses_count' => $this->courses_count ?? $this->courses->count(),
            'enrollments_count' => $this->enrollments_count ?? 0,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'courses' => LearningPathCourseResource::collection($this->whenLoaded('courses')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
