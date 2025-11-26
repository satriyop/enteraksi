<script setup lang="ts">
import { edit as editCourse } from '@/actions/App/Http/Controllers/CourseController';
import { store, update } from '@/actions/App/Http/Controllers/LessonController';
import PageHeader from '@/components/crud/PageHeader.vue';
import FormSection from '@/components/crud/FormSection.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import MediaUploader from '@/components/MediaUploader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    FileText,
    PlayCircle,
    Youtube,
    Headphones,
    FileDown,
    Video as VideoCall,
    Clock,
    Eye,
    Save,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';

interface Course {
    id: number;
    title: string;
}

interface Section {
    id: number;
    title: string;
    course: Course;
}

interface Media {
    id: number;
    name: string;
    file_name: string;
    mime_type: string;
    size: number;
    human_readable_size: string;
    url: string;
    duration_seconds?: number | null;
    duration_formatted?: string | null;
    is_video: boolean;
    is_audio: boolean;
    is_document: boolean;
    collection_name: string;
}

interface Lesson {
    id: number;
    title: string;
    description: string | null;
    order: number;
    content_type: 'text' | 'video' | 'youtube' | 'audio' | 'document' | 'conference';
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

const props = defineProps<Props>();

const isEditMode = computed(() => props.lesson !== null);
const pageTitle = computed(() => isEditMode.value ? `Edit: ${props.lesson?.title}` : 'Tambah Materi Baru');

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    { title: 'Courses', href: '/courses' },
    { title: props.section.course.title, href: editCourse(props.section.course.id).url },
    { title: props.section.title, href: editCourse(props.section.course.id).url },
    { title: isEditMode.value ? 'Edit Materi' : 'Tambah Materi', href: '#' },
]);

const form = useForm({
    title: props.lesson?.title ?? '',
    description: props.lesson?.description ?? '',
    content_type: props.lesson?.content_type ?? 'text',
    rich_content: props.lesson?.rich_content ?? null,
    youtube_url: props.lesson?.youtube_url ?? '',
    conference_url: props.lesson?.conference_url ?? '',
    conference_type: props.lesson?.conference_type ?? 'zoom',
    estimated_duration_minutes: props.lesson?.estimated_duration_minutes ?? null,
    is_free_preview: props.lesson?.is_free_preview ?? false,
});

const contentTypes = [
    { value: 'text', label: 'Teks', icon: FileText, description: 'Konten berbasis teks dengan rich editor' },
    { value: 'video', label: 'Video', icon: PlayCircle, description: 'Upload video dari perangkat' },
    { value: 'youtube', label: 'YouTube', icon: Youtube, description: 'Embed video dari YouTube' },
    { value: 'audio', label: 'Audio', icon: Headphones, description: 'Upload file audio' },
    { value: 'document', label: 'Dokumen', icon: FileDown, description: 'Upload PDF atau dokumen lainnya' },
    { value: 'conference', label: 'Konferensi', icon: VideoCall, description: 'Sesi live meeting' },
];

const conferenceTypes = [
    { value: 'zoom', label: 'Zoom' },
    { value: 'google_meet', label: 'Google Meet' },
    { value: 'other', label: 'Lainnya' },
];

// Rich text content state - handle both TipTap JSON and HTML string formats
const getInitialTextContent = () => {
    if (!props.lesson?.rich_content) return '';

    const richContent = props.lesson.rich_content;

    // If it's a TipTap JSON document (from seeder), pass it directly to editor
    if (typeof richContent === 'object' && 'type' in richContent && richContent.type === 'doc') {
        return richContent; // Return JSON for TipTap to parse
    }

    // If it's our custom format with HTML content
    if (typeof richContent === 'object' && 'content' in richContent) {
        return (richContent as { content?: string }).content ?? '';
    }

    return '';
};

const textContent = ref<string | Record<string, unknown>>(getInitialTextContent());

watch(textContent, (newVal) => {
    // Always store as HTML string format for consistency
    form.rich_content = { content: typeof newVal === 'string' ? newVal : '' };
});

// Get existing media by collection
const getMediaByCollection = (collection: string): Media[] => {
    if (!props.lesson?.media) return [];
    return props.lesson.media.filter(m => m.collection_name === collection);
};

const videoMedia = computed(() => getMediaByCollection('video'));
const audioMedia = computed(() => getMediaByCollection('audio'));
const documentMedia = computed(() => getMediaByCollection('document'));

// Handle media upload success - refresh page to get updated lesson data
const handleMediaUploaded = () => {
    // Refresh the page to get updated media and duration
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

const selectedContentType = computed(() =>
    contentTypes.find(t => t.value === form.content_type)
);

const youtubeVideoId = computed(() => {
    if (!form.youtube_url) return null;
    const match = form.youtube_url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
    return match?.[1] ?? null;
});

const youtubeEmbedUrl = computed(() => {
    if (!youtubeVideoId.value) return null;
    return `https://www.youtube.com/embed/${youtubeVideoId.value}`;
});

// Show notice for upload features when lesson is not saved yet
const showSaveFirstNotice = computed(() => {
    return !isEditMode.value && ['video', 'audio', 'document'].includes(form.content_type);
});
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

            <form @submit.prevent="submitForm" class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
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

                    <FormSection title="Tipe Konten" description="Pilih jenis konten untuk materi ini">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <label
                                v-for="type in contentTypes"
                                :key="type.value"
                                class="group relative flex cursor-pointer flex-col items-center gap-3 rounded-xl border-2 p-5 transition-all hover:border-primary/50 hover:bg-muted/30"
                                :class="form.content_type === type.value ? 'border-primary bg-primary/5 shadow-sm' : 'border-transparent bg-muted/20'"
                            >
                                <input
                                    type="radio"
                                    v-model="form.content_type"
                                    :value="type.value"
                                    class="sr-only"
                                />
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-xl transition-colors"
                                    :class="form.content_type === type.value ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary'"
                                >
                                    <component :is="type.icon" class="h-6 w-6" />
                                </div>
                                <span class="text-sm font-semibold">{{ type.label }}</span>
                                <span class="text-center text-xs text-muted-foreground leading-relaxed">{{ type.description }}</span>
                                <div
                                    v-if="form.content_type === type.value"
                                    class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
                                >
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </label>
                        </div>
                        <InputError :message="form.errors.content_type" />
                    </FormSection>

                    <FormSection :title="`Konten ${selectedContentType?.label}`" :description="selectedContentType?.description">
                        <template #default>
                            <!-- Save First Notice -->
                            <div v-if="showSaveFirstNotice" class="rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 p-4">
                                <p class="text-sm text-amber-800 dark:text-amber-200">
                                    Simpan materi terlebih dahulu untuk dapat mengunggah file.
                                </p>
                            </div>

                            <!-- Text Content -->
                            <div v-else-if="form.content_type === 'text'" class="space-y-4">
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium">Isi Materi</Label>
                                    <RichTextEditor
                                        v-model="textContent"
                                        placeholder="Tulis konten materi di sini..."
                                    />
                                    <InputError :message="form.errors.rich_content" />
                                </div>
                            </div>

                            <!-- Video Upload -->
                            <div v-else-if="form.content_type === 'video' && isEditMode && lesson" class="space-y-4">
                                <MediaUploader
                                    mediable-type="lesson"
                                    :mediable-id="lesson.id"
                                    collection-name="video"
                                    :existing-media="videoMedia"
                                    @uploaded="handleMediaUploaded"
                                    @deleted="handleMediaDeleted"
                                    @error="handleMediaError"
                                />
                            </div>

                            <!-- YouTube Embed -->
                            <div v-else-if="form.content_type === 'youtube'" class="space-y-5">
                                <div class="space-y-2">
                                    <Label for="youtube_url" class="text-sm font-medium">URL YouTube</Label>
                                    <Input
                                        id="youtube_url"
                                        v-model="form.youtube_url"
                                        type="url"
                                        placeholder="https://www.youtube.com/watch?v=..."
                                        class="h-11"
                                    />
                                    <InputError :message="form.errors.youtube_url" />
                                    <p class="text-xs text-muted-foreground">
                                        Masukkan URL video YouTube yang valid
                                    </p>
                                </div>

                                <!-- Preview -->
                                <div v-if="form.youtube_url">
                                    <Label class="text-sm font-medium">Preview Video</Label>
                                    <div class="mt-2 aspect-video w-full overflow-hidden rounded-xl bg-muted shadow-sm">
                                        <iframe
                                            v-if="youtubeEmbedUrl"
                                            :src="youtubeEmbedUrl"
                                            class="h-full w-full"
                                            frameborder="0"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                            allowfullscreen
                                        />
                                        <div v-else class="flex h-full flex-col items-center justify-center gap-2 text-muted-foreground">
                                            <Youtube class="h-12 w-12" />
                                            <span class="text-sm">URL tidak valid</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Audio Upload -->
                            <div v-else-if="form.content_type === 'audio' && isEditMode && lesson" class="space-y-4">
                                <MediaUploader
                                    mediable-type="lesson"
                                    :mediable-id="lesson.id"
                                    collection-name="audio"
                                    :existing-media="audioMedia"
                                    @uploaded="handleMediaUploaded"
                                    @deleted="handleMediaDeleted"
                                    @error="handleMediaError"
                                />
                            </div>

                            <!-- Document Upload -->
                            <div v-else-if="form.content_type === 'document' && isEditMode && lesson" class="space-y-4">
                                <MediaUploader
                                    mediable-type="lesson"
                                    :mediable-id="lesson.id"
                                    collection-name="document"
                                    :existing-media="documentMedia"
                                    @uploaded="handleMediaUploaded"
                                    @deleted="handleMediaDeleted"
                                    @error="handleMediaError"
                                />
                            </div>

                            <!-- Conference -->
                            <div v-else-if="form.content_type === 'conference'" class="space-y-5">
                                <div class="space-y-2">
                                    <Label for="conference_type" class="text-sm font-medium">Platform Konferensi</Label>
                                    <select
                                        id="conference_type"
                                        v-model="form.conference_type"
                                        class="flex h-11 w-full rounded-lg border border-input bg-background px-4 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary"
                                    >
                                        <option v-for="ct in conferenceTypes" :key="ct.value" :value="ct.value">
                                            {{ ct.label }}
                                        </option>
                                    </select>
                                    <InputError :message="form.errors.conference_type" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="conference_url" class="text-sm font-medium">URL Meeting</Label>
                                    <Input
                                        id="conference_url"
                                        v-model="form.conference_url"
                                        type="url"
                                        placeholder="https://zoom.us/j/... atau https://meet.google.com/..."
                                        class="h-11"
                                    />
                                    <InputError :message="form.errors.conference_url" />
                                    <p class="text-xs text-muted-foreground">
                                        Link meeting akan ditampilkan kepada peserta pada jadwal yang ditentukan
                                    </p>
                                </div>
                            </div>
                        </template>
                    </FormSection>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <FormSection title="Pengaturan">
                        <div class="space-y-5">
                            <div class="space-y-2">
                                <Label for="estimated_duration_minutes" class="flex items-center gap-2 text-sm font-medium">
                                    <Clock class="h-4 w-4 text-muted-foreground" />
                                    Estimasi Durasi (menit)
                                </Label>
                                <Input
                                    id="estimated_duration_minutes"
                                    v-model.number="form.estimated_duration_minutes"
                                    type="number"
                                    min="1"
                                    placeholder="Contoh: 15"
                                    class="h-11"
                                />
                                <InputError :message="form.errors.estimated_duration_minutes" />
                                <p class="text-xs text-muted-foreground">
                                    Perkiraan waktu untuk menyelesaikan materi
                                </p>
                            </div>

                            <label class="flex cursor-pointer items-start gap-4 rounded-xl border-2 p-4 transition-all hover:border-primary/50 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                <input
                                    id="is_free_preview"
                                    v-model="form.is_free_preview"
                                    type="checkbox"
                                    class="mt-0.5 h-5 w-5 rounded border-input accent-primary"
                                />
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 font-medium">
                                        <Eye class="h-4 w-4 text-primary" />
                                        Preview Gratis
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Materi ini dapat diakses tanpa pendaftaran kursus
                                    </p>
                                </div>
                            </label>
                        </div>
                    </FormSection>

                    <!-- Action Buttons -->
                    <div class="sticky bottom-4 flex gap-3 rounded-xl border bg-card p-4 shadow-lg">
                        <Link :href="editCourse(section.course.id).url" class="flex-1">
                            <Button type="button" variant="outline" class="w-full h-11">
                                Batal
                            </Button>
                        </Link>
                        <Button type="submit" class="flex-1 h-11 gap-2" :disabled="form.processing">
                            <Save class="h-4 w-4" />
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Materi' }}
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
