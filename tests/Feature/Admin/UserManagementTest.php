<?php

namespace Tests\Feature\Admin;

use App\Models\User;

/**
 * Feature tests for User Management.
 *
 * Tests the full HTTP flow for user CRUD operations.
 * Only lms_admin role can access these endpoints.
 */

// =============================================================================
// Authentication Tests
// =============================================================================

it('redirects guests to login when accessing user list', function () {
    $this->get(route('admin.users.index'))
        ->assertRedirect(route('login'));
});

it('denies learner access to user list', function () {
    asLearner()
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

it('denies content_manager access to user list', function () {
    asContentManager()
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

it('denies trainer access to user list', function () {
    asRole('trainer')
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

// =============================================================================
// Index Tests
// =============================================================================

it('allows lms_admin to view user list', function () {
    asAdmin()
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/Index')
            ->has('users'));
});

it('allows lms_admin to search users by name', function () {
    User::factory()->create(['name' => 'John Doe', 'role' => 'learner']);
    User::factory()->create(['name' => 'Jane Smith', 'role' => 'learner']);

    asAdmin()
        ->get(route('admin.users.index', ['search' => 'John']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/Index')
            ->where('users.data', fn ($users) => collect($users)->contains('name', 'John Doe'))
            ->where('users.data', fn ($users) => ! collect($users)->contains('name', 'Jane Smith')));
});

it('allows lms_admin to search users by email', function () {
    User::factory()->create(['name' => 'Test User', 'email' => 'unique@example.com', 'role' => 'learner']);
    User::factory()->create(['name' => 'Other User', 'email' => 'other@example.com', 'role' => 'learner']);

    asAdmin()
        ->get(route('admin.users.index', ['search' => 'unique@example']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($users) => collect($users)->contains('email', 'unique@example.com'))
            ->where('users.data', fn ($users) => ! collect($users)->contains('email', 'other@example.com')));
});

it('allows lms_admin to filter users by role', function () {
    User::factory()->create(['role' => 'learner']);
    User::factory()->create(['role' => 'content_manager']);

    asAdmin()
        ->get(route('admin.users.index', ['role' => 'learner']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($users) => collect($users)->every(fn ($user) => $user['role'] === 'learner')));
});

// =============================================================================
// Create Tests
// =============================================================================

it('allows lms_admin to view create user form', function () {
    asAdmin()
        ->get(route('admin.users.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/users/Create'));
});

it('denies learner to view create user form', function () {
    asLearner()
        ->get(route('admin.users.create'))
        ->assertForbidden();
});

// =============================================================================
// Store Tests
// =============================================================================

it('allows lms_admin to create a new user', function () {
    asAdmin()
        ->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'learner',
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('users', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'role' => 'learner',
    ]);
});

it('validates unique email when creating user', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    asAdmin()
        ->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'learner',
        ])
        ->assertSessionHasErrors('email');
});

it('validates password confirmation when creating user', function () {
    asAdmin()
        ->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
            'role' => 'learner',
        ])
        ->assertSessionHasErrors('password');
});

it('validates valid role when creating user', function () {
    asAdmin()
        ->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'invalid_role',
        ])
        ->assertSessionHasErrors('role');
});

it('sets email_verified_at when creating user', function () {
    asAdmin()
        ->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'learner',
        ]);

    $user = User::where('email', 'newuser@example.com')->first();
    expect($user->email_verified_at)->not->toBeNull();
});

// =============================================================================
// Edit Tests
// =============================================================================

it('allows lms_admin to view edit user form', function () {
    $user = User::factory()->create(['role' => 'learner']);

    asAdmin()
        ->get(route('admin.users.edit', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/Edit')
            ->has('user')
            ->where('canEditRole', true));
});

it('passes canEditRole=false when editing self', function () {
    $admin = User::factory()->create(['role' => 'lms_admin']);

    $this->actingAs($admin)
        ->get(route('admin.users.edit', $admin))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canEditRole', false));
});

it('denies learner to view edit user form', function () {
    $user = User::factory()->create(['role' => 'learner']);

    asLearner()
        ->get(route('admin.users.edit', $user))
        ->assertForbidden();
});

// =============================================================================
// Update Tests
// =============================================================================

it('allows lms_admin to update user', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'role' => 'learner',
    ]);

    asAdmin()
        ->put(route('admin.users.update', $user), [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'role' => 'content_manager',
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $user->refresh();
    expect($user->name)->toBe('New Name');
    expect($user->email)->toBe('new@example.com');
    expect($user->role)->toBe('content_manager');
});

it('allows lms_admin to update user password', function () {
    $user = User::factory()->create(['role' => 'learner']);
    $oldPassword = $user->password;

    asAdmin()
        ->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'role' => $user->role,
        ])
        ->assertRedirect(route('admin.users.index'));

    $user->refresh();
    expect($user->password)->not->toBe($oldPassword);
});

it('keeps password unchanged when not provided', function () {
    $user = User::factory()->create(['role' => 'learner']);
    $oldPassword = $user->password;

    asAdmin()
        ->put(route('admin.users.update', $user), [
            'name' => 'Updated Name',
            'email' => $user->email,
            'role' => $user->role,
        ])
        ->assertRedirect(route('admin.users.index'));

    $user->refresh();
    expect($user->password)->toBe($oldPassword);
    expect($user->name)->toBe('Updated Name');
});

it('prevents lms_admin from changing own role', function () {
    $admin = User::factory()->create(['role' => 'lms_admin']);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'learner',
        ])
        ->assertSessionHasErrors('role');

    $admin->refresh();
    expect($admin->role)->toBe('lms_admin');
});

it('validates unique email when updating user', function () {
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);
    $userToUpdate = User::factory()->create(['email' => 'original@example.com', 'role' => 'learner']);

    asAdmin()
        ->put(route('admin.users.update', $userToUpdate), [
            'name' => $userToUpdate->name,
            'email' => 'existing@example.com',
            'role' => $userToUpdate->role,
        ])
        ->assertSessionHasErrors('email');
});

it('allows updating user to same email', function () {
    $user = User::factory()->create(['email' => 'same@example.com', 'role' => 'learner']);

    asAdmin()
        ->put(route('admin.users.update', $user), [
            'name' => 'Updated Name',
            'email' => 'same@example.com',
            'role' => $user->role,
        ])
        ->assertRedirect(route('admin.users.index'));
});

// =============================================================================
// Delete Tests
// =============================================================================

it('allows lms_admin to delete other user', function () {
    $user = User::factory()->create(['role' => 'learner']);

    asAdmin()
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('prevents lms_admin from deleting self', function () {
    $admin = User::factory()->create(['role' => 'lms_admin']);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin))
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

it('denies learner to delete user', function () {
    $user = User::factory()->create(['role' => 'learner']);

    asLearner()
        ->delete(route('admin.users.destroy', $user))
        ->assertForbidden();
});

// =============================================================================
// Edge Cases
// =============================================================================

it('includes courses_count and enrollments_count in user list', function () {
    $user = User::factory()->create(['role' => 'content_manager']);

    asAdmin()
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($users) => collect($users)->every(fn ($u) => array_key_exists('courses_count', $u) && array_key_exists('enrollments_count', $u))));
});
