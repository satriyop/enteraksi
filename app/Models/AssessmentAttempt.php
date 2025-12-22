<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentAttempt extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'assessment_id',
        'user_id',
        'attempt_number',
        'status',
        'score',
        'max_score',
        'percentage',
        'passed',
        'started_at',
        'submitted_at',
        'graded_at',
        'graded_by',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'started_at'   => 'datetime',
            'submitted_at' => 'datetime',
            'graded_at'    => 'datetime',
            'passed'       => 'boolean',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isGraded(): bool
    {
        return $this->status === 'graded';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function requiresGrading(): bool
    {
        return $this->isSubmitted() && ! $this->isGraded();
    }

    public function calculateScore(): void
    {
        $totalScore = $this->answers()->sum('score');
        $maxScore   = $this->assessment->total_points;
        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;
        $passed     = $percentage >= $this->assessment->passing_score;

        $this->update([
            'score'      => $totalScore,
            'max_score'  => $maxScore,
            'percentage' => $percentage,
            'passed'     => $passed,
            'status'     => 'graded',
            'graded_at'  => now(),
        ]);
    }

    public function completeAttempt(): void
    {
        $this->update([
            'status' => 'completed',
        ]);
    }
}