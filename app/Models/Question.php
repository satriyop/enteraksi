<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $assessment_id
 * @property string $question_text
 * @property string $question_type
 * @property int $points
 * @property string|null $feedback
 * @property int $order
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read Assessment $assessment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, QuestionOption> $options
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AttemptAnswer> $answers
 */
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
            'true_false' => 'Benar/Salah',
            'matching' => 'Pencocokan',
            'short_answer' => 'Jawaban Singkat',
            'essay' => 'Esai',
            'file_upload' => 'Unggah Berkas',
            default => 'Tidak Diketahui',
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

    public function extractAnswerValue(array $answerData): mixed
    {
        if ($this->isMultipleChoice()) {
            return $answerData['selected_options'] ?? [];
        }

        return $answerData['answer_text'] ?? '';
    }

    public function formatAnswerForStorage(array $answerData): ?string
    {
        if ($this->isMultipleChoice() && ! empty($answerData['selected_options'])) {
            return json_encode($answerData['selected_options']);
        }

        return $answerData['answer_text'] ?? null;
    }

    public function syncOptions(array $optionsData): void
    {
        $existingOptionIds = $this->options()->pluck('id')->toArray();
        $submittedOptionIds = [];

        foreach ($optionsData as $optionData) {
            if (isset($optionData['id']) && $optionData['id'] > 0) {
                $option = $this->options()->find($optionData['id']);
                if ($option) {
                    $option->update([
                        'option_text' => $optionData['option_text'],
                        'is_correct' => $optionData['is_correct'] ?? false,
                        'feedback' => $optionData['feedback'] ?? null,
                        'order' => $optionData['order'] ?? 0,
                    ]);
                    $submittedOptionIds[] = $option->id;
                }
            } else {
                $option = $this->options()->create([
                    'option_text' => $optionData['option_text'],
                    'is_correct' => $optionData['is_correct'] ?? false,
                    'feedback' => $optionData['feedback'] ?? null,
                    'order' => $optionData['order'] ?? 0,
                ]);
                $submittedOptionIds[] = $option->id;
            }
        }

        $optionsToDelete = array_diff($existingOptionIds, $submittedOptionIds);
        if (! empty($optionsToDelete)) {
            $this->options()->whereIn('id', $optionsToDelete)->delete();
        }
    }

    public function createOptions(array $optionsData): void
    {
        foreach ($optionsData as $optionData) {
            $this->options()->create([
                'option_text' => $optionData['option_text'],
                'is_correct' => $optionData['is_correct'] ?? false,
                'feedback' => $optionData['feedback'] ?? null,
                'order' => $optionData['order'] ?? 0,
            ]);
        }
    }
}
