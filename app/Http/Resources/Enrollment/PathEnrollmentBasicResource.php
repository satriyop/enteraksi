<?php

namespace App\Http\Resources\Enrollment;

use App\Models\LearningPathEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LearningPathEnrollment
 */
class PathEnrollmentBasicResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'learning_path_id' => $this->learning_path_id,
            'user_id' => $this->user_id,
            'state' => (string) $this->state,
            'enrolled_at' => $this->enrolled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'dropped_at' => $this->dropped_at?->toIso8601String(),
        ];
    }
}
