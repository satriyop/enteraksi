<script setup lang="ts">
import { index, show, update as updateCourse } from '@/actions/App/Http/Controllers/CourseController';
import { store as storeSection, update as updateSection, destroy as destroySection } from '@/actions/App/Http/Controllers/CourseSectionController';
import { edit as editLesson, destroy as destroyLesson } from '@/actions/App/Http/Controllers/LessonController';
import { sections as reorderSections, lessons as reorderLessons } from '@/actions/App/Http/Controllers/CourseReorderController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Plus,
    X,
    GripVertical,
    ChevronDown,
    ChevronRight,
    Pencil,
    Trash2,
    FileText,
    PlayCircle,
    Youtube,
    Headphones,
    FileDown,
    Video as VideoCall,
    Clock,
    Save,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import draggable from 'vuedraggable';

interface Category {
    id: number;
    name: string;
}

interface Tag {
    id: number;
    name: string;
}

interface Lesson {
    id: number;
    title: string;
    description: string | null;
    order: number;
    content_type: 'text' | 'video' | 'youtube' | 'audio' | 'document' | 'conference';
    estimated_duration_minutes: number;
    is_free_preview: boolean;
}

interface Section {
    id: number;
    title: string;
    description: string | null;
    order: number;
    lessons: Lesson[];
}

interface Course {
    id: number;
    title: string;
    short_description: string;
    long_description: string | null;
    objectives: string[];
    prerequisites: string[];
    category_id: number | null;
    difficulty_level: 'beginner' | 'intermediate' | 'advanced';
    visibility: 'public' | 'restricted' | 'hidden';
    status: 'draft' | 'published' | 'archived';
    manual_duration_minutes: number | null;
    estimated_duration_minutes: number;
    tags: Tag[];
    sections: Section[];
}

interface Props {
    course: Course;
    categories: Category[];
    tags: Tag[];
    can: {
        publish: boolean;
        setStatus: boolean;
        setVisibility: boolean;
        delete: boolean;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Courses', href: index().url },
    { title: props.course.title, href: show(props.course.id).url },
    { title: 'Edit', href: '#' },
];

// Tab management
const activeTab = ref<'info' | 'outline'>('outline');

// Section management
const expandedSections = ref<number[]>(props.course.sections.map(s => s.id));
const editingSectionId = ref<number | null>(null);
const showAddSection = ref(false);

// Local sections state for drag-drop
const localSections = ref<Section[]>(JSON.parse(JSON.stringify(props.course.sections)));

// Watch for props changes and update local state
watch(() => props.course.sections, (newSections) => {
    localSections.value = JSON.parse(JSON.stringify(newSections));
    // Auto-expand new sections
    newSections.forEach(s => {
        if (!expandedSections.value.includes(s.id)) {
            expandedSections.value.push(s.id);
        }
    });
}, { deep: true });

// Form states
const objectives = ref<string[]>(
    props.course.objectives?.length > 0 ? [...props.course.objectives] : ['']
);
const prerequisites = ref<string[]>(
    props.course.prerequisites?.length > 0 ? [...props.course.prerequisites] : ['']
);
const selectedTagIds = ref<number[]>(props.course.tags?.map(t => t.id) ?? []);

// New section form
const newSectionForm = useForm({
    title: '',
    description: '',
});

// Edit section form
const editSectionForm = useForm({
    title: '',
    description: '',
});

const toggleSection = (sectionId: number) => {
    const idx = expandedSections.value.indexOf(sectionId);
    if (idx === -1) {
        expandedSections.value.push(sectionId);
    } else {
        expandedSections.value.splice(idx, 1);
    }
};

const toggleTag = (tagId: number) => {
    const idx = selectedTagIds.value.indexOf(tagId);
    if (idx === -1) {
        selectedTagIds.value.push(tagId);
    } else {
        selectedTagIds.value.splice(idx, 1);
    }
};

const addObjective = () => objectives.value.push('');
const removeObjective = (idx: number) => {
    if (objectives.value.length > 1) objectives.value.splice(idx, 1);
};

const addPrerequisite = () => prerequisites.value.push('');
const removePrerequisite = (idx: number) => {
    if (prerequisites.value.length > 1) prerequisites.value.splice(idx, 1);
};

const contentTypeIcon = (type: string) => {
    switch (type) {
        case 'video':
            return PlayCircle;
        case 'youtube':
            return Youtube;
        case 'audio':
            return Headphones;
        case 'document':
            return FileDown;
        case 'conference':
            return VideoCall;
        case 'text':
        default:
            return FileText;
    }
};

const formatDuration = (minutes: number) => {
    if (minutes < 60) return `${minutes} menit`;
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    if (remainingMinutes === 0) return `${hours} jam`;
    return `${hours}j ${remainingMinutes}m`;
};

const sectionDuration = (section: Section) => {
    return section.lessons.reduce((acc, l) => acc + (l.estimated_duration_minutes || 0), 0);
};

// Add section
const submitNewSection = () => {
    newSectionForm.post(storeSection(props.course.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            newSectionForm.reset();
            showAddSection.value = false;
        },
    });
};

// Start editing section
const startEditSection = (section: Section) => {
    editingSectionId.value = section.id;
    editSectionForm.title = section.title;
    editSectionForm.description = section.description || '';
};

// Cancel editing section
const cancelEditSection = () => {
    editingSectionId.value = null;
    editSectionForm.reset();
};

// Submit section edit
const submitEditSection = (sectionId: number) => {
    editSectionForm.patch(updateSection(sectionId).url, {
        preserveScroll: true,
        onSuccess: () => {
            editingSectionId.value = null;
            editSectionForm.reset();
        },
    });
};

// Delete section
const deleteSection = (section: Section) => {
    if (confirm(`Apakah Anda yakin ingin menghapus bagian "${section.title}"? Semua materi di dalamnya akan ikut terhapus.`)) {
        router.delete(destroySection(section.id).url, { preserveScroll: true });
    }
};

// Delete lesson
const deleteLesson = (lesson: Lesson) => {
    if (confirm(`Apakah Anda yakin ingin menghapus materi "${lesson.title}"?`)) {
        router.delete(destroyLesson(lesson.id).url, { preserveScroll: true });
    }
};

// Get CSRF token from cookie
const getCsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

// Reorder sections after drag
const onSectionDragEnd = () => {
    const sectionIds = localSections.value.map(s => s.id);
    fetch(reorderSections(props.course.id).url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ sections: sectionIds }),
        credentials: 'same-origin',
    });
};

// Reorder lessons after drag
const onLessonDragEnd = (sectionId: number) => {
    const section = localSections.value.find(s => s.id === sectionId);
    if (!section) return;

    const lessonIds = section.lessons.map(l => l.id);
    fetch(reorderLessons(sectionId).url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ lessons: lessonIds }),
        credentials: 'same-origin',
    });
};

const totalLessons = computed(() =>
    localSections.value.reduce((acc, s) => acc + s.lessons.length, 0)
);

const isEditable = computed(() => props.course.status !== 'published');
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Edit: ${course.title}`" />

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="show(course.id).url">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-2xl font-bold">Edit Kursus</h1>
                            <Badge :variant="course.status === 'published' ? 'default' : 'secondary'">
                                {{ course.status === 'published' ? 'Terbit' : course.status === 'draft' ? 'Draft' : 'Arsip' }}
                            </Badge>
                        </div>
                        <p class="text-muted-foreground">{{ course.title }}</p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-2 border-b">
                <button
                    class="px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeTab === 'outline'
                        ? 'border-b-2 border-primary text-primary'
                        : 'text-muted-foreground hover:text-foreground'"
                    @click="activeTab = 'outline'"
                >
                    Outline Kursus
                </button>
                <button
                    class="px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeTab === 'info'
                        ? 'border-b-2 border-primary text-primary'
                        : 'text-muted-foreground hover:text-foreground'"
                    @click="activeTab = 'info'"
                >
                    Informasi Kursus
                </button>
            </div>

            <!-- Outline Tab -->
            <div v-if="activeTab === 'outline'" class="space-y-4">
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Outline Kursus</CardTitle>
                                <CardDescription>
                                    {{ localSections.length }} bagian, {{ totalLessons }} materi
                                    <span class="mx-2">|</span>
                                    <Clock class="inline h-3 w-3" /> {{ formatDuration(course.estimated_duration_minutes) }}
                                </CardDescription>
                            </div>
                            <Button
                                v-if="isEditable"
                                @click="showAddSection = true"
                                :disabled="showAddSection"
                            >
                                <Plus class="mr-2 h-4 w-4" />
                                Tambah Bagian
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <!-- Add Section Form -->
                        <div v-if="showAddSection" class="mb-4 rounded-lg border bg-muted/30 p-4">
                            <h4 class="mb-3 font-medium">Bagian Baru</h4>
                            <form @submit.prevent="submitNewSection" class="space-y-3">
                                <div>
                                    <Input
                                        v-model="newSectionForm.title"
                                        placeholder="Judul bagian"
                                        required
                                    />
                                    <InputError :message="newSectionForm.errors.title" />
                                </div>
                                <div>
                                    <Input
                                        v-model="newSectionForm.description"
                                        placeholder="Deskripsi (opsional)"
                                    />
                                </div>
                                <div class="flex gap-2">
                                    <Button type="submit" size="sm" :disabled="newSectionForm.processing">
                                        <Save class="mr-2 h-4 w-4" />
                                        Simpan
                                    </Button>
                                    <Button type="button" variant="outline" size="sm" @click="showAddSection = false">
                                        Batal
                                    </Button>
                                </div>
                            </form>
                        </div>

                        <!-- Empty State -->
                        <div v-if="localSections.length === 0 && !showAddSection" class="py-12 text-center">
                            <FileText class="mx-auto h-12 w-12 text-muted-foreground" />
                            <h3 class="mt-4 text-lg font-semibold">Belum ada bagian</h3>
                            <p class="mt-2 text-muted-foreground">
                                Mulai dengan menambahkan bagian untuk mengorganisir materi kursus Anda.
                            </p>
                            <Button class="mt-4" @click="showAddSection = true">
                                <Plus class="mr-2 h-4 w-4" />
                                Tambah Bagian Pertama
                            </Button>
                        </div>

                        <!-- Sections List with Drag-Drop -->
                        <draggable
                            v-else
                            v-model="localSections"
                            item-key="id"
                            handle=".section-handle"
                            ghost-class="opacity-50"
                            @end="onSectionDragEnd"
                            class="space-y-3"
                        >
                            <template #item="{ element: section }">
                                <div class="rounded-lg border bg-card">
                                    <!-- Section Header -->
                                    <div class="flex items-center gap-2 p-3">
                                        <button
                                            v-if="isEditable"
                                            class="section-handle cursor-grab p-1 text-muted-foreground hover:text-foreground"
                                        >
                                            <GripVertical class="h-4 w-4" />
                                        </button>

                                        <button
                                            class="flex flex-1 items-center gap-2 text-left"
                                            @click="toggleSection(section.id)"
                                        >
                                            <component
                                                :is="expandedSections.includes(section.id) ? ChevronDown : ChevronRight"
                                                class="h-4 w-4 text-muted-foreground"
                                            />

                                            <!-- Edit Mode -->
                                            <template v-if="editingSectionId === section.id">
                                                <form
                                                    @submit.prevent="submitEditSection(section.id)"
                                                    @click.stop
                                                    class="flex flex-1 items-center gap-2"
                                                >
                                                    <Input
                                                        v-model="editSectionForm.title"
                                                        class="h-8"
                                                        required
                                                        @click.stop
                                                    />
                                                    <Button type="submit" size="sm" variant="ghost">
                                                        <Save class="h-4 w-4" />
                                                    </Button>
                                                    <Button type="button" size="sm" variant="ghost" @click.stop="cancelEditSection">
                                                        <X class="h-4 w-4" />
                                                    </Button>
                                                </form>
                                            </template>

                                            <!-- Display Mode -->
                                            <template v-else>
                                                <div class="flex-1">
                                                    <span class="font-medium">{{ section.title }}</span>
                                                    <span class="ml-2 text-sm text-muted-foreground">
                                                        {{ section.lessons.length }} materi
                                                        <span class="mx-1">|</span>
                                                        {{ formatDuration(sectionDuration(section)) }}
                                                    </span>
                                                </div>
                                            </template>
                                        </button>

                                        <!-- Section Actions -->
                                        <div v-if="isEditable && editingSectionId !== section.id" class="flex gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8"
                                                @click.stop="startEditSection(section)"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 text-destructive hover:text-destructive"
                                                @click.stop="deleteSection(section)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>

                                    <!-- Lessons List -->
                                    <div
                                        v-if="expandedSections.includes(section.id)"
                                        class="border-t bg-muted/20"
                                    >
                                        <draggable
                                            v-model="section.lessons"
                                            item-key="id"
                                            handle=".lesson-handle"
                                            ghost-class="opacity-50"
                                            @end="() => onLessonDragEnd(section.id)"
                                            class="divide-y"
                                        >
                                            <template #item="{ element: lesson }">
                                                <div class="flex items-center gap-2 px-3 py-2 hover:bg-muted/50">
                                                    <button
                                                        v-if="isEditable"
                                                        class="lesson-handle cursor-grab p-1 text-muted-foreground hover:text-foreground"
                                                    >
                                                        <GripVertical class="h-3 w-3" />
                                                    </button>
                                                    <component
                                                        :is="contentTypeIcon(lesson.content_type)"
                                                        class="h-4 w-4 text-muted-foreground"
                                                    />
                                                    <div class="flex-1">
                                                        <span class="text-sm">{{ lesson.title }}</span>
                                                        <Badge v-if="lesson.is_free_preview" variant="outline" class="ml-2 text-xs">
                                                            Preview
                                                        </Badge>
                                                    </div>
                                                    <span class="text-xs text-muted-foreground">
                                                        {{ lesson.estimated_duration_minutes }} menit
                                                    </span>
                                                    <div v-if="isEditable" class="flex gap-1">
                                                        <Link :href="editLesson(lesson.id).url">
                                                            <Button variant="ghost" size="icon" class="h-7 w-7">
                                                                <Pencil class="h-3 w-3" />
                                                            </Button>
                                                        </Link>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            class="h-7 w-7 text-destructive hover:text-destructive"
                                                            @click="deleteLesson(lesson)"
                                                        >
                                                            <Trash2 class="h-3 w-3" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </template>
                                        </draggable>

                                        <!-- Add Lesson Button -->
                                        <div v-if="isEditable" class="border-t p-2">
                                            <Link :href="`/sections/${section.id}/lessons/create`">
                                                <Button variant="ghost" size="sm" class="w-full justify-start">
                                                    <Plus class="mr-2 h-4 w-4" />
                                                    Tambah Materi
                                                </Button>
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </draggable>
                    </CardContent>
                </Card>
            </div>

            <!-- Info Tab -->
            <div v-if="activeTab === 'info'">
                <Form
                    v-bind="updateCourse.form(course.id)"
                    class="grid gap-6 lg:grid-cols-3"
                    v-slot="{ errors, processing }"
                >
                    <input type="hidden" name="_method" value="PUT" />

                    <div class="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Informasi Dasar</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-2">
                                    <Label for="title">Judul Kursus *</Label>
                                    <Input
                                        id="title"
                                        name="title"
                                        :default-value="course.title"
                                        placeholder="Masukkan judul kursus"
                                        required
                                        :disabled="!isEditable"
                                    />
                                    <InputError :message="errors.title" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="short_description">Deskripsi Singkat *</Label>
                                    <textarea
                                        id="short_description"
                                        name="short_description"
                                        class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                        placeholder="Deskripsi singkat tentang kursus"
                                        required
                                        :disabled="!isEditable"
                                    >{{ course.short_description }}</textarea>
                                    <InputError :message="errors.short_description" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="long_description">Deskripsi Lengkap</Label>
                                    <textarea
                                        id="long_description"
                                        name="long_description"
                                        class="flex min-h-32 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                        placeholder="Deskripsi lengkap tentang kursus"
                                        :disabled="!isEditable"
                                    >{{ course.long_description }}</textarea>
                                    <InputError :message="errors.long_description" />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Tujuan Pembelajaran</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div v-for="(objective, idx) in objectives" :key="idx" class="flex gap-2">
                                    <Input
                                        v-model="objectives[idx]"
                                        :name="`objectives[${idx}]`"
                                        :placeholder="`Tujuan ${idx + 1}`"
                                        :disabled="!isEditable"
                                    />
                                    <Button
                                        v-if="isEditable"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        @click="removeObjective(idx)"
                                        :disabled="objectives.length === 1"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>
                                <Button v-if="isEditable" type="button" variant="outline" size="sm" @click="addObjective">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Tambah Tujuan
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Prasyarat</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div v-for="(prereq, idx) in prerequisites" :key="idx" class="flex gap-2">
                                    <Input
                                        v-model="prerequisites[idx]"
                                        :name="`prerequisites[${idx}]`"
                                        :placeholder="`Prasyarat ${idx + 1}`"
                                        :disabled="!isEditable"
                                    />
                                    <Button
                                        v-if="isEditable"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        @click="removePrerequisite(idx)"
                                        :disabled="prerequisites.length === 1"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>
                                <Button v-if="isEditable" type="button" variant="outline" size="sm" @click="addPrerequisite">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Tambah Prasyarat
                                </Button>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Pengaturan</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-2">
                                    <Label for="category_id">Kategori</Label>
                                    <select
                                        id="category_id"
                                        name="category_id"
                                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="!isEditable"
                                    >
                                        <option value="">Pilih Kategori</option>
                                        <option
                                            v-for="category in categories"
                                            :key="category.id"
                                            :value="category.id"
                                            :selected="category.id === course.category_id"
                                        >
                                            {{ category.name }}
                                        </option>
                                    </select>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="difficulty_level">Tingkat Kesulitan *</Label>
                                    <select
                                        id="difficulty_level"
                                        name="difficulty_level"
                                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                        required
                                        :disabled="!isEditable"
                                    >
                                        <option value="beginner" :selected="course.difficulty_level === 'beginner'">Pemula</option>
                                        <option value="intermediate" :selected="course.difficulty_level === 'intermediate'">Menengah</option>
                                        <option value="advanced" :selected="course.difficulty_level === 'advanced'">Lanjutan</option>
                                    </select>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="visibility">Visibilitas *</Label>
                                    <select
                                        id="visibility"
                                        name="visibility"
                                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                        required
                                        :disabled="!isEditable"
                                    >
                                        <option value="public" :selected="course.visibility === 'public'">Publik</option>
                                        <option value="restricted" :selected="course.visibility === 'restricted'">Terbatas</option>
                                        <option value="hidden" :selected="course.visibility === 'hidden'">Tersembunyi</option>
                                    </select>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="manual_duration_minutes">Durasi Manual (menit)</Label>
                                    <Input
                                        id="manual_duration_minutes"
                                        name="manual_duration_minutes"
                                        type="number"
                                        min="0"
                                        :default-value="course.manual_duration_minutes ?? undefined"
                                        placeholder="Kosongkan untuk hitung otomatis"
                                        :disabled="!isEditable"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Durasi terhitung: {{ formatDuration(course.estimated_duration_minutes) }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Tag</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="flex flex-wrap gap-2">
                                    <label
                                        v-for="tag in tags"
                                        :key="tag.id"
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm transition-colors"
                                        :class="[
                                            selectedTagIds.includes(tag.id) ? 'border-primary bg-primary/10' : '',
                                            !isEditable ? 'cursor-not-allowed opacity-50' : ''
                                        ]"
                                        @click="isEditable && toggleTag(tag.id)"
                                    >
                                        <input
                                            type="checkbox"
                                            name="tag_ids[]"
                                            :value="tag.id"
                                            :checked="selectedTagIds.includes(tag.id)"
                                            :disabled="!isEditable"
                                            class="sr-only"
                                        />
                                        {{ tag.name }}
                                    </label>
                                </div>
                            </CardContent>
                        </Card>

                        <div v-if="isEditable" class="flex gap-2">
                            <Link :href="show(course.id).url" class="flex-1">
                                <Button type="button" variant="outline" class="w-full">
                                    Batal
                                </Button>
                            </Link>
                            <Button type="submit" class="flex-1" :disabled="processing">
                                {{ processing ? 'Menyimpan...' : 'Simpan' }}
                            </Button>
                        </div>
                    </div>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
