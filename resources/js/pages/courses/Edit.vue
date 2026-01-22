<script setup lang="ts">
// =============================================================================
// Course Edit Page
// Uses CourseOutline and CourseInfoForm components for tab content
// =============================================================================

import { index, show } from '@/actions/App/Http/Controllers/CourseController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import CourseInfoForm from '@/components/courses/CourseInfoForm.vue';
import CourseOutline from '@/components/courses/CourseOutline.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    BreadcrumbItem,
    Category,
    Tag,
    ContentType,
    CourseStatus,
    CourseVisibility,
    DifficultyLevel,
} from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft } from 'lucide-vue-next';
import { ref, computed } from 'vue';

// =============================================================================
// Page-Specific Types
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

interface EditableCourse {
    id: number;
    title: string;
    short_description: string;
    long_description: string | null;
    objectives: string[];
    prerequisites: string[];
    category_id: number | null;
    difficulty_level: DifficultyLevel;
    visibility: CourseVisibility;
    status: CourseStatus;
    manual_duration_minutes: number | null;
    estimated_duration_minutes: number;
    tags: Tag[];
    sections: CurriculumSection[];
}

interface Props {
    course: EditableCourse;
    categories: Category[];
    tags: Tag[];
    /** Number of active enrollments for warning display */
    activeEnrollmentsCount: number;
    can: {
        publish: boolean;
        setStatus: boolean;
        setVisibility: boolean;
        delete: boolean;
    };
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Courses', href: index().url },
    { title: props.course.title, href: show(props.course.id).url },
    { title: 'Edit', href: '#' },
];

// =============================================================================
// State
// =============================================================================

const activeTab = ref<'info' | 'outline'>('outline');

// =============================================================================
// Computed
// =============================================================================

/**
 * Course is now always editable if user has permission to access this page.
 * Content managers can edit their own published courses.
 */
const isEditable = computed(() => true);

const hasActiveEnrollments = computed(() => props.activeEnrollmentsCount > 0);

const statusLabel = computed(() => {
    switch (props.course.status) {
        case 'published': return 'Terbit';
        case 'draft': return 'Draft';
        case 'archived': return 'Arsip';
        default: return props.course.status;
    }
});
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
                                {{ statusLabel }}
                            </Badge>
                        </div>
                        <p class="text-muted-foreground">{{ course.title }}</p>
                    </div>
                </div>
            </div>

            <!-- Active Enrollments Warning -->
            <Alert
                v-if="hasActiveEnrollments && course.status === 'published'"
                variant="destructive"
                class="border-yellow-500 bg-yellow-50 dark:bg-yellow-950"
            >
                <AlertTriangle class="h-4 w-4 text-yellow-600 dark:text-yellow-400" />
                <AlertTitle class="text-yellow-800 dark:text-yellow-200">
                    Perhatian: {{ activeEnrollmentsCount }} Peserta Aktif
                </AlertTitle>
                <AlertDescription class="text-yellow-700 dark:text-yellow-300">
                    Kursus ini memiliki {{ activeEnrollmentsCount }} peserta yang sedang aktif belajar.
                    Perubahan pada konten akan langsung terlihat oleh mereka.
                    Hindari menghapus materi yang sedang dipelajari.
                </AlertDescription>
            </Alert>

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
            <CourseOutline
                v-if="activeTab === 'outline'"
                :course-id="course.id"
                :sections="course.sections"
                :estimated-duration-minutes="course.estimated_duration_minutes"
                :editable="isEditable"
            />

            <!-- Info Tab -->
            <CourseInfoForm
                v-if="activeTab === 'info'"
                :course="course"
                :categories="categories"
                :tags="tags"
                :cancel-url="show(course.id).url"
                :editable="isEditable"
            />
        </div>
    </AppLayout>
</template>
