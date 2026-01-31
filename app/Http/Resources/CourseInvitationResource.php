<?php

namespace App\Http\Resources;

use App\Models\CourseInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for course invitations (management view).
 *
 * Used in CourseController show page for invitation listing.
 *
 * @mixin CourseInvitation
 */
class CourseInvitationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'status' => $this->status,
            'message' => $this->message,
            'invited_by' => $this->inviter->name,
            'invited_at' => $this->created_at->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'responded_at' => $this->responded_at?->toISOString(),
        ];
    }
}
