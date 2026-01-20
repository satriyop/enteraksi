<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class LearningPathCourse extends Pivot
{
    protected $table = 'learning_path_course';

    public $incrementing = true;

    protected $fillable = [
        'learning_path_id',
        'course_id',
        'position',
        'is_required',
        'prerequisites',
        'min_completion_percentage',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_required' => 'boolean',
            'prerequisites' => 'array',
            'min_completion_percentage' => 'integer',
        ];
    }

    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
