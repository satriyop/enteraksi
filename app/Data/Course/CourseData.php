<?php

namespace App\Data\Course;

use App\Models\Course;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class CourseData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $slug,
        public ?string $short_description,
        public ?string $long_description,
        public string $status,
        public string $visibility,
        public ?string $difficulty_level,
        public ?int $estimated_duration_minutes,
        public ?string $thumbnail_path,
        public int $user_id,
        public ?int $category_id,
        public ?string $created_at,
        public ?string $updated_at,
        public ?string $published_at,
        // Eager loaded counts
        public Lazy|int|null $lessons_count = null,
        public Lazy|int|null $sections_count = null,
        public Lazy|int|null $enrollments_count = null,
        public Lazy|float|null $average_rating = null,
        public Lazy|int|null $ratings_count = null,
    ) {}

    public static function fromModel(Course $course): self
    {
        return new self(
            id: $course->id,
            title: $course->title,
            slug: $course->slug,
            short_description: $course->short_description,
            long_description: $course->long_description,
            status: (string) $course->status,
            visibility: $course->visibility,
            difficulty_level: $course->difficulty_level,
            estimated_duration_minutes: $course->estimated_duration_minutes,
            thumbnail_path: $course->thumbnail_path,
            user_id: $course->user_id,
            category_id: $course->category_id,
            created_at: $course->created_at?->toIso8601String(),
            updated_at: $course->updated_at?->toIso8601String(),
            published_at: $course->published_at?->toIso8601String(),
            lessons_count: Lazy::create(fn () => $course->lessons_count ?? $course->lessons()->count()),
            sections_count: Lazy::create(fn () => $course->sections_count ?? $course->sections()->count()),
            enrollments_count: Lazy::create(fn () => $course->enrollments_count ?? $course->enrollments()->count()),
            average_rating: Lazy::create(fn () => $course->ratings_avg_rating ?? $course->ratings()->avg('rating')),
            ratings_count: Lazy::create(fn () => $course->ratings_count ?? $course->ratings()->count()),
        );
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }
}
