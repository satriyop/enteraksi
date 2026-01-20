<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseSection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'order',
        'estimated_duration_minutes',
    ];

    protected static function booted(): void
    {
        // Cascade soft delete to lessons when section is deleted
        static::deleting(function (CourseSection $section) {
            if ($section->isForceDeleting()) {
                // Force delete lessons if section is being force deleted
                $section->lessons()->forceDelete();
            } else {
                // Soft delete lessons when section is soft deleted
                $section->lessons()->delete();
            }
        });

        // Restore lessons when section is restored
        static::restoring(function (CourseSection $section) {
            $section->lessons()->onlyTrashed()->restore();
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }

    public function getTotalLessonsAttribute(): int
    {
        return $this->lessons()->count();
    }

    public function getDurationAttribute(): int
    {
        if ($this->estimated_duration_minutes) {
            return $this->estimated_duration_minutes;
        }

        return $this->lessons->sum('estimated_duration_minutes') ?? 0;
    }

    public function calculateEstimatedDuration(): int
    {
        return $this->lessons->sum('estimated_duration_minutes') ?? 0;
    }

    public function updateEstimatedDuration(): void
    {
        $this->update([
            'estimated_duration_minutes' => $this->calculateEstimatedDuration(),
        ]);
    }
}
