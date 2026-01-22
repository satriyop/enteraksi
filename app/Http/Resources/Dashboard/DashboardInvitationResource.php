<?php

namespace App\Http\Resources\Dashboard;

use App\Models\CourseInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for course invitations displayed on the learner dashboard.
 *
 * Used for: invitedCourses
 *
 * @mixin CourseInvitation
 */
class DashboardInvitationResource extends JsonResource
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
            'lessons_count' => $this->course->lessons_count,
            'invited_by' => $this->inviter->name,
            'message' => $this->message,
            'invited_at' => $this->created_at->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
        ];
    }
}
