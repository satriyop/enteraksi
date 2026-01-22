<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property int $invited_by
 * @property string $status
 * @property string|null $message
 * @property Carbon|null $expires_at
 * @property Carbon|null $responded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read bool $is_expired
 * @property-read User $user
 * @property-read Course $course
 * @property-read User $inviter
 *
 * @method static Builder|CourseInvitation pending()
 * @method static Builder|CourseInvitation forUser(User $user)
 * @method static Builder|CourseInvitation notExpired()
 */
class CourseInvitation extends Model
{
    /** @use HasFactory<\Database\Factories\CourseInvitationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'invited_by',
        'status',
        'message',
        'expires_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending' && ! $this->is_expired;
    }
}
