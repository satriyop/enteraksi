<?php

namespace App\Domain\Progress\DTOs;

use App\Data\Progress\LessonProgressData;
use App\Domain\Progress\ValueObjects\AssessmentStats;
use App\Domain\Shared\ValueObjects\Percentage;
use App\Models\LessonProgress;

/**
 * Result of a progress tracking operation.
 *
 * Contains lesson progress data, course completion percentage, and assessment stats.
 *
 * @phpstan-type ProgressDataArray array{
 *     id: int,
 *     enrollment_id: int,
 *     lesson_id: int,
 *     is_completed: bool,
 *     progress_percentage?: int,
 *     time_spent_seconds?: int,
 *     current_page?: int|null,
 *     total_pages?: int|null,
 *     highest_page_reached?: int|null,
 *     media_position_seconds?: int|null,
 *     media_duration_seconds?: int|null,
 *     media_progress_percentage?: int|null,
 *     completed_at?: string|null,
 *     pagination_metadata?: array<string, mixed>|null
 * }
 * @phpstan-type AssessmentStatsArray array{
 *     total: int,
 *     passed: int,
 *     pending: int,
 *     required_total: int,
 *     required_passed: int
 * }
 */
final readonly class ProgressResult
{
    public function __construct(
        public LessonProgressData $progress,
        public Percentage $coursePercentage,
        public bool $lessonCompleted,
        public bool $courseCompleted,
        public ?AssessmentStats $assessmentStats = null,
    ) {}

    /**
     * Create from LessonProgress model and other data.
     *
     * This is the preferred way to create a ProgressResult - it extracts
     * only the necessary data from the model into a serialization-safe format.
     */
    public static function fromProgress(
        LessonProgress $progress,
        Percentage $coursePercentage,
        bool $lessonCompleted,
        bool $courseCompleted,
        ?AssessmentStats $assessmentStats = null
    ): self {
        return new self(
            progress: LessonProgressData::fromModel($progress),
            coursePercentage: $coursePercentage,
            lessonCompleted: $lessonCompleted,
            courseCompleted: $courseCompleted,
            assessmentStats: $assessmentStats,
        );
    }

    /**
     * @param  array{
     *     progress: ProgressDataArray,
     *     course_percentage: float|int,
     *     lesson_completed: bool,
     *     course_completed: bool,
     *     assessment_stats?: AssessmentStatsArray|null
     * }  $data
     */
    public static function fromArray(array $data): static
    {
        return new self(
            progress: LessonProgressData::from($data['progress']),
            coursePercentage: new Percentage($data['course_percentage']),
            lessonCompleted: $data['lesson_completed'],
            courseCompleted: $data['course_completed'],
            assessmentStats: isset($data['assessment_stats'])
                ? AssessmentStats::fromArray($data['assessment_stats'])
                : null,
        );
    }

    public function toResponse(): array
    {
        $response = [
            'progress' => $this->progress->toArray(),
            'course_percentage' => $this->coursePercentage->value,
            'lesson_completed' => $this->lessonCompleted,
            'course_completed' => $this->courseCompleted,
        ];

        if ($this->assessmentStats !== null) {
            $response['assessment_stats'] = $this->assessmentStats->toResponse();
        }

        return $response;
    }
}
