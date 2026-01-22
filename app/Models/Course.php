<?php

namespace App\Models;

use App\Domain\Course\States\ArchivedState;
use App\Domain\Course\States\CourseState;
use App\Domain\Course\States\DraftState;
use App\Domain\Course\States\PublishedState;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\ModelStates\HasStates;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $slug
 * @property string|null $short_description
 * @property string|null $long_description
 * @property array|null $objectives
 * @property array|null $prerequisites
 * @property int|null $category_id
 * @property string|null $thumbnail_path
 * @property CourseState $status
 * @property string $visibility
 * @property string|null $difficulty_level
 * @property int|null $estimated_duration_minutes
 * @property int|null $manual_duration_minutes
 * @property Carbon|null $published_at
 * @property int|null $published_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read User|null $publishedByUser
 * @property-read Category|null $category
 * @property-read Collection<int, CourseSection> $sections
 * @property-read Collection<int, Lesson> $lessons
 * @property-read Collection<int, Enrollment> $enrollments
 * @property-read Collection<int, Assessment> $assessments
 * @property-read Collection<int, LearningPath> $learningPaths
 * @property-read Collection<int, CourseInvitation> $invitations
 * @property-read Collection<int, CourseRating> $ratings
 * @property-read \Illuminate\Database\Eloquent\Relations\Pivot|null $pivot
 *
 * @method static Builder|Course published()
 * @method static Builder|Course draft()
 * @method static Builder|Course archived()
 * @method static Builder|Course visible()
 * @method static Builder|Course public()
 */
class Course extends Model
{
    use Concerns\RequiresEagerLoading;
    use HasFactory, HasStates, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'short_description',
        'long_description',
        'objectives',
        'prerequisites',
        'category_id',
        'thumbnail_path',
        'status',
        'visibility',
        'difficulty_level',
        'estimated_duration_minutes',
        'manual_duration_minutes',
        'published_at',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CourseState::class,
            'objectives' => 'array',
            'prerequisites' => 'array',
            'published_at' => 'datetime',
        ];
    }

    // State helper methods

    public function isDraft(): bool
    {
        return $this->status instanceof DraftState;
    }

    public function isPublished(): bool
    {
        return $this->status instanceof PublishedState;
    }

    public function isArchived(): bool
    {
        return $this->status instanceof ArchivedState;
    }

    public function canBeEdited(): bool
    {
        return $this->status->canEdit();
    }

    public function canAcceptEnrollments(): bool
    {
        return $this->status->canEnroll();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class)->orderBy('order');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, CourseSection::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function enrolledUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments')
            ->withPivot(['status', 'progress_percentage', 'enrolled_at', 'started_at', 'completed_at'])
            ->withTimestamps();
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(CourseRating::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CourseInvitation::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
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

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function getDurationAttribute(): int
    {
        return $this->manual_duration_minutes ?? $this->estimated_duration_minutes ?? 0;
    }

    /**
     * Get total lessons count.
     *
     * Requires: ->withCount('lessons') in your query.
     * Throws in dev/testing if not eager loaded.
     */
    public function getTotalLessonsAttribute(): int
    {
        return $this->getEagerCount('lessons');
    }

    public function getIsEditableAttribute(): bool
    {
        return $this->canBeEdited();
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail_path) {
            return null;
        }

        return Storage::disk('public')->url($this->thumbnail_path);
    }

    /**
     * Get average rating.
     *
     * Requires: ->withAvg('ratings', 'rating') in your query.
     * Throws in dev/testing if not eager loaded.
     */
    public function getAverageRatingAttribute(): ?float
    {
        $avg = $this->getEagerAvg('ratings', 'rating');

        return $avg !== null ? round($avg, 1) : null;
    }

    /**
     * Get ratings count.
     *
     * Requires: ->withCount('ratings') in your query.
     * Throws in dev/testing if not eager loaded.
     */
    public function getRatingsCountAttribute(): int
    {
        return $this->getEagerCount('ratings');
    }

    /**
     * Calculate total estimated duration from all lessons.
     *
     * Uses single SQL query instead of N+1 nested loops.
     */
    public function calculateEstimatedDuration(): int
    {
        return (int) DB::table('lessons')
            ->join('course_sections', 'lessons.course_section_id', '=', 'course_sections.id')
            ->where('course_sections.course_id', $this->id)
            ->whereNull('lessons.deleted_at')
            ->whereNull('course_sections.deleted_at')
            ->sum('lessons.estimated_duration_minutes');
    }

    public function updateEstimatedDuration(): void
    {
        $this->update([
            'estimated_duration_minutes' => $this->calculateEstimatedDuration(),
        ]);
    }
}
