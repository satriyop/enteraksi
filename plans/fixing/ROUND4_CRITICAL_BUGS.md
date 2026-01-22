# Round 4: Critical Bug Fixes

> Breaking bugs that would cause user-facing failures in production.

## Status: ✅ COMPLETED

| Priority | Issue | Status | Severity |
|----------|-------|--------|----------|
| 🔴 P0 | State mutation bug in listener | ✅ Fixed | CRITICAL - Data Corruption |
| 🔴 P1 | Race condition in enrollment | ✅ Fixed | HIGH - Concurrency Bug |
| 🔴 P2 | Race condition in acceptInvitation | ✅ Fixed | HIGH - Same Race Condition |

### Completed: 2026-01-22

All fixes verified with **1362 passing tests**.

---

## 🔴 P0: State Mutation Bug in Listener

### Problem

Direct string assignment to a Spatie state-casted column corrupts the state object.

### File

`app/Domain/LearningPath/Listeners/UpdatePathProgressOnCourseDrop.php:96-97`

### Current Code (BAD)

```php
// If path was completed, revert to active
if ($pathEnrollment->isCompleted()) {
    $pathEnrollment->state = ActivePathState::$name;  // ❌ String assignment to state cast
    $pathEnrollment->completed_at = null;
}

$pathEnrollment->save();
```

### Why It Breaks

1. `state` column is cast to `PathEnrollmentState::class` (Spatie state machine)
2. Assigning `ActivePathState::$name` (a string) bypasses the state casting
3. After `save()`, the model holds a raw string instead of state instance
4. `$pathEnrollment->isActive()` checks `$this->state instanceof ActivePathState` → **FALSE** (it's a string!)
5. `$pathEnrollment->state->canAccessContent()` → **"Call to member function on string"**

### Impact

- **Data Corruption**: Learning path enrollments become "zombies" with invalid state
- **App Crashes**: Any subsequent state checks throw errors
- **Silent Failures**: Listener is queued, errors aren't visible during user flow
- **Cascading Failures**: Other listeners checking state will misbehave

### Proposed Fix

```php
// If path was completed, revert to active
if ($pathEnrollment->isCompleted()) {
    $pathEnrollment->state->transitionTo(ActivePathState::class);
    $pathEnrollment->completed_at = null;
    $pathEnrollment->save();
}
```

Or using update() which properly handles casts:

```php
if ($pathEnrollment->isCompleted()) {
    $pathEnrollment->update([
        'state' => ActivePathState::class,
        'completed_at' => null,
    ]);
}
```

### Also Check

Search for similar patterns in other listeners:
- `UpdatePathProgressOnCourseCompletion.php`
- Any file with `->state = SomeState::$name`

---

## 🔴 P1: Race Condition in Enrollment

### Problem

Concurrent enrollment requests can cause database exceptions and data inconsistency.

### File

`app/Http/Controllers/EnrollmentController.php:25-62`

### Current Code (BAD)

```php
public function store(Request $request, Course $course): RedirectResponse
{
    Gate::authorize('enroll', $course);

    $user = $request->user();

    // ❌ Query OUTSIDE any transaction
    $invitation = $user->courseInvitations()
        ->where('course_id', $course->id)
        ->where('status', 'pending')
        ->first();

    try {
        $dto = new CreateEnrollmentDTO(
            userId: $user->id,
            courseId: $course->id,
            invitedBy: $invitation?->invited_by,
        );

        // ❌ Transaction starts HERE (inside service)
        $this->enrollmentService->enroll($dto);

        // ❌ Invitation update OUTSIDE service transaction
        if ($invitation) {
            $invitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);
        }

        return redirect()...;

    } catch (AlreadyEnrolledException) {
        return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
    }
    // ❌ IntegrityConstraintViolationException NOT caught!
}
```

### Why It Breaks

**Race Condition Timeline:**
```
Request A                          Request B
─────────────────────────────────────────────────────
canEnroll() → true
                                   canEnroll() → true
enroll() starts transaction
                                   enroll() starts transaction
INSERT enrollment ✓
                                   INSERT enrollment → UNIQUE violation!
commit ✓
                                   IntegrityConstraintViolationException!
invitation → accepted
                                   500 Error (not caught)
```

**Problems:**
1. Two requests pass `canEnroll()` check simultaneously
2. Second request hits `UNIQUE (user_id, course_id)` constraint
3. `IntegrityConstraintViolationException` is NOT `AlreadyEnrolledException`
4. User sees 500 error instead of friendly message
5. Invitation can be marked "accepted" even if enrollment failed (separate operation)

### Impact

- **500 Errors**: Users get database exceptions during traffic spikes
- **Data Inconsistency**: Invitation marked "accepted" without actual enrollment
- **Poor UX**: Cryptic errors instead of "Already enrolled" message

### Proposed Fix

```php
public function store(Request $request, Course $course): RedirectResponse
{
    Gate::authorize('enroll', $course);

    $user = $request->user();

    try {
        // Wrap entire flow in transaction with pessimistic locking
        $result = DB::transaction(function () use ($user, $course) {
            // Lock invitation row to prevent concurrent updates
            $invitation = $user->courseInvitations()
                ->where('course_id', $course->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            $dto = new CreateEnrollmentDTO(
                userId: $user->id,
                courseId: $course->id,
                invitedBy: $invitation?->invited_by,
            );

            $result = $this->enrollmentService->enroll($dto);

            // Update invitation inside same transaction
            if ($invitation) {
                $invitation->update([
                    'status' => 'accepted',
                    'responded_at' => now(),
                ]);
            }

            return $result;
        });

        return redirect()
            ->route('courses.show', $course)
            ->with('success', 'Berhasil mendaftar ke kursus.');

    } catch (AlreadyEnrolledException) {
        return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
    } catch (CourseNotPublishedException) {
        return back()->with('error', 'Kursus ini belum dipublikasikan.');
    } catch (QueryException $e) {
        // Catch unique constraint violation as fallback
        if ($e->errorInfo[1] === 1062) { // MySQL duplicate entry
            return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
        }
        throw $e;
    }
}
```

### Also Check

- `acceptInvitation()` method has similar pattern
- `LearningPathEnrollmentController` - verify it has proper locking

---

## 🔴 P2: Race Condition in acceptInvitation (Same Pattern as P1)

### Problem

The `acceptInvitation()` method has the exact same race condition and transaction boundary issues as `store()`.

### File

`app/Http/Controllers/EnrollmentController.php:127-172`

### Current Code (BAD)

```php
public function acceptInvitation(Request $request, CourseInvitation $invitation): RedirectResponse
{
    $user = $request->user();

    // ❌ Status check OUTSIDE transaction - can become stale
    if ($invitation->status !== 'pending') {
        return back()->with('error', 'Undangan ini sudah tidak berlaku.');
    }

    // ❌ Expiry check OUTSIDE transaction
    if ($invitation->is_expired) {
        $invitation->update(['status' => 'expired']);  // ❌ Separate transaction!
        return back()->with('error', 'Undangan ini sudah kadaluarsa.');
    }

    try {
        $dto = new CreateEnrollmentDTO(...);

        $this->enrollmentService->enroll($dto);  // ❌ Transaction inside service

        // ❌ Invitation update OUTSIDE service transaction
        $invitation->update([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        return redirect()...;

    } catch (AlreadyEnrolledException) {
        return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
    }
    // ❌ QueryException (duplicate key) NOT caught!
}
```

### Why It Breaks

**Race Condition Timeline:**
```
Request A                              Request B
──────────────────────────────────────────────────────────
invitation->status === 'pending' ✓
                                       invitation->status === 'pending' ✓
enrollmentService->enroll() ✓
                                       enrollmentService->enroll() → UNIQUE violation!
invitation->update(['accepted'])
                                       500 Error thrown
```

**Additional Issues:**
1. If `enroll()` succeeds but the later `invitation->update()` fails, we have orphaned enrollment
2. The invitation status check is not locked - two requests can pass it simultaneously
3. No protection against double-click or network retries

### Impact

- Same as P1: 500 errors during concurrent requests
- Invitation can remain "pending" while user is actually enrolled
- Double invitations can create duplicate error conditions

### Proposed Fix

```php
public function acceptInvitation(Request $request, CourseInvitation $invitation): RedirectResponse
{
    $user = $request->user();

    // Verify ownership first (doesn't need transaction)
    if ($invitation->user_id !== $user->id) {
        abort(403);
    }

    try {
        DB::transaction(function () use ($user, $invitation) {
            // Lock and re-check invitation status inside transaction
            $lockedInvitation = CourseInvitation::lockForUpdate()
                ->findOrFail($invitation->id);

            if ($lockedInvitation->status !== 'pending') {
                throw new InvitationNotPendingException();
            }

            if ($lockedInvitation->is_expired) {
                $lockedInvitation->update(['status' => 'expired']);
                throw new InvitationExpiredException();
            }

            $dto = new CreateEnrollmentDTO(
                userId: $user->id,
                courseId: $lockedInvitation->course_id,
                invitedBy: $lockedInvitation->invited_by,
            );

            $this->enrollmentService->enroll($dto);

            // Update invitation inside same transaction
            $lockedInvitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);
        });

        return redirect()
            ->route('courses.show', $invitation->course_id)
            ->with('success', 'Undangan diterima. Selamat belajar!');

    } catch (InvitationNotPendingException) {
        return back()->with('error', 'Undangan ini sudah tidak berlaku.');
    } catch (InvitationExpiredException) {
        return back()->with('error', 'Undangan ini sudah kadaluarsa.');
    } catch (AlreadyEnrolledException) {
        return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
    } catch (CourseNotPublishedException) {
        return back()->with('error', 'Kursus ini belum dipublikasikan.');
    } catch (QueryException $e) {
        if ($e->errorInfo[1] === 1062) {
            return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
        }
        throw $e;
    }
}
```

### Note: Custom Exceptions Needed

Create these in `App\Domain\Enrollment\Exceptions\`:
- `InvitationNotPendingException`
- `InvitationExpiredException`

Or use a single generic approach with error codes

---

## Implementation Order

1. **P0 First**: State mutation bug - causes data corruption
2. **P1 Second**: Race condition in store() - causes 500 errors under load
3. **P2 Third**: Race condition in acceptInvitation() - same pattern as P1

## Testing Requirements

### P0 Tests
```php
it('reverts completed path to active using proper state transition', function () {
    // Setup completed path enrollment
    // Trigger course drop event
    // Assert state is ActivePathState instance, not string
    // Assert isActive() returns true
});
```

### P1 Tests
```php
it('handles concurrent enrollment attempts gracefully', function () {
    // This is tricky to test - consider using database transactions
    // Or test that duplicate key violation returns friendly error
});

it('updates invitation status within same transaction as enrollment', function () {
    // Mock service to throw after enrollment
    // Assert invitation status unchanged
});
```

### P2 Tests
```php
it('handles concurrent invitation acceptance gracefully', function () {
    $course = Course::factory()->published()->create();
    $invitation = CourseInvitation::factory()->pending()->create([
        'course_id' => $course->id,
    ]);

    // Test that duplicate key violation returns friendly error
    // Similar approach to P1 tests
});

it('updates invitation status within same transaction as enrollment', function () {
    // Test that if enrollment fails, invitation remains pending
    // Mock service to throw after enrollment attempt
    // Assert invitation status unchanged
});

it('prevents double acceptance of same invitation', function () {
    // Test lockForUpdate prevents concurrent acceptance
});
```

---

## Related Files to Audit

After fixing these, audit similar patterns in:

1. **State assignments**: `grep -r "->state = " app/`
2. **Invitation status checks**: `grep -r "status.*pending" app/Policies/`
3. **Controller transactions**: Check all controllers for transaction boundaries
