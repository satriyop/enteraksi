---
name: enteraksi-concurrency
description: Race condition prevention and pessimistic locking patterns for Enteraksi LMS. Use when handling concurrent user operations, enrollment flows, or any operation where double-submission could cause issues.
triggers:
  - race condition
  - concurrent
  - lockForUpdate
  - pessimistic lock
  - double submit
  - duplicate enrollment
  - QueryException
  - DatabaseHelper
  - isDuplicateKeyException
  - unique constraint
  - transaction boundary
  - atomicity
  - concurrent enrollment
  - double click
---

# Enteraksi Concurrency Patterns

## When to Use This Skill

- Handling enrollment operations (users clicking "enroll" twice)
- Processing invitation acceptance
- Any operation with unique constraints that could race
- Preventing double-submission of forms
- Wrapping related operations in atomic transactions

## The Race Condition Problem

### Scenario: Concurrent Enrollment

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
                                   500 Error (QueryException not caught)
invitation → accepted
                                   User sees database error
```

### Why It Happens

1. Two requests pass validation checks simultaneously
2. First request completes successfully
3. Second request hits `UNIQUE (user_id, course_id)` constraint
4. `QueryException` (not `AlreadyEnrolledException`) is thrown
5. User sees 500 error instead of friendly message

---

## Pattern 1: Transaction with Pessimistic Locking

Use `lockForUpdate()` to prevent concurrent modifications to related rows.

### The Anti-Pattern (BAD)

```php
public function store(Request $request, Course $course): RedirectResponse
{
    $user = $request->user();

    // ❌ Query OUTSIDE transaction - can become stale
    $invitation = $user->courseInvitations()
        ->where('course_id', $course->id)
        ->where('status', 'pending')
        ->first();

    try {
        // ❌ Service has its own transaction
        $this->enrollmentService->enroll($dto);

        // ❌ Update OUTSIDE service transaction
        if ($invitation) {
            $invitation->update(['status' => 'accepted']);
        }

    } catch (AlreadyEnrolledException) {
        return back()->with('error', 'Already enrolled');
    }
    // ❌ QueryException NOT caught - user sees 500!
}
```

### The Fix: Wrap Everything in Single Transaction

```php
public function store(Request $request, Course $course): RedirectResponse
{
    Gate::authorize('enroll', $course);
    $user = $request->user();

    try {
        DB::transaction(function () use ($user, $course) {
            // Lock invitation row to prevent concurrent updates
            $invitation = $user->courseInvitations()
                ->where('course_id', $course->id)
                ->where('status', 'pending')
                ->lockForUpdate()  // ← Pessimistic lock
                ->first();

            $dto = new CreateEnrollmentDTO(
                userId: $user->id,
                courseId: $course->id,
                invitedBy: $invitation?->invited_by,
            );

            $this->enrollmentService->enroll($dto);

            // Update invitation inside SAME transaction
            if ($invitation) {
                $invitation->update([
                    'status' => 'accepted',
                    'responded_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('courses.show', $course)
            ->with('success', 'Berhasil mendaftar ke kursus.');

    } catch (AlreadyEnrolledException) {
        return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
    } catch (CourseNotPublishedException) {
        return back()->with('error', 'Kursus ini belum dipublikasikan.');
    } catch (QueryException $e) {
        // Catch unique constraint violation as FALLBACK (database-agnostic)
        if (DatabaseHelper::isDuplicateKeyException($e)) {
            return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
        }
        throw $e;
    }
}
```

---

## Pattern 2: Re-fetch and Re-validate Inside Transaction

When accepting invitations or processing items that could change state concurrently.

### The Problem

```php
public function acceptInvitation(Request $request, CourseInvitation $invitation)
{
    // ❌ Status check OUTSIDE transaction - can become stale
    if ($invitation->status !== 'pending') {
        return back()->with('error', 'Invitation no longer valid');
    }

    // Between check and enroll(), another request could accept it!
    $this->enrollmentService->enroll($dto);
    $invitation->update(['status' => 'accepted']);
}
```

### The Fix: Lock, Re-fetch, Re-validate

```php
public function acceptInvitation(Request $request, CourseInvitation $invitation)
{
    $user = $request->user();

    // Verify ownership first (doesn't need transaction)
    if ($invitation->user_id !== $user->id) {
        abort(403);
    }

    try {
        $courseId = DB::transaction(function () use ($user, $invitation) {
            // Lock and RE-FETCH to get current state
            $lockedInvitation = CourseInvitation::lockForUpdate()
                ->findOrFail($invitation->id);

            // Re-check status INSIDE transaction
            if ($lockedInvitation->status !== 'pending') {
                throw new \RuntimeException('invitation_not_pending');
            }

            if ($lockedInvitation->is_expired) {
                $lockedInvitation->update(['status' => 'expired']);
                throw new \RuntimeException('invitation_expired');
            }

            $dto = new CreateEnrollmentDTO(
                userId: $user->id,
                courseId: $lockedInvitation->course_id,
                invitedBy: $lockedInvitation->invited_by,
            );

            $this->enrollmentService->enroll($dto);

            // Update inside SAME transaction
            $lockedInvitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            return $lockedInvitation->course_id;
        });

        return redirect()
            ->route('courses.show', $courseId)
            ->with('success', 'Undangan diterima!');

    } catch (\RuntimeException $e) {
        if ($e->getMessage() === 'invitation_not_pending') {
            return back()->with('error', 'Undangan ini sudah tidak berlaku.');
        }
        if ($e->getMessage() === 'invitation_expired') {
            return back()->with('error', 'Undangan ini sudah kadaluarsa.');
        }
        throw $e;
    } catch (AlreadyEnrolledException) {
        return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
    } catch (QueryException $e) {
        if (DatabaseHelper::isDuplicateKeyException($e)) {
            return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
        }
        throw $e;
    }
}
```

---

## Pattern 3: Database-Agnostic Duplicate Key Handling

Use `DatabaseHelper::isDuplicateKeyException()` to catch unique constraint violations across all database engines.

### The Helper Class

```php
// app/Support/Helpers/DatabaseHelper.php
namespace App\Support\Helpers;

use Illuminate\Database\QueryException;

class DatabaseHelper
{
    /**
     * Check if a QueryException is a unique constraint violation.
     * Works across MySQL, PostgreSQL, SQLite, and SQL Server.
     */
    public static function isDuplicateKeyException(QueryException $e): bool
    {
        $code = $e->errorInfo[1] ?? null;
        $sqlState = $e->errorInfo[0] ?? null;

        // MySQL: error code 1062
        if ($code === 1062) {
            return true;
        }

        // PostgreSQL: SQLSTATE 23505
        if ($sqlState === '23505') {
            return true;
        }

        // SQLite: error code 19 with "UNIQUE constraint failed"
        if ($code === 19 && str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
            return true;
        }

        // SQL Server: error code 2627
        if ($code === 2627) {
            return true;
        }

        // Fallback: check message for common patterns
        return str_contains($e->getMessage(), 'Duplicate entry')
            || str_contains($e->getMessage(), 'unique constraint')
            || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
```

### Usage

```php
use App\Support\Helpers\DatabaseHelper;
use Illuminate\Database\QueryException;

try {
    // ... enrollment logic ...
} catch (QueryException $e) {
    if (DatabaseHelper::isDuplicateKeyException($e)) {
        return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
    }
    throw $e;  // Re-throw other database errors
}
```

### Database Error Codes Reference

| Database | Error Code | SQLSTATE | Description |
|----------|------------|----------|-------------|
| MySQL | 1062 | 23000 | Duplicate entry |
| PostgreSQL | - | 23505 | unique_violation |
| SQLite | 19 | - | UNIQUE constraint failed |
| SQL Server | 2627 | 23000 | Unique constraint violation |

### Why Not Hardcode MySQL?

```php
// ❌ BAD: MySQL-only, breaks on other databases
if ($e->errorInfo[1] === 1062) {
    return back()->with('error', 'Already enrolled');
}

// ✅ GOOD: Works on MySQL, PostgreSQL, SQLite, SQL Server
if (DatabaseHelper::isDuplicateKeyException($e)) {
    return back()->with('error', 'Already enrolled');
}
```

Even if you're only using MySQL today, using the helper:
- Allows painless database migrations
- Makes tests work with SQLite
- Follows the principle of abstraction

---

## Key Principles

### 1. Transaction Boundaries

Keep all related operations in ONE transaction:

```php
// ❌ BAD: Operations in separate transactions
$this->enrollmentService->enroll($dto);  // Transaction 1
$invitation->update(['status' => 'accepted']);  // Transaction 2

// ✅ GOOD: All in one transaction
DB::transaction(function () use ($dto, $invitation) {
    $this->enrollmentService->enroll($dto);
    $invitation->update(['status' => 'accepted']);
});
```

### 2. Lock Before Read

When you need to update based on current value:

```php
// ❌ BAD: Read then update (race window exists)
$invitation = CourseInvitation::find($id);
if ($invitation->status === 'pending') {
    $invitation->update(['status' => 'accepted']);
}

// ✅ GOOD: Lock, then read, then update
DB::transaction(function () use ($id) {
    $invitation = CourseInvitation::lockForUpdate()->findOrFail($id);
    if ($invitation->status === 'pending') {
        $invitation->update(['status' => 'accepted']);
    }
});
```

### 3. Catch Both Domain Exception AND QueryException

Domain exceptions (AlreadyEnrolledException) are thrown when service detects duplicate.
QueryException is thrown when database constraint catches the race.

```php
use App\Support\Helpers\DatabaseHelper;

try {
    // ...
} catch (AlreadyEnrolledException) {
    // Service detected duplicate
    return back()->with('error', 'Already enrolled');
} catch (QueryException $e) {
    // Database caught the race condition (works on any database)
    if (DatabaseHelper::isDuplicateKeyException($e)) {
        return back()->with('error', 'Already enrolled');
    }
    throw $e;
}
```

---

## When to Use Each Pattern

| Scenario | Pattern |
|----------|---------|
| Simple enrollment | Transaction + QueryException catch |
| Invitation with status check | Lock + Re-fetch + Re-validate |
| Multiple related updates | Single transaction boundary |
| Idempotent operations | `firstOrCreate()` / `updateOrCreate()` |

---

## Files to Reference

```
app/Support/Helpers/DatabaseHelper.php                     # isDuplicateKeyException() - database-agnostic helper
app/Http/Controllers/EnrollmentController.php              # store(), acceptInvitation() - full transaction + locking
app/Http/Controllers/LearningPathEnrollmentController.php  # enroll() - QueryException fallback
plans/fixing/ROUND4_CRITICAL_BUGS.md                       # Documentation of race condition fixes
plans/fixing/ROUND6_CODE_QUALITY.md                        # DatabaseHelper introduction
```

---

## Testing Race Conditions

Testing true race conditions is tricky. Focus on:

1. **Duplicate key handling**: Test that QueryException returns friendly error
2. **Transaction atomicity**: Mock service to throw mid-transaction, verify rollback
3. **Status re-validation**: Test expired/accepted invitation handling

```php
it('handles duplicate enrollment gracefully', function () {
    $user = User::factory()->learner()->create();
    $course = Course::factory()->published()->create();

    // Create enrollment directly (simulating first request completed)
    Enrollment::factory()->for($user)->for($course)->create();

    // Second request should get friendly error, not 500
    $this->actingAs($user)
        ->post(route('courses.enroll', $course))
        ->assertRedirect()
        ->assertSessionHas('error', 'Anda sudah terdaftar di kursus ini.');
});

it('rejects already-accepted invitation', function () {
    $invitation = CourseInvitation::factory()->create(['status' => 'accepted']);

    $this->actingAs($invitation->user)
        ->post(route('invitations.accept', $invitation))
        ->assertSessionHas('error');
});
```
