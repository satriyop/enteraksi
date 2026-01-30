<?php

namespace App\Domain\Course\Services;


use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\User;

class CourseInvitationService
{
    /**
     * Get user IDs that should be excluded from invitations.
     *
     * Excludes users who are already enrolled or have pending invitations.
     *
     * @return array<int>
     */
    public function getExcludedUserIds(Course $course): array
    {
        $enrolledUserIds = $course->enrollments()
            ->where('status', 'active')
            ->pluck('user_id')
            ->toArray();

        $pendingInvitationUserIds = CourseInvitation::where('course_id', $course->id)
            ->where('status', 'pending')
            ->pluck('user_id')
            ->toArray();

        return array_merge($enrolledUserIds, $pendingInvitationUserIds);
    }

    /**
     * Import invitations from CSV file data.
     *
     * @param  array<array<string, string>>  $csvData  Parsed CSV rows (without headers)
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
    ): array {
        $results = [
            'success' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $course = Course::find($courseId);
        if (! $course) {
            return $results;
        }

        $excludeUserIds = $this->getExcludedUserIds($course);

        foreach ($csvData as $index => $row) {
            $rowNumber = $index + 2;

            if (empty($row) || ! isset($row[$emailIndex])) {
                continue;
            }

            $email = trim($row[$emailIndex] ?? '');
            if (empty($email)) {
                continue;
            }

            $user = User::where('email', $email)->where('role', 'learner')->first();

            if (! $user) {
                $results['errors'][] = "Baris {$rowNumber}: Email '{$email}' tidak ditemukan atau bukan learner.";
                $results['skipped']++;

                continue;
            }

            if (in_array($user->id, $excludeUserIds)) {
                $results['errors'][] = "Baris {$rowNumber}: '{$email}' sudah terdaftar atau memiliki undangan.";
                $results['skipped']++;

                continue;
            }

            CourseInvitation::create([
                'user_id' => $user->id,
                'course_id' => $courseId,
                'invited_by' => $invitedBy,
                'message' => $message,
                'expires_at' => $expiresAt,
                'status' => 'pending',
            ]);

            $excludeUserIds[] = $user->id;
            $results['success']++;
        }

        return $results;
    }

    /**
     * Check if a user can be invited to a course.
     *
     * @return array{can: bool, reason?: string}
     */
    public function canInvite(User $user, Course $course): array
    {
        if ($user->role !== 'learner') {
            return [
                'can' => false,
                'reason' => 'Hanya learner yang dapat diundang.',
            ];
        }

        $isEnrolled = $course->enrollments()
            ->where('status', 'active')
            ->where('user_id', $user->id)
            ->exists();

        if ($isEnrolled) {
            return [
                'can' => false,
                'reason' => 'User sudah terdaftar di kursus.',
            ];
        }

        $hasPendingInvitation = CourseInvitation::where('course_id', $course->id)
            ->where('status', 'pending')
            ->where('user_id', $user->id)
            ->exists();

        if ($hasPendingInvitation) {
            return [
                'can' => false,
                'reason' => 'User sudah memiliki undangan tertunda.',
            ];
        }

        return ['can' => true];
    }
}
