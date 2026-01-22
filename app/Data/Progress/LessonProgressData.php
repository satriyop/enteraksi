<?php

namespace App\Data\Progress;

use App\Models\LessonProgress;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class LessonProgressData extends Data
{
    public function __construct(
        public int $id,
        public int $enrollment_id,
        public int $lesson_id,
        public bool $is_completed,
        public int $progress_percentage,
        public int $time_spent_seconds,
        public ?int $current_page = null,
        public ?int $total_pages = null,
        public ?int $highest_page_reached = null,
        public ?int $media_position_seconds = null,
        public ?int $media_duration_seconds = null,
        public ?int $media_progress_percentage = null,
        public ?string $completed_at = null,
        /** @var array<string, mixed>|null */
        public ?array $pagination_metadata = null,
    ) {}

    public static function fromModel(LessonProgress $progress): self
    {
        return new self(
            id: $progress->id,
            enrollment_id: $progress->enrollment_id,
            lesson_id: $progress->lesson_id,
            is_completed: $progress->is_completed,
            progress_percentage: $progress->progress_percentage ?? 0,
            time_spent_seconds: $progress->time_spent_seconds ?? 0,
            current_page: $progress->current_page,
            total_pages: $progress->total_pages,
            highest_page_reached: $progress->highest_page_reached,
            media_position_seconds: $progress->media_position_seconds,
            media_duration_seconds: $progress->media_duration_seconds,
            media_progress_percentage: $progress->media_progress_percentage !== null ? (int) $progress->media_progress_percentage : null,
            completed_at: $progress->completed_at?->toIso8601String(),
            pagination_metadata: $progress->pagination_metadata,
        );
    }

    public function getTimeSpentFormatted(): string
    {
        $seconds = $this->time_spent_seconds;

        if ($seconds < 60) {
            return "{$seconds} detik";
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return $remainingSeconds > 0
                ? "{$minutes} menit {$remainingSeconds} detik"
                : "{$minutes} menit";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return $remainingMinutes > 0
            ? "{$hours} jam {$remainingMinutes} menit"
            : "{$hours} jam";
    }
}
