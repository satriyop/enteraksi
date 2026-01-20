<?php

namespace App\Models;

use App\Domain\LearningPath\States\AvailableCourseState;
use App\Domain\LearningPath\States\CompletedCourseState;
use App\Domain\LearningPath\States\CourseProgressState;
use App\Domain\LearningPath\States\InProgressCourseState;
use App\Domain\LearningPath\States\LockedCourseState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\ModelStates\HasStates;

class LearningPathCourseProgress extends Model
{
    use HasFactory, HasStates;

    protected $table = 'learning_path_course_progress';

    protected $fillable = [
        'learning_path_enrollment_id',
        'course_id',
        'state',
        'position',
        'course_enrollment_id',
        'unlocked_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => CourseProgressState::class,
            'position' => 'integer',
            'unlocked_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // State helper methods

    public function isLocked(): bool
    {
        return $this->state instanceof LockedCourseState;
    }

    public function isAvailable(): bool
    {
        return $this->state instanceof AvailableCourseState;
    }

    public function isInProgress(): bool
    {
        return $this->state instanceof InProgressCourseState;
    }

    public function isCompleted(): bool
    {
        return $this->state instanceof CompletedCourseState;
    }

    public function canStart(): bool
    {
        return $this->state->canStart();
    }

    public function blocksNext(): bool
    {
        return $this->state->blocksNext();
    }

    // Relationships

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(LearningPathEnrollment::class, 'learning_path_enrollment_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function courseEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'course_enrollment_id');
    }

    /**
     * Get the path course pivot relationship.
     * This gives access to is_required, min_completion_percentage, etc.
     */
    public function pathCourse(): BelongsTo
    {
        return $this->belongsTo(LearningPathCourse::class, 'course_id', 'course_id')
            ->where('learning_path_id', function ($query) {
                $query->select('learning_path_id')
                    ->from('learning_path_enrollments')
                    ->whereColumn('learning_path_enrollments.id', 'learning_path_course_progress.learning_path_enrollment_id')
                    ->limit(1);
            });
    }

    // Scopes

    public function scopeLocked(Builder $query): Builder
    {
        return $query->where('state', LockedCourseState::$name);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('state', AvailableCourseState::$name);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('state', InProgressCourseState::$name);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('state', CompletedCourseState::$name);
    }

    public function scopeUnlocked(Builder $query): Builder
    {
        return $query->whereIn('state', [
            AvailableCourseState::$name,
            InProgressCourseState::$name,
            CompletedCourseState::$name,
        ]);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position');
    }
}
