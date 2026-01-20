# Understanding User Roles

Enteraksi uses role-based access control (RBAC) with four distinct roles. Each role has specific permissions and capabilities.

## Role Hierarchy

```
┌─────────────────────────────────────────────────────────────┐
│                        LMS Admin                             │
│  Full system access, publish courses, manage all users       │
├─────────────────────────────────────────────────────────────┤
│                         Trainer                              │
│  Create courses, invite learners, grade assessments          │
├─────────────────────────────────────────────────────────────┤
│                     Content Manager                          │
│  Create and manage own courses                               │
├─────────────────────────────────────────────────────────────┤
│                         Learner                              │
│  Enroll in courses, take assessments, track progress         │
└─────────────────────────────────────────────────────────────┘
```

## Role Comparison

| Capability | Learner | Content Manager | Trainer | LMS Admin |
|------------|---------|-----------------|---------|-----------|
| Enroll in courses | Yes | Yes | Yes | Yes |
| View lessons | Yes | Yes | Yes | Yes |
| Take assessments | Yes | Yes | Yes | Yes |
| Rate courses | Yes | Yes | Yes | Yes |
| Create courses | No | Yes | Yes | Yes |
| Edit own courses | No | Yes | Yes | Yes |
| Edit any course | No | No | No | Yes |
| Publish courses | No | No | No | Yes |
| Archive courses | No | No | No | Yes |
| Invite learners | No | No | Yes | Yes |
| Grade assessments | No | Own courses | Own courses | Any |
| Manage users | No | No | No | Yes |

## Detailed Role Descriptions

### Learner (Default Role)

The default role assigned to new users upon registration.

**Can do:**
- Browse published public courses
- Enroll in public courses
- Accept course invitations for restricted courses
- View lessons in enrolled courses
- Track progress through courses
- Take assessments and quizzes
- View assessment results
- Rate and review enrolled courses

**Cannot do:**
- Create or edit courses
- Invite other users
- Access admin dashboard
- Publish or archive content

**Best for:** Students, employees taking training, anyone consuming learning content.

---

### Content Manager

For users who create learning content but don't need full administrative access.

**Can do:**
- Everything a Learner can do
- Create new courses
- Edit their own courses (draft and archived only)
- Add sections and lessons to own courses
- Upload media (videos, documents, audio)
- Create assessments for own courses
- View assessment attempts on own courses
- Grade assessments on own courses

**Cannot do:**
- Publish courses (requires LMS Admin)
- Edit published courses
- Invite learners directly
- Access other users' courses
- Archive courses

**Best for:** Subject matter experts, content creators, instructional designers.

---

### Trainer

Extended permissions for managing learner enrollment and engagement.

**Can do:**
- Everything a Content Manager can do
- Invite learners to courses (single and bulk)
- View all course invitations
- Cancel pending invitations
- Grade assessments on any course they have access to

**Cannot do:**
- Publish or archive courses
- Edit other users' courses
- Administrative functions

**Best for:** Instructors, facilitators, team leads managing training.

---

### LMS Admin

Full system administrator with unrestricted access.

**Can do:**
- Everything all other roles can do
- Publish courses (make available to learners)
- Unpublish courses (remove from catalog)
- Archive courses (soft-remove)
- Edit any course (including published)
- Delete any course
- Grade any assessment
- Manage all invitations
- Access all system functions

**Best for:** System administrators, training managers, platform owners.

---

## Role Assignment

### During Registration

New users are automatically assigned the `learner` role:

```php
// app/Actions/Fortify/CreateNewUser.php
User::create([
    'name' => $input['name'],
    'email' => $input['email'],
    'password' => Hash::make($input['password']),
    'role' => 'learner',  // Default role
]);
```

### Changing Roles

Currently, roles can only be changed directly in the database or via Tinker:

```bash
php artisan tinker
```

```php
$user = User::where('email', 'user@example.com')->first();
$user->role = 'trainer';
$user->save();
```

> **Note**: A user management UI for role assignment is planned for future development.

---

## Role Checks in Code

### In Controllers

```php
// Check if user can manage courses
if ($user->canManageCourses()) {
    // Content Manager, Trainer, or Admin
}

// Check specific role
if ($user->isLmsAdmin()) {
    // Admin only
}
```

### In Policies

```php
// CoursePolicy.php
public function publish(User $user, Course $course): bool
{
    return $user->isLmsAdmin();
}

public function update(User $user, Course $course): bool
{
    if ($user->isLmsAdmin()) {
        return true;
    }

    return $user->canManageCourses()
        && $course->user_id === $user->id
        && $course->status !== 'published';
}
```

### In Blade/Vue Templates

```vue
<template>
  <!-- Show only to admins -->
  <button v-if="$page.props.auth.user.role === 'lms_admin'">
    Publish Course
  </button>

  <!-- Show to anyone who can manage courses -->
  <button v-if="canManageCourses">
    Edit Course
  </button>
</template>

<script setup>
const canManageCourses = computed(() => {
  const role = usePage().props.auth.user?.role;
  return ['content_manager', 'trainer', 'lms_admin'].includes(role);
});
</script>
```

---

## Helper Methods Reference

Available on the `User` model:

| Method | Returns `true` for |
|--------|-------------------|
| `isLearner()` | learner |
| `isContentManager()` | content_manager |
| `isTrainer()` | trainer |
| `isLmsAdmin()` | lms_admin |
| `canManageCourses()` | content_manager, trainer, lms_admin |

---

## Common Scenarios

### "User can't see their created course"
- Check if user has `content_manager`, `trainer`, or `lms_admin` role
- Learners cannot create courses

### "User can't publish their course"
- Only `lms_admin` can publish
- Content Managers and Trainers must request publication from an admin

### "User can't edit published course"
- Published courses are locked to prevent accidental changes
- Only `lms_admin` can edit published courses

### "User can't invite learners"
- Only `trainer` and `lms_admin` can send invitations
- Content Managers cannot invite directly

---

## Next Steps

- [Your First Course](./first-course.md) - Create content as Content Manager
- [Course Management Guide](../guides/course-management.md) - Detailed course operations
- [Security Architecture](../architecture/security.md) - How authorization works
