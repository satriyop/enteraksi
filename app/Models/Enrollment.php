<?php

namespace App\Models;

use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\Enrollment\Events\UserDropped;
use App\Domain\Enrollment\Events\UserReenrolled;
use App\Domain\Enrollment\States\ActiveState;
use App\Domain\Enrollment\States\CompletedState;
use App\Domain\Enrollment\States\DroppedState;
use App\Domain\Enrollment\States\EnrollmentState;
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
 * @property int $course_id
 * @property EnrollmentState $status
 * @property int $progress_percentage
 * @property Carbon|null $enrolled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $dropped_at
 * @property string|null $drop_reason
 * @property int|null $invited_by
 * @property int|null $last_lesson_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Course $course
 * @property-read User|null $inviter
 * @property-read Lesson|null $lastLesson
 * @property-read Collection<int, LessonProgress> $lessonProgress
 *
 * @method static Builder|Enrollment active()
 * @method static Builder|Enrollment completed()
 * @method static Builder|Enrollment dropped()
 * @method static Builder|Enrollment forUser(User $user)
 * @method static Builder|Enrollment forCourse(Course $course)
 */
class Enrollment extends Model
{
    /** @use HasFactory<\Database\Factories\EnrollmentFactory> */
    use HasFactory, HasStates;

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
            'status' => EnrollmentState::class,
            'enrolled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // State Behavior Methods
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Drop this enrollment.
     *
     * @throws InvalidStateTransitionException if not currently active
     */
    public function drop(?string $reason = null): self
    {
        if (! $this->isActive()) {
            throw new InvalidStateTransitionException(
                from: (string) $this->status,
                to: DroppedState::$name,
                modelType: 'Enrollment',
                modelId: $this->id,
                reason: 'Only active enrollments can be dropped'
            );
        }

        DB::transaction(function () use ($reason) {
            $this->update(['status' => DroppedState::$name]);
            UserDropped::dispatch($this, $reason);
        });

        return $this;
    }

    /**
     * Complete this enrollment.
     * Idempotent - calling on already completed enrollment is a no-op.
     */
    public function complete(): self
    {
        if ($this->isCompleted()) {
            return $this; // Idempotent
        }

        DB::transaction(function () {
            $this->update([
                'status' => CompletedState::$name,
                'completed_at' => now(),
            ]);
            EnrollmentCompleted::dispatch($this);
        });

        return $this;
    }

    /**
     * Reactivate a dropped enrollment.
     *
     * @param  bool  $preserveProgress  Whether to keep previous progress (default: true)
     * @param  int|null  $invitedBy  Optional new inviter (e.g., re-invited by different trainer)
     *
     * @throws InvalidStateTransitionException if not currently dropped
     */
    public function reactivate(bool $preserveProgress = true, ?int $invitedBy = null): self
    {
        if (! $this->isDropped()) {
            throw new InvalidStateTransitionException(
                from: (string) $this->status,
                to: ActiveState::$name,
                modelType: 'Enrollment',
                modelId: $this->id,
                reason: 'Only dropped enrollments can be reactivated'
            );
        }

        DB::transaction(function () use ($preserveProgress, $invitedBy) {
            $updateData = [
                'status' => ActiveState::$name,
                'enrolled_at' => now(),
                'completed_at' => null,
            ];

            if (! $preserveProgress) {
                $updateData['progress_percentage'] = 0;
                $updateData['started_at'] = null;
                $updateData['last_lesson_id'] = null;
            }

            if ($invitedBy) {
                $updateData['invited_by'] = $invitedBy;
            }

            $this->update($updateData);
            UserReenrolled::dispatch($this, $preserveProgress);
        });

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // State Query Methods
    // ─────────────────────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status instanceof ActiveState;
    }

    public function isCompleted(): bool
    {
        return $this->status instanceof CompletedState;
    }

    public function isDropped(): bool
    {
        return $this->status instanceof DroppedState;
    }

    public function canAccessContent(): bool
    {
        return $this->status->canAccessContent();
    }

    public function canTrackProgress(): bool
    {
        return $this->status->canTrackProgress();
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
        return $this->isCompleted();
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->isActive();
    }

    public function scopeForUserAndCourse(Builder $query, User $user, Course $course): Builder
    {
        return $query->where('user_id', $user->id)
            ->where('course_id', $course->id);
    }

    public static function getActiveForUserAndCourse(User $user, Course $course): ?self
    {
        return self::query()
            ->forUserAndCourse($user, $course)
            ->active()
            ->first();
    }
}
