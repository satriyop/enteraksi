<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    /** @use HasFactory<\Database\Factories\EnrollmentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'status',
        'progress_percentage',
        'enrolled_at',
        'started_at',
        'completed_at',
        'invited_by',
        'last_lesson_id',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function lastLesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'last_lesson_id');
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function getProgressForLesson(Lesson $lesson): ?LessonProgress
    {
        return $this->lessonProgress()->where('lesson_id', $lesson->id)->first();
    }

    public function getOrCreateProgressForLesson(Lesson $lesson): LessonProgress
    {
        return $this->lessonProgress()->firstOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'current_page' => 1,
                'highest_page_reached' => 1,
                'time_spent_seconds' => 0,
                'is_completed' => false,
            ]
        );
    }

    public function recalculateCourseProgress(): void
    {
        $totalLessons = $this->course->lessons()->count();

        if ($totalLessons === 0) {
            $this->progress_percentage = 0;
            $this->save();

            return;
        }

        $completedLessons = $this->lessonProgress()
            ->where('is_completed', true)
            ->count();

        $this->progress_percentage = round(($completedLessons / $totalLessons) * 100, 1);

        // Mark enrollment as completed if all lessons are done
        if ($completedLessons >= $totalLessons && $this->status !== 'completed') {
            $this->status = 'completed';
            $this->completed_at = now();
        }

        $this->save();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }
}
