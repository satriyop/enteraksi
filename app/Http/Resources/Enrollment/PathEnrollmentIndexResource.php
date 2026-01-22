<?php

namespace App\Http\Resources\Enrollment;

use App\Http\Resources\LearningPath\LearningPathBrowseResource;
use App\Models\LearningPathEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LearningPathEnrollment
 */
class PathEnrollmentIndexResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $path = $this->learningPath;
        $completedCourses = $this->courseProgress->where('state', 'completed')->count();
        $totalCourses = $path->courses->count();

        return [
            'id' => $this->id,
            'learning_path' => new LearningPathBrowseResource($path),
            'state' => (string) $this->state,
            'progress_percentage' => $this->progress_percentage ?? 0,
            'completed_courses' => $completedCourses,
            'total_courses' => $totalCourses,
            'enrolled_at' => $this->enrolled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
