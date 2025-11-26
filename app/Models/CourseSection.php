<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'order',
        'estimated_duration_minutes',
    ];

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
