<?php

namespace App\Models;

use App\Domain\Course\States\ArchivedState;
use App\Domain\Course\States\CourseState;
use App\Domain\Course\States\DraftState;
use App\Domain\Course\States\PublishedState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\ModelStates\HasStates;

class Course extends Model
{
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

    public function getTotalLessonsAttribute(): int
    {
        return $this->lessons()->count();
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

    public function getAverageRatingAttribute(): ?float
    {
        $avg = $this->ratings()->avg('rating');

        return $avg !== null ? round($avg, 1) : null;
    }

    public function getRatingsCountAttribute(): int
    {
        return $this->ratings()->count();
    }

    public function calculateEstimatedDuration(): int
    {
        $totalMinutes = 0;

        foreach ($this->sections as $section) {
            foreach ($section->lessons as $lesson) {
                $totalMinutes += $lesson->estimated_duration_minutes ?? 0;
            }
        }

        return $totalMinutes;
    }

    public function updateEstimatedDuration(): void
    {
        $this->update([
            'estimated_duration_minutes' => $this->calculateEstimatedDuration(),
        ]);
    }
}
