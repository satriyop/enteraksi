# Phase 2: Dashboard Widget

> **Priority**: Medium
> **Dependencies**: Phase 1 (Attempts List page for "Lihat Semua" link)
> **Estimated Effort**: Low

---

## Objectives

1. Show pending grading count on dashboard
2. Provide quick access to attempts list
3. Use deferred props for non-blocking load
4. Different displays for CM vs Admin

---

## User Stories Addressed

- **US-G01**: See pending grading count on dashboard
- **US-G05**: Admin sees system-wide pending count

---

## Widget Design

### Content Manager View

```
┌─────────────────────────────────────────┐
│  📋 Penilaian Tertunda                  │
│                                         │
│        ┌─────────────┐                  │
│        │     12      │                  │
│        │  submission │                  │
│        └─────────────┘                  │
│                                         │
│  Menunggu penilaian Anda                │
│                                         │
│  [Lihat Semua →]                        │
└─────────────────────────────────────────┘
```

### Admin View

```
┌─────────────────────────────────────────┐
│  📋 Penilaian Sistem                    │
│                                         │
│   Total Tertunda    Dinilai Hari Ini    │
│   ┌─────────┐       ┌─────────┐         │
│   │   47    │       │   23    │         │
│   └─────────┘       └─────────┘         │
│                                         │
│  [Lihat Semua →]                        │
└─────────────────────────────────────────┘
```

---

## Technical Implementation

### 1. API Controller for Stats

```php
// app/Http/Controllers/Api/GradingStatsController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class GradingStatsController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $cacheKey = "grading_stats_{$user->id}";

        $stats = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user) {
            $baseQuery = AssessmentAttempt::query();

            // CM sees only their courses
            if ($user->isContentManager()) {
                $baseQuery->whereHas('assessment.course', fn($q) =>
                    $q->where('user_id', $user->id)
                );
            }

            $pendingCount = (clone $baseQuery)
                ->where('status', 'submitted')
                ->count();

            $gradedTodayCount = (clone $baseQuery)
                ->where('status', 'graded')
                ->whereDate('graded_at', today())
                ->count();

            return [
                'pending_count' => $pendingCount,
                'graded_today' => $gradedTodayCount,
            ];
        });

        return response()->json($stats);
    }
}
```

### 2. API Route

```php
// routes/api.php

use App\Http\Controllers\Api\GradingStatsController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/grading/stats', [GradingStatsController::class, 'index'])
        ->name('api.grading.stats');
});
```

### 3. Alternative: Deferred Props in Dashboard Controller

```php
// app/Http/Controllers/DashboardController.php

use Inertia\Inertia;

public function index()
{
    return Inertia::render('Dashboard', [
        // ... existing props ...

        // Deferred prop for grading stats
        'gradingStats' => Inertia::defer(fn () => $this->getGradingStats()),
    ]);
}

private function getGradingStats(): ?array
{
    $user = Auth::user();

    // Only for CM and Admin
    if (!$user->isContentManager() && !$user->isLmsAdmin()) {
        return null;
    }

    $baseQuery = AssessmentAttempt::query();

    if ($user->isContentManager()) {
        $baseQuery->whereHas('assessment.course', fn($q) =>
            $q->where('user_id', $user->id)
        );
    }

    return [
        'pending_count' => (clone $baseQuery)->where('status', 'submitted')->count(),
        'graded_today' => (clone $baseQuery)
            ->where('status', 'graded')
            ->whereDate('graded_at', today())
            ->count(),
    ];
}
```

### 4. Vue Component

```vue
<!-- resources/js/components/dashboard/PendingGradingCard.vue -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { ClipboardList } from 'lucide-vue-next'
import { Skeleton } from '@/components/ui/skeleton'

interface Props {
    stats?: {
        pending_count: number
        graded_today: number
    }
    isAdmin?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    isAdmin: false,
})
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">
                {{ isAdmin ? 'Penilaian Sistem' : 'Penilaian Tertunda' }}
            </CardTitle>
            <ClipboardList class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
            <!-- Loading state -->
            <template v-if="!stats">
                <div class="space-y-2">
                    <Skeleton class="h-8 w-16" />
                    <Skeleton class="h-4 w-32" />
                </div>
            </template>

            <!-- Loaded state -->
            <template v-else>
                <div v-if="isAdmin" class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-2xl font-bold">{{ stats.pending_count }}</div>
                        <p class="text-xs text-muted-foreground">Tertunda</p>
                    </div>
                    <div>
                        <div class="text-2xl font-bold">{{ stats.graded_today }}</div>
                        <p class="text-xs text-muted-foreground">Dinilai Hari Ini</p>
                    </div>
                </div>
                <div v-else>
                    <div class="text-2xl font-bold">{{ stats.pending_count }}</div>
                    <p class="text-xs text-muted-foreground">
                        submission menunggu penilaian Anda
                    </p>
                </div>

                <Link
                    :href="route('assessments.attempts.index', { status: 'submitted' })"
                    class="mt-4 block"
                >
                    <Button variant="outline" size="sm" class="w-full">
                        Lihat Semua
                    </Button>
                </Link>
            </template>
        </CardContent>
    </Card>
</template>
```

### 5. Dashboard Integration

```vue
<!-- resources/js/pages/Dashboard.vue -->
<!-- Add to imports -->
<script setup lang="ts">
import PendingGradingCard from '@/components/dashboard/PendingGradingCard.vue'
import { Deferred } from '@inertiajs/vue3'

interface Props {
    // ... existing props ...
    gradingStats?: {
        pending_count: number
        graded_today: number
    }
}

const props = defineProps<Props>()
const user = usePage().props.auth.user
const canGrade = ['lms_admin', 'content_manager'].includes(user.role)
</script>

<template>
    <!-- Add to dashboard grid -->
    <Deferred data="gradingStats" v-if="canGrade">
        <template #fallback>
            <PendingGradingCard :stats="undefined" :is-admin="user.role === 'lms_admin'" />
        </template>
        <PendingGradingCard
            :stats="gradingStats"
            :is-admin="user.role === 'lms_admin'"
        />
    </Deferred>
</template>
```

---

## Implementation Checklist

### Backend

- [ ] Create `GradingStatsController` (if using API approach)
- [ ] OR add deferred prop to `DashboardController`
- [ ] Add cache for stats (5 minute TTL)
- [ ] Test CM sees only own course stats
- [ ] Test Admin sees all stats

### Frontend

- [ ] Create `PendingGradingCard.vue` component
- [ ] Add skeleton loading state
- [ ] Support both CM and Admin views
- [ ] Link to attempts list with status filter
- [ ] Support dark mode

### Dashboard

- [ ] Import and add card to Dashboard.vue
- [ ] Use `<Deferred>` wrapper for non-blocking load
- [ ] Show only for CM and Admin roles

### Testing

- [ ] Test stats calculation for CM
- [ ] Test stats calculation for Admin
- [ ] Test cache invalidation (optional)
- [ ] Test loading state displays skeleton

---

## Cache Invalidation (Optional Enhancement)

To keep stats fresh after grading:

```php
// app/Listeners/InvalidateGradingStatsCache.php

namespace App\Listeners;

use App\Events\AttemptGraded;
use Illuminate\Support\Facades\Cache;

class InvalidateGradingStatsCache
{
    public function handle(AttemptGraded $event): void
    {
        // Invalidate grader's cache
        Cache::forget("grading_stats_{$event->graderId}");

        // Invalidate all admin caches (they see global stats)
        User::where('role', 'lms_admin')->each(function ($admin) {
            Cache::forget("grading_stats_{$admin->id}");
        });
    }
}
```

---

## Indonesian Labels Reference

| English | Indonesian |
|---------|------------|
| Pending Grading | Penilaian Tertunda |
| System Grading | Penilaian Sistem |
| Pending | Tertunda |
| Graded Today | Dinilai Hari Ini |
| submissions awaiting your review | submission menunggu penilaian Anda |
| View All | Lihat Semua |

---

## Related Files

- Dashboard page: `resources/js/pages/Dashboard.vue`
- Card component pattern: `resources/js/components/ui/card/`
- Skeleton component: `resources/js/components/ui/skeleton/`
