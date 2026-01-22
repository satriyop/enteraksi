<?php

namespace App\Policies;

use App\Models\User;

/**
 * User management policy.
 *
 * Only LMS administrators can manage users.
 * Self-delete is prohibited to prevent admin lockout.
 */
class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isLmsAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->isLmsAdmin();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isLmsAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->isLmsAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Admins cannot delete themselves to prevent lockout.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isLmsAdmin() && $user->id !== $model->id;
    }
}
