# Phase 1: Attempts List Page

> **Priority**: High
> **Dependencies**: None (uses existing Grade page)
> **Estimated Effort**: Medium

---

## Objectives

1. Create a centralized page to view all assessment attempts
2. Add filtering by status, course, and assessment
3. Provide direct navigation to grade pending submissions
4. Add sidebar navigation entry for grading workflow

---

## User Stories Addressed

- **US-G02**: View all assessment attempts for my courses
- **US-G03**: Filter attempts by status (pending, graded)
- **US-G04**: Click directly to grade from attempts list
- **US-G06**: Admin can view all attempts across all courses

---

## Page Structure

```
┌─────────────────────────────────────────────────────────────────┐
│  PageHeader: "Penilaian Tugas"                                  │
│  Subtitle: "Kelola dan nilai submission dari peserta"           │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  FILTERS                                                         │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌───────────┐ │
│  │ Status ▼    │ │ Kursus ▼    │ │ Assessment ▼│ │ 🔍 Cari   │ │
│  └─────────────┘ └─────────────┘ └─────────────┘ └───────────┘ │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  ATTEMPTS TABLE                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ Peserta     │ Assessment    │ Kursus    │ Status │ Aksi  │   │
│  ├──────────────────────────────────────────────────────────┤   │
│  │ John Doe    │ Quiz Bab 1    │ Laravel   │ 🟡 Pending │ [Nilai] │
│  │ Jane Smith  │ Ujian Akhir   │ Vue.js    │ ✅ 85/100  │ [Lihat] │
│  │ Bob Wilson  │ Quiz Bab 2    │ Laravel   │ 🟡 Pending │ [Nilai] │
│  └──────────────────────────────────────────────────────────┘   │
│  < 1 2 3 ... 10 >                                               │
└─────────────────────────────────────────────────────────────────┘
```

---

## Technical Implementation

### 1. Controller

```php
// app/Http/Controllers/AssessmentAttemptController.php

namespace App\Http\Controllers;

use App\Models\AssessmentAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentAttemptController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $query = AssessmentAttempt::query()
            ->with([
                'user:id,name,email',
                'assessment:id,title,course_id',
                'assessment.course:id,title,user_id',
            ])
            ->latest('submitted_at');

        // Authorization: CM sees only their courses, Admin sees all
        if ($user->isContentManager()) {
            $query->whereHas('assessment.course', fn($q) =>
                $q->where('user_id', $user->id)
            );
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by course
        if ($request->filled('course_id')) {
            $query->whereHas('assessment', fn($q) =>
                $q->where('course_id', $request->course_id)
            );
        }

        // Filter by assessment
        if ($request->filled('assessment_id')) {
            $query->where('assessment_id', $request->assessment_id);
        }

        // Search by learner name
        if ($request->filled('search')) {
            $query->whereHas('user', fn($q) =>
                $q->where('name', 'like', "%{$request->search}%")
            );
        }

        $attempts = $query->paginate(15)->withQueryString();

        // Get filter options
        $coursesQuery = $user->isLmsAdmin()
            ? Course::query()
            : Course::where('user_id', $user->id);

        $courses = $coursesQuery
            ->whereHas('assessments.attempts')
            ->select('id', 'title')
            ->get();

        return Inertia::render('assessments/Attempts', [
            'attempts' => $attempts,
            'filters' => $request->only(['status', 'course_id', 'assessment_id', 'search']),
            'courses' => $courses,
            'statuses' => [
                ['value' => 'submitted', 'label' => 'Menunggu Penilaian'],
                ['value' => 'graded', 'label' => 'Sudah Dinilai'],
                ['value' => 'in_progress', 'label' => 'Sedang Dikerjakan'],
                ['value' => 'expired', 'label' => 'Kadaluarsa'],
            ],
        ]);
    }
}
```

### 2. Routes

```php
// routes/assessments.php (or routes/web.php)

use App\Http\Controllers\AssessmentAttemptController;

Route::middleware(['auth', 'role:lms_admin,content_manager'])->group(function () {
    Route::get('/assessments/attempts', [AssessmentAttemptController::class, 'index'])
        ->name('assessments.attempts.index');
});
```

### 3. Vue Page Component

```vue
<!-- resources/js/pages/assessments/Attempts.vue -->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/crud/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Link } from '@inertiajs/vue3'
import { debounce } from 'lodash-es'

interface Attempt {
    id: number
    status: string
    score: number | null
    submitted_at: string
    graded_at: string | null
    user: {
        id: number
        name: string
        email: string
    }
    assessment: {
        id: number
        title: string
        course_id: number
        course: {
            id: number
            title: string
        }
    }
}

interface Props {
    attempts: {
        data: Attempt[]
        links: any
        meta: any
    }
    filters: {
        status?: string
        course_id?: string
        assessment_id?: string
        search?: string
    }
    courses: Array<{ id: number; title: string }>
    statuses: Array<{ value: string; label: string }>
}

const props = defineProps<Props>()

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const courseId = ref(props.filters.course_id ?? '')

const applyFilters = debounce(() => {
    router.get(
        route('assessments.attempts.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            course_id: courseId.value || undefined,
        },
        { preserveState: true, replace: true }
    )
}, 300)

watch([search, status, courseId], applyFilters)

const getStatusBadge = (attempt: Attempt) => {
    switch (attempt.status) {
        case 'submitted':
            return { label: 'Menunggu Penilaian', variant: 'warning' as const }
        case 'graded':
            return { label: `${attempt.score}/100`, variant: 'success' as const }
        case 'in_progress':
            return { label: 'Sedang Dikerjakan', variant: 'secondary' as const }
        case 'expired':
            return { label: 'Kadaluarsa', variant: 'destructive' as const }
        default:
            return { label: attempt.status, variant: 'outline' as const }
    }
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}
</script>

<template>
    <AppLayout>
        <Head title="Penilaian Tugas" />

        <div class="space-y-6">
            <PageHeader
                title="Penilaian Tugas"
                description="Kelola dan nilai submission dari peserta"
            />

            <!-- Filters -->
            <div class="flex flex-wrap gap-4">
                <div class="w-64">
                    <Input
                        v-model="search"
                        placeholder="Cari peserta..."
                        type="search"
                    />
                </div>

                <Select v-model="status">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Semua Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">Semua Status</SelectItem>
                        <SelectItem
                            v-for="s in statuses"
                            :key="s.value"
                            :value="s.value"
                        >
                            {{ s.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="courseId">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Semua Kursus" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">Semua Kursus</SelectItem>
                        <SelectItem
                            v-for="course in courses"
                            :key="course.id"
                            :value="String(course.id)"
                        >
                            {{ course.title }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Table -->
            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Peserta</TableHead>
                            <TableHead>Assessment</TableHead>
                            <TableHead>Kursus</TableHead>
                            <TableHead>Waktu Submit</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="attempt in attempts.data"
                            :key="attempt.id"
                        >
                            <TableCell>
                                <div>
                                    <div class="font-medium">{{ attempt.user.name }}</div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ attempt.user.email }}
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>{{ attempt.assessment.title }}</TableCell>
                            <TableCell>{{ attempt.assessment.course.title }}</TableCell>
                            <TableCell>
                                {{ attempt.submitted_at ? formatDate(attempt.submitted_at) : '-' }}
                            </TableCell>
                            <TableCell>
                                <Badge :variant="getStatusBadge(attempt).variant">
                                    {{ getStatusBadge(attempt).label }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <Link
                                    v-if="attempt.status === 'submitted'"
                                    :href="route('assessments.grade', [
                                        attempt.assessment.course_id,
                                        attempt.assessment.id,
                                        attempt.id
                                    ])"
                                >
                                    <Button size="sm">Nilai</Button>
                                </Link>
                                <Link
                                    v-else
                                    :href="route('assessments.attempt.complete', [
                                        attempt.assessment.course_id,
                                        attempt.assessment.id,
                                        attempt.id
                                    ])"
                                >
                                    <Button size="sm" variant="outline">Lihat</Button>
                                </Link>
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="attempts.data.length === 0">
                            <TableCell colspan="6" class="text-center py-8 text-muted-foreground">
                                Tidak ada submission ditemukan
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Pagination -->
            <div v-if="attempts.meta.last_page > 1" class="flex justify-center">
                <!-- Add pagination component here -->
            </div>
        </div>
    </AppLayout>
</template>
```

### 4. Sidebar Navigation Update

```vue
<!-- Update resources/js/components/AppSidebar.vue -->
<!-- Add to navMain items for content_manager and lms_admin -->

{
    title: 'Penilaian',
    url: route('assessments.attempts.index'),
    icon: ClipboardCheck, // from lucide-vue-next
    isActive: route().current('assessments.attempts.*'),
}
```

---

## Implementation Checklist

### Backend

- [ ] Create `AssessmentAttemptController` with `index` method
- [ ] Add route in `routes/assessments.php` or `routes/web.php`
- [ ] Add authorization middleware (role check)
- [ ] Add query scope for CM-owned courses
- [ ] Test N+1 query prevention with eager loading

### Frontend

- [ ] Create `resources/js/pages/assessments/Attempts.vue`
- [ ] Implement filtering (status, course, search)
- [ ] Add status badges with Indonesian labels
- [ ] Add action buttons (Nilai/Lihat)
- [ ] Add pagination component
- [ ] Support dark mode

### Navigation

- [ ] Add sidebar menu item for CM and Admin
- [ ] Add appropriate Lucide icon (ClipboardCheck)
- [ ] Set active state for current route

### Testing

- [ ] Test CM can only see own course attempts
- [ ] Test Admin can see all attempts
- [ ] Test filtering by status
- [ ] Test filtering by course
- [ ] Test search by learner name
- [ ] Test Grade button only shows for submitted attempts
- [ ] Test pagination works correctly

### Wayfinder

- [ ] Run `php artisan wayfinder:generate` after adding route
- [ ] Update imports in Vue component to use Wayfinder

---

## Indonesian Labels Reference

| English | Indonesian |
|---------|------------|
| Grading | Penilaian |
| Assessment Attempts | Submission Tugas |
| Pending Review | Menunggu Penilaian |
| Graded | Sudah Dinilai |
| In Progress | Sedang Dikerjakan |
| Expired | Kadaluarsa |
| Participant | Peserta |
| Course | Kursus |
| Submit Time | Waktu Submit |
| Status | Status |
| Action | Aksi |
| Grade | Nilai |
| View | Lihat |
| Search participant | Cari peserta |
| All Status | Semua Status |
| All Courses | Semua Kursus |
| No submissions found | Tidak ada submission ditemukan |

---

## Related Files

- Existing Grade page: `resources/js/pages/assessments/Grade.vue`
- Table pattern reference: `resources/js/pages/courses/Index.vue`
- Filter pattern reference: `resources/js/pages/courses/Browse.vue`
