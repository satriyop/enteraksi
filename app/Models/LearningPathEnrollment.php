<?php

namespace App\Models;

use App\Domain\LearningPath\States\ActivePathState;
use App\Domain\LearningPath\States\CompletedPathState;
use App\Domain\LearningPath\States\DroppedPathState;
use App\Domain\LearningPath\States\PathEnrollmentState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStates\HasStates;

class LearningPathEnrollment extends Model
{
    /** @use HasFactory<\Database\Factories\LearningPathEnrollmentFactory> */
    use HasFactory, HasStates;

    protected $fillable = [
        'user_id',
        'learning_path_id',
        'state',
        'progress_percentage',
        'enrolled_at',
        'completed_at',
        'dropped_at',
        'drop_reason',
    ];

    protected function casts(): array
    {
        return [
            'state' => PathEnrollmentState::class,
            'progress_percentage' => 'integer',
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
            'dropped_at' => 'datetime',
        ];
    }

    // State helper methods

    public function isActive(): bool
    {
        return $this->state instanceof ActivePathState;
    }

    public function isCompleted(): bool
    {
        return $this->state instanceof CompletedPathState;
    }

    public function isDropped(): bool
    {
        return $this->state instanceof DroppedPathState;
    }

    public function canAccessContent(): bool
    {
        return $this->state->canAccessContent();
    }

    public function canTrackProgress(): bool
    {
        return $this->state->canTrackProgress();
    }

    public function canUnlockCourses(): bool
    {
        return $this->state->canUnlockCourses();
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    public function courseProgress(): HasMany
    {
        return $this->hasMany(LearningPathCourseProgress::class, 'learning_path_enrollment_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('state', ActivePathState::$name);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('state', CompletedPathState::$name);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForPath(Builder $query, LearningPath $path): Builder
    {
        return $query->where('learning_path_id', $path->id);
    }

    // Accessors

    public function getIsCompletedAttribute(): bool
    {
        return $this->isCompleted();
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->isActive();
    }

    public function getCompletedCoursesCountAttribute(): int
    {
        return $this->courseProgress()
            ->where('state', 'completed')
            ->count();
    }

    public function getTotalCoursesCountAttribute(): int
    {
        return $this->courseProgress()->count();
    }
}
