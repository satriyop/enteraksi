<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property array|null $objectives
 * @property string|null $slug
 * @property int $created_by
 * @property int|null $updated_by
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property int|null $estimated_duration
 * @property string|null $difficulty_level
 * @property string|null $thumbnail_url
 * @property string $prerequisite_mode
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $creator
 * @property-read User|null $updater
 * @property-read Collection<int, Course> $courses
 * @property-read Collection<int, LearningPathEnrollment> $learnerEnrollments
 *
 * @method static Builder|LearningPath published()
 */
class LearningPath extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'objectives',
        'slug',
        'created_by',
        'updated_by',
        'is_published',
        'published_at',
        'estimated_duration',
        'difficulty_level',
        'thumbnail_url',
        'prerequisite_mode',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'objectives' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'learning_path_course')
            ->using(LearningPathCourse::class)
            ->withPivot([
                'position',
                'is_required',
                'prerequisites',
                'min_completion_percentage',
            ])
            ->withTimestamps()
            ->orderBy('position');
    }

    public function enrollments()
    {
        return $this->hasManyThrough(Enrollment::class, Course::class);
    }

    public function learnerEnrollments()
    {
        return $this->hasMany(LearningPathEnrollment::class);
    }

    public function isPublished(): bool
    {
        return $this->is_published;
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($learningPath) {
            if (empty($learningPath->slug)) {
                $learningPath->slug = \Str::slug($learningPath->title);
            }
        });
    }
}
