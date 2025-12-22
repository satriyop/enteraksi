<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttemptAnswer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'attempt_id',
        'question_id',
        'answer_text',
        'file_path',
        'is_correct',
        'score',
        'feedback',
        'graded_by',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'graded_at'  => 'datetime',
            'is_correct' => 'boolean',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function isGraded(): bool
    {
        return $this->graded_at !== null;
    }

    public function isCorrect(): bool
    {
        return $this->is_correct ?? false;
    }

    public function getFileUrl(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return asset('storage/' . $this->file_path);
    }
}