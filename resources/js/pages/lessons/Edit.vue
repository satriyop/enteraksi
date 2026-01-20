<script setup lang="ts">
// =============================================================================
// Lesson Edit Page
// Create or edit lesson content with various content types
// =============================================================================

import { edit as editCourse } from '@/actions/App/Http/Controllers/CourseController';
import { store, update } from '@/actions/App/Http/Controllers/LessonController';
import PageHeader from '@/components/crud/PageHeader.vue';
import FormSection from '@/components/crud/FormSection.vue';
import InputError from '@/components/InputError.vue';
import LessonContentTypeSelector from '@/components/lesson/LessonContentTypeSelector.vue';
import LessonContentEditor from '@/components/lesson/LessonContentEditor.vue';
import LessonSettingsSidebar from '@/components/lesson/LessonSettingsSidebar.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type ContentType, type Media } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { contentTypeLabel } from '@/lib/formatters';

// =============================================================================
// Page-Specific Types
// =============================================================================

interface Course {
    id: number;
    title: string;
}

interface Section {
    id: number;
    title: string;
    course: Course;
}

interface Lesson {
    id: number;
    title: string;
    description: string | null;
    order: number;
    content_type: ContentType;
    rich_content: Record<string, unknown> | null;
    youtube_url: string | null;
    conference_url: string | null;
    conference_type: 'zoom' | 'google_meet' | 'other' | null;
    estimated_duration_minutes: number | null;
    is_free_preview: boolean;
    media?: Media[];
}

interface Props {
    section: Section;
    lesson: Lesson | null;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

const isEditMode = computed(() => props.lesson !== null);
const pageTitle = computed(() => isEditMode.value ? `Edit: ${props.lesson?.title}` : 'Tambah Materi Baru');

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    { title: 'Courses', href: '/courses' },
    { title: props.section.course.title, href: editCourse(props.section.course.id).url },
    { title: props.section.title, href: editCourse(props.section.course.id).url },
    { title: isEditMode.value ? 'Edit Materi' : 'Tambah Materi', href: '#' },
]);

// =============================================================================
// Form
// =============================================================================

const form = useForm({
    title: props.lesson?.title ?? '',
    description: props.lesson?.description ?? '',
    content_type: props.lesson?.content_type ?? 'text' as ContentType,
    rich_content: props.lesson?.rich_content ?? null,
    youtube_url: props.lesson?.youtube_url ?? '',
    conference_url: props.lesson?.conference_url ?? '',
    conference_type: props.lesson?.conference_type ?? 'zoom',
    estimated_duration_minutes: props.lesson?.estimated_duration_minutes ?? null,
    is_free_preview: props.lesson?.is_free_preview ?? false,
});

// =============================================================================
// Computed
// =============================================================================

const getMediaByCollection = (collection: string): Media[] => {
    if (!props.lesson?.media) return [];
    return props.lesson.media.filter(m => m.collection_name === collection);
};

const videoMedia = computed(() => getMediaByCollection('video'));
const audioMedia = computed(() => getMediaByCollection('audio'));
const documentMedia = computed(() => getMediaByCollection('document'));

const selectedContentTypeLabel = computed(() => contentTypeLabel(form.content_type));

// =============================================================================
// Methods
// =============================================================================

const handleMediaUploaded = () => {
    router.reload({ only: ['lesson'] });
};

const handleMediaDeleted = () => {
    router.reload({ only: ['lesson'] });
};

const handleMediaError = (message: string) => {
    alert(message);
};

const submitForm = () => {
    if (isEditMode.value && props.lesson) {
        form.patch(update(props.lesson.id).url, {
            preserveScroll: true,
        });
    } else {
        form.post(store(props.section.id).url, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="pageTitle" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                :title="isEditMode ? 'Edit Materi' : 'Tambah Materi Baru'"
                :description="`${section.course.title} / ${section.title}`"
                :back-href="editCourse(section.course.id).url"
                back-label="Kembali ke Kursus"
            />

            <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="submitForm">
                <div class="space-y-6 lg:col-span-2">
                    <!-- Basic Info -->
                    <FormSection title="Informasi Dasar" description="Informasi utama tentang materi pembelajaran">
                        <div class="space-y-5">
                            <div class="space-y-2">
                                <Label for="title" class="text-sm font-medium">
                                    Judul Materi <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="title"
                                    v-model="form.title"
                                    placeholder="Contoh: Pengenalan JavaScript"
                                    class="h-11"
                                    required
                                />
                                <InputError :message="form.errors.title" />
                            </div>

                            <div class="space-y-2">
                                <Label for="description" class="text-sm font-medium">Deskripsi</Label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="3"
                                    class="flex w-full rounded-lg border border-input bg-background px-4 py-3 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary disabled:cursor-not-allowed disabled:opacity-50"
                                    placeholder="Deskripsi singkat tentang materi ini"
                                />
                                <InputError :message="form.errors.description" />
                            </div>
                        </div>
                    </FormSection>

                    <!-- Content Type Selector -->
                    <FormSection title="Tipe Konten" description="Pilih jenis konten untuk materi ini">
                        <LessonContentTypeSelector v-model="form.content_type" />
                        <InputError :message="form.errors.content_type" />
                    </FormSection>

                    <!-- Content Editor -->
                    <FormSection :title="`Konten ${selectedContentTypeLabel}`">
                        <LessonContentEditor
                            :content-type="form.content_type"
                            :lesson-id="lesson?.id ?? null"
                            :rich-content="form.rich_content"
                            :youtube-url="form.youtube_url"
                            :conference-url="form.conference_url"
                            :conference-type="form.conference_type"
                            :existing-video-media="videoMedia"
                            :existing-audio-media="audioMedia"
                            :existing-document-media="documentMedia"
                            :errors="form.errors"
                            @update:rich-content="form.rich_content = $event"
                            @update:youtube-url="form.youtube_url = $event"
                            @update:conference-url="form.conference_url = $event"
                            @update:conference-type="form.conference_type = $event"
                            @media-uploaded="handleMediaUploaded"
                            @media-deleted="handleMediaDeleted"
                            @media-error="handleMediaError"
                        />
                    </FormSection>
                </div>

                <!-- Sidebar -->
                <LessonSettingsSidebar
                    :cancel-href="editCourse(section.course.id).url"
                    :estimated-duration-minutes="form.estimated_duration_minutes"
                    :is-free-preview="form.is_free_preview"
                    :is-processing="form.processing"
                    :errors="form.errors"
                    @update:estimated-duration-minutes="form.estimated_duration_minutes = $event"
                    @update:is-free-preview="form.is_free_preview = $event"
                />
            </form>
        </div>
    </AppLayout>
</template>
