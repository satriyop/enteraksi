<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    protected $fillable = [
        'enrollment_id',
        'lesson_id',
        'current_page',
        'total_pages',
        'highest_page_reached',
        'time_spent_seconds',
        'media_position_seconds',
        'media_duration_seconds',
        'media_progress_percentage',
        'is_completed',
        'last_viewed_at',
        'completed_at',
        'pagination_metadata',
    ];

    protected function casts(): array
    {
        return [
            'current_page' => 'integer',
            'total_pages' => 'integer',
            'highest_page_reached' => 'integer',
            'time_spent_seconds' => 'float',
            'media_position_seconds' => 'integer',
            'media_duration_seconds' => 'integer',
            'media_progress_percentage' => 'decimal:2',
            'is_completed' => 'boolean',
            'last_viewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'pagination_metadata' => 'array',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function updateProgress(int $page, ?int $totalPages = null, ?array $metadata = null): self
    {
        $this->current_page = $page;
        $this->last_viewed_at = now();

        if ($totalPages !== null) {
            $this->total_pages = $totalPages;
        }

        if ($metadata !== null) {
            $this->pagination_metadata = $metadata;
        }

        // Update highest page reached
        if ($page > $this->highest_page_reached) {
            $this->highest_page_reached = $page;
        }

        // Auto-complete when reaching last page
        if ($this->total_pages !== null && $this->highest_page_reached >= $this->total_pages && ! $this->is_completed) {
            $this->markCompleted();
        }

        $this->save();

        return $this;
    }

    public function addTimeSpent(float $seconds): self
    {
        $this->time_spent_seconds += $seconds;
        $this->save();

        return $this;
    }

    /**
     * Update media (video/audio) progress.
     *
     * Auto-completes when media progress reaches 90% or more.
     */
    public function updateMediaProgress(int $positionSeconds, int $durationSeconds): self
    {
        $this->media_position_seconds = $positionSeconds;
        $this->media_duration_seconds = $durationSeconds;
        $this->last_viewed_at = now();

        // Calculate progress percentage
        if ($durationSeconds > 0) {
            $percentage = ($positionSeconds / $durationSeconds) * 100;
            $this->media_progress_percentage = min(100, round($percentage, 2));

            // Auto-complete at 90% watched
            if ($percentage >= 90 && ! $this->is_completed) {
                $this->markCompleted();

                return $this;
            }
        }

        $this->save();

        return $this;
    }

    /**
     * Check if this is a media-based lesson (video/youtube/audio).
     */
    public function isMediaBased(): bool
    {
        return in_array($this->lesson->content_type, ['video', 'youtube', 'audio']);
    }

    /**
     * Get resume position for media playback.
     */
    public function getResumePositionAttribute(): ?int
    {
        return $this->media_position_seconds;
    }

    public function markCompleted(): self
    {
        $this->is_completed = true;
        $this->completed_at = now();
        $this->save();

        // Recalculate course progress
        $this->enrollment->recalculateCourseProgress();

        return $this;
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_pages === null || $this->total_pages === 0) {
            return 0;
        }

        return round(($this->highest_page_reached / $this->total_pages) * 100, 1);
    }

    public function getTimeSpentFormattedAttribute(): string
    {
        $seconds = (int) $this->time_spent_seconds;

        if ($seconds < 60) {
            return "{$seconds} detik";
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return "{$minutes} menit";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours} jam {$remainingMinutes} menit";
    }
}
