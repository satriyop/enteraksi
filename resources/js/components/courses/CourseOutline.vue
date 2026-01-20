<script setup lang="ts">
// =============================================================================
// CourseOutline Component
// Displays and manages course curriculum with drag-drop reordering
// =============================================================================

import { ref, computed, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { store as storeSection, update as updateSection, destroy as destroySection } from '@/actions/App/Http/Controllers/CourseSectionController';
import { edit as editLesson, destroy as destroyLesson } from '@/actions/App/Http/Controllers/LessonController';
import { sections as reorderSections, lessons as reorderLessons } from '@/actions/App/Http/Controllers/CourseReorderController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import InputError from '@/components/InputError.vue';
import draggable from 'vuedraggable';
import { formatDuration, getContentTypeIcon } from '@/lib/utils';
import {
    Plus,
    X,
    GripVertical,
    ChevronDown,
    ChevronRight,
    Pencil,
    Trash2,
    FileText,
    Clock,
    Save,
    Loader2,
} from 'lucide-vue-next';
import type { ContentType } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface CurriculumLesson {
    id: number;
    title: string;
    description: string | null;
    order: number;
    content_type: ContentType;
    estimated_duration_minutes: number;
    is_free_preview: boolean;
}

interface CurriculumSection {
    id: number;
    title: string;
    description: string | null;
    order: number;
    lessons: CurriculumLesson[];
}

interface Props {
    /** Course ID */
    courseId: number;
    /** Sections with lessons */
    sections: CurriculumSection[];
    /** Estimated duration in minutes */
    estimatedDurationMinutes: number;
    /** Whether content can be edited */
    editable?: boolean;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    editable: true,
});

// =============================================================================
// State
// =============================================================================

const expandedSections = ref<number[]>(props.sections.map(s => s.id));
const editingSectionId = ref<number | null>(null);
const showAddSection = ref(false);
const processingDuration = ref(false);

// Local sections state for drag-drop
const localSections = ref<CurriculumSection[]>(JSON.parse(JSON.stringify(props.sections)));

// Watch for props changes
watch(() => props.sections, (newSections) => {
    localSections.value = JSON.parse(JSON.stringify(newSections));
    newSections.forEach(s => {
        if (!expandedSections.value.includes(s.id)) {
            expandedSections.value.push(s.id);
        }
    });
}, { deep: true });

// Forms
const newSectionForm = useForm({
    title: '',
    description: '',
});

const editSectionForm = useForm({
    title: '',
    description: '',
});

// =============================================================================
// Computed
// =============================================================================

const totalLessons = computed(() =>
    localSections.value.reduce((acc, s) => acc + s.lessons.length, 0)
);

// =============================================================================
// Methods
// =============================================================================

const toggleSection = (sectionId: number) => {
    const idx = expandedSections.value.indexOf(sectionId);
    if (idx === -1) {
        expandedSections.value.push(sectionId);
    } else {
        expandedSections.value.splice(idx, 1);
    }
};

const sectionDuration = (section: CurriculumSection) => {
    return section.lessons.reduce((acc, l) => acc + (l.estimated_duration_minutes || 0), 0);
};

const contentTypeIcon = (type: string) => {
    return getContentTypeIcon(type as ContentType);
};

// Section CRUD
const submitNewSection = () => {
    newSectionForm.post(storeSection(props.courseId).url, {
        preserveScroll: true,
        onSuccess: () => {
            newSectionForm.reset();
            showAddSection.value = false;
        },
    });
};

const startEditSection = (section: CurriculumSection) => {
    editingSectionId.value = section.id;
    editSectionForm.title = section.title;
    editSectionForm.description = section.description || '';
};

const cancelEditSection = () => {
    editingSectionId.value = null;
    editSectionForm.reset();
};

const submitEditSection = (sectionId: number) => {
    editSectionForm.patch(updateSection(sectionId).url, {
        preserveScroll: true,
        onSuccess: () => {
            editingSectionId.value = null;
            editSectionForm.reset();
        },
    });
};

const deleteSection = (section: CurriculumSection) => {
    if (confirm(`Apakah Anda yakin ingin menghapus bagian "${section.title}"? Semua materi di dalamnya akan ikut terhapus.`)) {
        router.delete(destroySection(section.id).url, { preserveScroll: true });
    }
};

const deleteLesson = (lesson: CurriculumLesson) => {
    if (confirm(`Apakah Anda yakin ingin menghapus materi "${lesson.title}"?`)) {
        router.delete(destroyLesson(lesson.id).url, { preserveScroll: true });
    }
};

// Drag-drop reordering
const getCsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

const onSectionDragEnd = () => {
    const sectionIds = localSections.value.map(s => s.id);
    fetch(reorderSections(props.courseId).url, {
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

const recalculateDuration = () => {
    if (!props.editable) return;

    processingDuration.value = true;

    fetch(`/courses/${props.courseId}/recalculate-duration`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
    })
    .then(response => {
        if (response.ok) {
            router.reload({ preserveScroll: true });
        } else {
            throw new Error('Failed to recalculate duration');
        }
    })
    .catch(error => {
        console.error('Error recalculating duration:', error);
        alert('Gagal memperbarui durasi. Silakan coba lagi.');
    })
    .finally(() => {
        processingDuration.value = false;
    });
};
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle>Outline Kursus</CardTitle>
                    <CardDescription>
                        {{ localSections.length }} bagian, {{ totalLessons }} materi
                        <span class="mx-2">|</span>
                        <Clock class="inline h-3 w-3" /> {{ formatDuration(estimatedDurationMinutes, 'long') }}
                    </CardDescription>
                </div>
                <Button
                    v-if="editable"
                    @click="showAddSection = true"
                    :disabled="showAddSection"
                >
                    <Plus class="mr-2 h-4 w-4" />
                    Tambah Bagian
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <!-- Duration Re-estimation Button -->
            <div v-if="editable" class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Clock class="h-4 w-4 text-muted-foreground" />
                    <span class="text-sm text-muted-foreground">
                        Durasi terhitung: {{ formatDuration(estimatedDurationMinutes, 'long') }}
                    </span>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    @click="recalculateDuration"
                    :disabled="processingDuration"
                >
                    <Loader2 v-if="processingDuration" class="mr-2 h-4 w-4 animate-spin" />
                    <span>{{ processingDuration ? 'Memperbarui...' : 'Perbarui Durasi' }}</span>
                </Button>
            </div>

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
                                v-if="editable"
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
                                            {{ formatDuration(sectionDuration(section), 'short') }}
                                        </span>
                                    </div>
                                </template>
                            </button>

                            <!-- Section Actions -->
                            <div v-if="editable && editingSectionId !== section.id" class="flex gap-1">
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
                                            v-if="editable"
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
                                        <div v-if="editable" class="flex gap-1">
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
                            <div v-if="editable" class="border-t p-2">
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
</template>
