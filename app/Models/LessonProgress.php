<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $enrollment_id
 * @property int $lesson_id
 * @property int $current_page
 * @property int $total_pages
 * @property int $highest_page_reached
 * @property float $time_spent_seconds
 * @property int|null $media_position_seconds
 * @property int|null $media_duration_seconds
 * @property float|null $media_progress_percentage
 * @property bool $is_completed
 * @property Carbon|null $last_viewed_at
 * @property Carbon|null $completed_at
 * @property array|null $pagination_metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $resume_position
 * @property-read Enrollment $enrollment
 * @property-read Lesson $lesson
 */
class LessonProgress extends Model
{
    use HasFactory;

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
