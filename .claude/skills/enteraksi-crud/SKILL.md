---
name: enteraksi-crud
description: Consistent CRUD page patterns (full-stack) for Enteraksi LMS. Use when building list pages, form pages, or resource controllers.
triggers:
  - create page
  - index page
  - edit page
  - show page
  - crud controller
  - resource controller
  - form request
  - validation
  - PageHeader
  - FormSection
  - DataCard
  - list page
  - form page
---

# Enteraksi CRUD Patterns

## When to Use This Skill

- Building CRUD pages (Index, Create, Edit, Show)
- Creating resource controllers
- Writing FormRequest validation
- Using reusable CRUD components
- Implementing flash messages and errors

## Full-Stack CRUD Flow

```
Request → Controller → FormRequest → Model → Event → Response
   ↓          ↓            ↓           ↓
 Route    Gate::authorize  rules()   create/update
   ↓          ↓            ↓           ↓
Inertia   Policy check   messages()  Dispatch event
   ↓                                    ↓
  Vue Page                          Flash message
```

## Key Patterns

### 1. Controller (Index + Create + Store)

```php
// app/Http/Controllers/CourseController.php
namespace App\Http\Controllers;

use App\Http\Requests\Course\StoreCourseRequest;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Course::class);

        $query = Course::query()
            ->with(['category', 'user'])
            ->withCount(['lessons', 'enrollments']);

        // Role-based filtering
        $user = $request->user();
        if ($user->isLearner()) {
            $query->published()->visible();
        } elseif (!$user->isLmsAdmin()) {
            $query->where('user_id', $user->id);
        }

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $courses = $query->latest()->paginate(12)->withQueryString();

        return Inertia::render('courses/Index', [
            'courses' => $courses,
            'categories' => Category::orderBy('name')->get(),
            'filters' => $request->only(['search', 'status', 'category_id']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        Gate::authorize('create', Course::class);

        return Inertia::render('courses/Create', [
            'categories' => Category::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = $request->user()->id;

        // Handle file upload
        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail_path'] = $request->file('thumbnail')
                ->store('courses/thumbnails', 'public');
        }

        $course = Course::create($validated);

        // Sync relations
        if (isset($validated['tags'])) {
            $course->tags()->sync($validated['tags']);
        }

        return redirect()
            ->route('courses.edit', $course)
            ->with('success', 'Kursus berhasil dibuat.');
    }
}
```

### 2. FormRequest with Indonesian Messages

```php
// app/Http/Requests/Course/StoreCourseRequest.php
namespace App\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', \App\Models\Course::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'long_description' => ['nullable', 'string'],
            'objectives' => ['nullable', 'array'],
            'objectives.*' => ['string', 'max:500'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'difficulty_level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul kursus wajib diisi.',
            'title.max' => 'Judul kursus maksimal 255 karakter.',
            'short_description.max' => 'Deskripsi singkat maksimal 500 karakter.',
            'category_id.exists' => 'Kategori yang dipilih tidak valid.',
            'thumbnail.image' => 'Thumbnail harus berupa gambar.',
            'thumbnail.mimes' => 'Format thumbnail harus jpeg, png, jpg, atau webp.',
            'thumbnail.max' => 'Ukuran thumbnail maksimal 2MB.',
            'difficulty_level.required' => 'Tingkat kesulitan wajib dipilih.',
            'difficulty_level.in' => 'Tingkat kesulitan tidak valid.',
        ];
    }
}
```

### 3. Index Page (List)

```vue
<!-- resources/js/pages/courses/Index.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { PageHeader, SearchInput, FilterTabs, DataCard, EmptyState, Pagination } from '@/components/crud';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { Plus, BookOpen } from 'lucide-vue-next';
import { index, create, show } from '@/actions/App/Http/Controllers/CourseController';
import type { AppPageProps, CourseListItem, Category, PaginatedResponse } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<AppPageProps<{
    courses: PaginatedResponse<CourseListItem>;
    categories: Category[];
    filters: {
        search?: string;
        status?: string;
        category_id?: number;
    };
}>>();

const statusTabs = [
    { value: '', label: 'Semua' },
    { value: 'draft', label: 'Draf' },
    { value: 'published', label: 'Dipublikasikan' },
    { value: 'archived', label: 'Diarsipkan' },
];
</script>

<template>
    <div class="space-y-6">
        <!-- Header with action button -->
        <PageHeader
            title="Kursus Saya"
            description="Kelola kursus yang Anda buat"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="create.url()">
                        <Plus class="mr-2 h-4 w-4" />
                        Buat Kursus
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <!-- Filters -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <SearchInput
                v-model="filters.search"
                placeholder="Cari kursus..."
                :action="index.url()"
            />
            <FilterTabs
                v-model="filters.status"
                :tabs="statusTabs"
                :action="index.url()"
            />
        </div>

        <!-- Content List -->
        <div v-if="courses.data.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <DataCard
                v-for="course in courses.data"
                :key="course.id"
                :href="show.url(course.id)"
            >
                <template #image>
                    <img :src="course.thumbnail_url" :alt="course.title" />
                </template>
                <template #title>{{ course.title }}</template>
                <template #meta>
                    <span>{{ course.lessons_count }} pelajaran</span>
                    <span>{{ course.enrollments_count }} peserta</span>
                </template>
            </DataCard>
        </div>

        <!-- Empty State -->
        <EmptyState
            v-else
            :icon="BookOpen"
            title="Belum ada kursus"
            description="Mulai buat kursus pertama Anda"
        >
            <Button as-child>
                <Link :href="create.url()">Buat Kursus</Link>
            </Button>
        </EmptyState>

        <!-- Pagination -->
        <Pagination :links="courses.links" :meta="courses.meta" />
    </div>
</template>
```

### 4. Create/Edit Page (Form)

```vue
<!-- resources/js/pages/courses/Create.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { PageHeader, FormSection } from '@/components/crud';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/InputError.vue';
import { Form } from '@inertiajs/vue3';
import { store, index } from '@/actions/App/Http/Controllers/CourseController';
import type { AppPageProps, Category, Tag } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<AppPageProps<{
    categories: Category[];
    tags: Tag[];
}>>();
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <PageHeader
            title="Buat Kursus"
            description="Isi informasi dasar kursus Anda"
            :back-href="index.url()"
            back-label="Kembali ke daftar kursus"
        />

        <Form
            v-bind="store.form()"
            #default="{ errors, processing }"
        >
            <div class="space-y-6">
                <!-- Basic Info Section -->
                <FormSection
                    title="Informasi Dasar"
                    description="Detail utama tentang kursus"
                >
                    <div class="space-y-4">
                        <div>
                            <Label for="title">Judul Kursus</Label>
                            <Input
                                id="title"
                                name="title"
                                placeholder="Masukkan judul kursus"
                            />
                            <InputError :message="errors.title" />
                        </div>

                        <div>
                            <Label for="short_description">Deskripsi Singkat</Label>
                            <Textarea
                                id="short_description"
                                name="short_description"
                                rows="3"
                                placeholder="Jelaskan kursus dalam 2-3 kalimat"
                            />
                            <InputError :message="errors.short_description" />
                        </div>
                    </div>
                </FormSection>

                <!-- Category Section -->
                <FormSection
                    title="Kategori & Tag"
                    description="Bantu peserta menemukan kursus Anda"
                >
                    <div class="space-y-4">
                        <div>
                            <Label for="category_id">Kategori</Label>
                            <select id="category_id" name="category_id" class="...">
                                <option value="">Pilih kategori</option>
                                <option
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="cat.id"
                                >
                                    {{ cat.name }}
                                </option>
                            </select>
                            <InputError :message="errors.category_id" />
                        </div>
                    </div>
                </FormSection>

                <!-- Submit -->
                <div class="flex justify-end gap-3">
                    <Button variant="outline" as-child>
                        <a :href="index.url()">Batal</a>
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Menyimpan...' : 'Buat Kursus' }}
                    </Button>
                </div>
            </div>
        </Form>
    </div>
</template>
```

## CRUD Components Reference

### PageHeader
```vue
<PageHeader
    title="Page Title"
    description="Optional description"
    :back-href="index.url()"
    back-label="Kembali"
>
    <template #actions>
        <Button>Action</Button>
    </template>
</PageHeader>
```

### FormSection
```vue
<FormSection
    title="Section Title"
    description="Optional description"
>
    <!-- Form fields here -->
</FormSection>
```

### DataCard
```vue
<DataCard :href="show.url(item.id)">
    <template #image>...</template>
    <template #title>{{ item.title }}</template>
    <template #meta>
        <span>Meta 1</span>
        <span>Meta 2</span>
    </template>
</DataCard>
```

### EmptyState
```vue
<EmptyState
    :icon="BookOpen"
    title="No items found"
    description="Create your first item"
>
    <Button as-child>
        <Link :href="create.url()">Create</Link>
    </Button>
</EmptyState>
```

### SearchInput
```vue
<SearchInput
    v-model="filters.search"
    placeholder="Search..."
    :action="index.url()"
/>
```

### FilterTabs
```vue
<FilterTabs
    v-model="filters.status"
    :tabs="[
        { value: '', label: 'All' },
        { value: 'draft', label: 'Draft' },
    ]"
    :action="index.url()"
/>
```

### Pagination
```vue
<Pagination :links="items.links" :meta="items.meta" />
```

## Flash Messages

```php
// Controller
return redirect()
    ->route('courses.index')
    ->with('success', 'Kursus berhasil disimpan.');

// Or with error
return redirect()
    ->back()
    ->with('error', 'Gagal menyimpan kursus.');
```

```vue
<!-- FlashMessages.vue (in layout) -->
<div v-if="flash?.success" class="bg-green-100 text-green-800 ...">
    {{ flash.success }}
</div>
<div v-if="flash?.error" class="bg-red-100 text-red-800 ...">
    {{ flash.error }}
</div>
```

## Breadcrumbs

```vue
<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { index, show } from '@/actions/App/Http/Controllers/CourseController';

defineOptions({ layout: AppLayout });

const breadcrumbs = [
    { title: 'Kursus', href: index.url() },
    { title: props.course.title, href: show.url(props.course.id) },
    { title: 'Edit', href: '#' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- content -->
    </AppLayout>
</template>
```

## Gotchas & Best Practices

1. **Always Gate::authorize() first** - Before any query
2. **FormRequest for all mutations** - Never validate inline
3. **Indonesian messages** - All error messages in Bahasa
4. **Array rules** - `['required', 'string']` not `'required|string'`
5. **Use Wayfinder** - Never hardcode routes
6. **withQueryString()** - On paginated responses to preserve filters
7. **Flash success/error** - Always give user feedback
8. **InputError component** - Consistent error display

## File Organization

```
app/Http/
├── Controllers/
│   └── CourseController.php
└── Requests/
    └── Course/
        ├── StoreCourseRequest.php
        └── UpdateCourseRequest.php

resources/js/
├── pages/
│   └── courses/
│       ├── Index.vue
│       ├── Create.vue
│       ├── Edit.vue
│       └── Show.vue
└── components/
    └── crud/
        ├── PageHeader.vue
        ├── FormSection.vue
        ├── DataCard.vue
        ├── EmptyState.vue
        ├── SearchInput.vue
        ├── FilterTabs.vue
        └── Pagination.vue
```

## Quick Reference

```bash
# Create controller
php artisan make:controller CourseController --resource

# Create form request
php artisan make:request Course/StoreCourseRequest

# Files to reference
app/Http/Controllers/CourseController.php
app/Http/Requests/Course/StoreCourseRequest.php
resources/js/pages/courses/Index.vue
resources/js/pages/courses/Create.vue
resources/js/components/crud/
```
