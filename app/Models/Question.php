<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'assessment_id',
        'question_text',
        'question_type',
        'points',
        'feedback',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'question_type' => 'string',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('order');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    public function getQuestionTypeLabel(): string
    {
        return match ($this->question_type) {
            'multiple_choice' => 'Pilihan Ganda',
            'true_false'      => 'Benar/Salah',
            'matching'        => 'Pencocokan',
            'short_answer'    => 'Jawaban Singkat',
            'essay'           => 'Esai',
            'file_upload'     => 'Unggah Berkas',
            default           => 'Tidak Diketahui',
        };
    }

    public function getCorrectOptions(): HasMany
    {
        return $this->options()->where('is_correct', true);
    }

    public function isMultipleChoice(): bool
    {
        return $this->question_type === 'multiple_choice';
    }

    public function isTrueFalse(): bool
    {
        return $this->question_type === 'true_false';
    }

    public function isMatching(): bool
    {
        return $this->question_type === 'matching';
    }

    public function isShortAnswer(): bool
    {
        return $this->question_type === 'short_answer';
    }

    public function isEssay(): bool
    {
        return $this->question_type === 'essay';
    }

    public function isFileUpload(): bool
    {
        return $this->question_type === 'file_upload';
    }

    public function requiresManualGrading(): bool
    {
        return $this->isEssay() || $this->isFileUpload();
    }
}