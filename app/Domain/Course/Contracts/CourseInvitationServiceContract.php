<?php

namespace App\Domain\Course\Contracts;

use App\Models\Course;
use App\Models\User;

/**
 * Contract for course invitation management services.
 *
 * Handles bulk importing invitations from CSV and managing
 * enrollment/invitation status checks.
 */
interface CourseInvitationServiceContract
{
    /**
     * Get user IDs that should be excluded from invitations
     * (already enrolled or have pending invitations).
     *
     * @return array<int>
     */
    public function getExcludedUserIds(Course $course): array;

    /**
     * Import invitations from CSV file data.
     *
     * @param  array<array<string|null>>  $csvData  Parsed CSV rows (without headers)
     * @param  int  $emailIndex  Index of email column in CSV rows
     * @param  int  $courseId  Course ID for invitations
     * @param  int  $invitedBy  User ID who is sending invitations
     * @param  string|null  $message  Optional message to include with invitations
     * @param  \Carbon\Carbon|null  $expiresAt  Optional expiration date
     * @return array{success: int, skipped: int, errors: array<string>}
     */
    public function importFromCsv(
        array $csvData,
        int $emailIndex,
        int $courseId,
        int $invitedBy,
        ?string $message = null,
        ?\Carbon\Carbon $expiresAt = null
    ): array;

    /**
     * Check if a user can be invited to a course.
     *
     * @return array{can: bool, reason?: string}
     */
    public function canInvite(User $user, Course $course): array;
}
