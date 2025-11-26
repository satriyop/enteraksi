<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Lesson extends Model
{
    use HasFactory;

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

    protected $appends = ['youtube_video_id'];

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
}
