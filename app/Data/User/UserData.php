<?php

namespace App\Data\User;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $role,
        public ?string $avatar,
        public ?string $bio,
        public ?string $created_at,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $user->role,
            avatar: $user->avatar,
            bio: $user->bio,
            created_at: $user->created_at?->toIso8601String(),
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === 'lms_admin';
    }

    public function isContentManager(): bool
    {
        return $this->role === 'content_manager';
    }

    public function isLearner(): bool
    {
        return $this->role === 'learner';
    }
}
