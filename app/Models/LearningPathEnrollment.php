<?php

namespace App\Models;

use App\Domain\LearningPath\Events\PathCompleted;
use App\Domain\LearningPath\Events\PathDropped;
use App\Domain\LearningPath\States\ActivePathState;
use App\Domain\LearningPath\States\CompletedCourseState;
use App\Domain\LearningPath\States\CompletedPathState;
use App\Domain\LearningPath\States\DroppedPathState;
use App\Domain\LearningPath\States\PathEnrollmentState;
use App\Domain\Shared\Exceptions\InvalidStateTransitionException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\HasStates;

/**
 * @property int $id
 * @property int $user_id
 * @property int $learning_path_id
 * @property PathEnrollmentState $state
 * @property int $progress_percentage
 * @property Carbon|null $enrolled_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $dropped_at
 * @property string|null $drop_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read LearningPath $learningPath
 * @property-read Collection<int, LearningPathCourseProgress> $courseProgress
 *
 * @method static Builder|LearningPathEnrollment forUser(User $user)
 * @method static Builder|LearningPathEnrollment forPath(LearningPath $path)
 * @method static Builder|LearningPathEnrollment active()
 * @method static Builder|LearningPathEnrollment completed()
 * @method static Builder|LearningPathEnrollment dropped()
 */
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

    // ─────────────────────────────────────────────────────────────────────────────
    // State Behavior Methods
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Drop this learning path enrollment.
     *
     * @throws InvalidStateTransitionException if not currently active
     */
    public function drop(?string $reason = null): self
    {
        if (! $this->isActive()) {
            throw new InvalidStateTransitionException(
                from: (string) $this->state,
                to: DroppedPathState::$name,
                modelType: 'LearningPathEnrollment',
                modelId: $this->id,
                reason: 'Only active enrollments can be dropped'
            );
        }

        DB::transaction(function () use ($reason) {
            $this->update([
                'state' => DroppedPathState::$name,
                'dropped_at' => now(),
                'drop_reason' => $reason,
            ]);
            PathDropped::dispatch($this, $reason);
        });

        return $this;
    }

    /**
     * Complete this learning path enrollment.
     * Idempotent - calling on already completed enrollment is a no-op.
     */
    public function complete(): self
    {
        if ($this->isCompleted()) {
            return $this; // Idempotent
        }

        $completedCourses = $this->courseProgress()
            ->where('state', CompletedCourseState::$name)
            ->count();

        DB::transaction(function () use ($completedCourses) {
            $this->update([
                'state' => CompletedPathState::$name,
                'completed_at' => now(),
                'progress_percentage' => 100,
            ]);
            PathCompleted::dispatch($this, $completedCourses);
        });

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // State Query Methods
    // ─────────────────────────────────────────────────────────────────────────────

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

    /**
     * Get count of completed courses.
     *
     * N+1 safe: Uses pre-loaded count from withCount() if available.
     * Call: $enrollment->loadCount(['courseProgress as completed_courses_count' => fn($q) => $q->where('state', 'completed')])
     */
    public function getCompletedCoursesCountAttribute(): int
    {
        // Use pre-loaded count if available (from withCount)
        if (array_key_exists('completed_courses_count', $this->attributes)) {
            return (int) $this->attributes['completed_courses_count'];
        }

        // Fallback: query (triggers N+1 in loops - avoid!)
        return $this->courseProgress()
            ->where('state', 'completed')
            ->count();
    }

    /**
     * Get total count of courses in the path.
     *
     * N+1 safe: Uses pre-loaded count from withCount() if available.
     * Call: $enrollment->loadCount('courseProgress') or use withCount('courseProgress')
     */
    public function getTotalCoursesCountAttribute(): int
    {
        // Use pre-loaded count if available (from withCount)
        if (array_key_exists('course_progress_count', $this->attributes)) {
            return (int) $this->attributes['course_progress_count'];
        }

        // Fallback: query (triggers N+1 in loops - avoid!)
        return $this->courseProgress()->count();
    }
}
