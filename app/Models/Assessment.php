<?php

namespace App\Models;

use App\Domain\Assessment\Exceptions\MaxAttemptsReachedException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Assessment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_id',
        'user_id',
        'title',
        'slug',
        'description',
        'instructions',
        'time_limit_minutes',
        'passing_score',
        'max_attempts',
        'shuffle_questions',
        'show_correct_answers',
        'allow_review',
        'is_required',
        'status',
        'visibility',
        'published_at',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'shuffle_questions' => 'boolean',
            'show_correct_answers' => 'boolean',
            'allow_review' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', 'archived');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visibility', 'public');
    }

    /**
     * Get total questions count.
     * Uses pre-loaded questions_count if available (via withCount()),
     * otherwise falls back to query.
     */
    public function getTotalQuestionsAttribute(): int
    {
        if (array_key_exists('questions_count', $this->attributes)) {
            return (int) $this->attributes['questions_count'];
        }

        return $this->questions()->count();
    }

    /**
     * Get total points.
     * Uses pre-loaded questions_sum_points if available (via withSum()),
     * otherwise falls back to query.
     */
    public function getTotalPointsAttribute(): int
    {
        if (array_key_exists('questions_sum_points', $this->attributes)) {
            return (int) ($this->attributes['questions_sum_points'] ?? 0);
        }

        return (int) $this->questions()->sum('points');
    }

    public function getIsEditableAttribute(): bool
    {
        return $this->status !== 'published';
    }

    public function generateSlug(): string
    {
        return Str::slug($this->title).'-'.Str::random(6);
    }

    public function canBeAttemptedBy(User $user): bool
    {
        // Check if assessment is published
        if ($this->status !== 'published') {
            return false;
        }

        // Check if user is enrolled in the course and has content access
        $enrollment = $user->enrollments()->where('course_id', $this->course_id)->first();
        if (! $enrollment || ! $enrollment->canAccessContent()) {
            return false;
        }

        // Check attempt limits
        $completedAttempts = $this->attempts()->where('user_id', $user->id)
            ->whereIn('status', ['submitted', 'graded', 'completed'])->count();

        if ($this->max_attempts > 0 && $completedAttempts >= $this->max_attempts) {
            return false;
        }

        return true;
    }

    /**
     * Determine if any question in this assessment requires manual grading.
     */
    public function requiresManualGrading(): bool
    {
        return $this->questions()
            ->whereIn('question_type', ['essay', 'file_upload'])
            ->exists();
    }

    /**
     * Validate that user can attempt this assessment.
     * Throws specific exceptions for each failure reason.
     *
     * @throws MaxAttemptsReachedException
     */
    public function validateAttemptOrFail(User $user): void
    {
        if ($this->max_attempts > 0) {
            $completedAttempts = $this->attempts()
                ->where('user_id', $user->id)
                ->whereIn('status', ['submitted', 'graded', 'completed'])
                ->count();

            if ($completedAttempts >= $this->max_attempts) {
                throw new MaxAttemptsReachedException(
                    userId: $user->id,
                    assessmentId: $this->id,
                    maxAttempts: $this->max_attempts,
                    completedAttempts: $completedAttempts
                );
            }
        }
    }

    /**
     * Get the number of completed attempts for a user.
     */
    public function getCompletedAttemptsCount(User $user): int
    {
        return $this->attempts()
            ->where('user_id', $user->id)
            ->whereIn('status', ['submitted', 'graded', 'completed'])
            ->count();
    }
}
