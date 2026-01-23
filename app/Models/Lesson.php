<?php

namespace App\Models;

use App\Services\TipTapRenderer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $course_section_id
 * @property string $title
 * @property string|null $description
 * @property int $order
 * @property string $content_type
 * @property array|null $rich_content
 * @property string|null $youtube_url
 * @property string|null $conference_url
 * @property string|null $conference_type
 * @property int|null $estimated_duration_minutes
 * @property bool $is_free_preview
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $youtube_video_id
 * @property-read string|null $rich_content_html
 * @property-read CourseSection $section
 * @property-read Course|null $course
 * @property-read Collection<int, LessonProgress> $progress
 * @property-read Collection<int, Media> $media
 */
class Lesson extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_section_id',
        'title',
        'description',
        'order',
        'content_type',
        'rich_content',
        'youtube_url',
        'conference_url',
        'conference_type',
        'estimated_duration_minutes',
        'is_free_preview',
    ];

    protected $appends = ['youtube_video_id', 'rich_content_html'];

    protected function casts(): array
    {
        return [
            'rich_content' => 'array',
            'is_free_preview' => 'boolean',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    public function course(): HasOneThrough
    {
        return $this->hasOneThrough(
            Course::class,
            CourseSection::class,
            'id',
            'id',
            'course_section_id',
            'course_id'
        );
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function getYoutubeVideoIdAttribute(): ?string
    {
        if (! $this->youtube_url) {
            return null;
        }

        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $this->youtube_url, $matches);

        return $matches[1] ?? null;
    }

    public function getHasVideoAttribute(): bool
    {
        return in_array($this->content_type, ['video', 'youtube']);
    }

    public function getHasAudioAttribute(): bool
    {
        return $this->content_type === 'audio';
    }

    public function getHasDocumentAttribute(): bool
    {
        return $this->content_type === 'document';
    }

    public function getHasConferenceAttribute(): bool
    {
        return $this->content_type === 'conference';
    }

    /**
     * Get rich content as HTML.
     */
    protected function richContentHtml(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->rich_content) {
                    return null;
                }

                $renderer = new TipTapRenderer;

                return $renderer->render($this->rich_content);
            }
        );
    }

    public static function bulkUpdateOrder(CourseSection $section, array $lessonIds): void
    {
        foreach ($lessonIds as $order => $id) {
            self::where('id', $id)->where('course_section_id', $section->id)
                ->update(['order' => $order + 1]);
        }
    }

    protected static function boot(): void
    {
        parent::boot();

        static::deleted(function (self $lesson): void {
            $section = $lesson->section;
            if ($section) {
                $course = $section->course;
                $lessonId = $lesson->id;
                $lessonTitle = $lesson->title;

                $section->lessons()
                    ->where('order', '>', $lesson->order)
                    ->decrement('order');

                $section->updateEstimatedDuration();
                if ($course instanceof Course) {
                    $course->updateEstimatedDuration();

                    \App\Domain\Progress\Events\LessonDeleted::dispatch(
                        $lessonId,
                        $course,
                        $lessonTitle,
                        auth()->id()
                    );
                }
            }
        });
    }

    /**
     * Update estimated durations for section and course.
     */
    public function updateDurations(): void
    {
        $this->section->updateEstimatedDuration();
        $this->section->course->updateEstimatedDuration();
    }
}
