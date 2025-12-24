<?php
namespace App\Policies;

use App\Models\LearningPath;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LearningPathPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_learning_paths');
    }

    public function view(User $user, LearningPath $learningPath): bool
    {
        // Admins can view any learning path
        if ($user->role === 'lms_admin') {
            return true;
        }

        // Content managers can view learning paths they created
        if ($user->role === 'content_manager') {
            return $user->id === $learningPath->created_by;
        }

        // Learners can view published learning paths
        if ($user->role === 'learner') {
            return $learningPath->is_published;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === 'lms_admin' || $user->role === 'content_manager';
    }

    public function update(User $user, LearningPath $learningPath): bool
    {
        // Admins can update any learning path
        if ($user->role === 'lms_admin') {
            return true;
        }

        // Content managers can update learning paths they created
        if ($user->role === 'content_manager') {
            return $user->id === $learningPath->created_by;
        }

        return false;
    }

    public function delete(User $user, LearningPath $learningPath): bool
    {
        // Admins can delete any learning path
        if ($user->role === 'lms_admin') {
            return true;
        }

        // Content managers can delete learning paths they created
        if ($user->role === 'content_manager') {
            return $user->id === $learningPath->created_by;
        }

        return false;
    }

    public function publish(User $user, LearningPath $learningPath): bool
    {
        // Admins can publish any learning path
        if ($user->role === 'lms_admin') {
            return true;
        }

        // Content managers can publish learning paths they created
        if ($user->role === 'content_manager') {
            return $user->id === $learningPath->created_by;
        }

        return false;
    }

    public function reorder(User $user, LearningPath $learningPath): bool
    {
        return $this->update($user, $learningPath);
    }
}