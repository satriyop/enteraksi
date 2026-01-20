<script setup lang="ts">
// =============================================================================
// LessonContentEditor Component
// Renders appropriate editor based on content type
// =============================================================================

import RichTextEditor from '@/components/RichTextEditor.vue';
import MediaUploader from '@/components/MediaUploader.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Youtube } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { ContentType, Media } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface ConferenceType {
    value: string;
    label: string;
}

interface Props {
    contentType: ContentType;
    lessonId: number | null;
    richContent: Record<string, unknown> | null;
    youtubeUrl: string;
    conferenceUrl: string;
    conferenceType: string;
    existingVideoMedia: Media[];
    existingAudioMedia: Media[];
    existingDocumentMedia: Media[];
    errors: Record<string, string>;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:richContent': [value: Record<string, unknown> | null];
    'update:youtubeUrl': [value: string];
    'update:conferenceUrl': [value: string];
    'update:conferenceType': [value: string];
    mediaUploaded: [];
    mediaDeleted: [];
    mediaError: [message: string];
}>();

// =============================================================================
// Data
// =============================================================================

const conferenceTypes: ConferenceType[] = [
    { value: 'zoom', label: 'Zoom' },
    { value: 'google_meet', label: 'Google Meet' },
    { value: 'other', label: 'Lainnya' },
];

// =============================================================================
// State
// =============================================================================

const getInitialTextContent = () => {
    if (!props.richContent) return '';

    // If it's a TipTap JSON document
    if (typeof props.richContent === 'object' && 'type' in props.richContent && props.richContent.type === 'doc') {
        return props.richContent;
    }

    // If it's our custom format with HTML content
    if (typeof props.richContent === 'object' && 'content' in props.richContent) {
        return (props.richContent as { content?: string }).content ?? '';
    }

    return '';
};

const textContent = ref<string | Record<string, unknown>>(getInitialTextContent());

watch(textContent, (newVal) => {
    emit('update:richContent', { content: typeof newVal === 'string' ? newVal : '' });
});

// =============================================================================
// Computed
// =============================================================================

const isEditMode = computed(() => props.lessonId !== null);

const showSaveFirstNotice = computed(() => {
    return !isEditMode.value && ['video', 'audio', 'document'].includes(props.contentType);
});

const youtubeVideoId = computed(() => {
    if (!props.youtubeUrl) return null;
    const match = props.youtubeUrl.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
    return match?.[1] ?? null;
});

const youtubeEmbedUrl = computed(() => {
    if (!youtubeVideoId.value) return null;
    return `https://www.youtube.com/embed/${youtubeVideoId.value}`;
});
</script>

<template>
    <!-- Save First Notice -->
    <div v-if="showSaveFirstNotice" class="rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 p-4">
        <p class="text-sm text-amber-800 dark:text-amber-200">
            Simpan materi terlebih dahulu untuk dapat mengunggah file.
        </p>
    </div>

    <!-- Text Content -->
    <div v-else-if="contentType === 'text'" class="space-y-4">
        <div class="space-y-2">
            <Label class="text-sm font-medium">Isi Materi</Label>
            <RichTextEditor
                v-model="textContent"
                placeholder="Tulis konten materi di sini..."
            />
            <InputError :message="errors.rich_content" />
        </div>
    </div>

    <!-- Video Upload -->
    <div v-else-if="contentType === 'video' && isEditMode && lessonId" class="space-y-4">
        <MediaUploader
            mediable-type="lesson"
            :mediable-id="lessonId"
            collection-name="video"
            :existing-media="existingVideoMedia"
            @uploaded="emit('mediaUploaded')"
            @deleted="emit('mediaDeleted')"
            @error="emit('mediaError', $event)"
        />
    </div>

    <!-- YouTube Embed -->
    <div v-else-if="contentType === 'youtube'" class="space-y-5">
        <div class="space-y-2">
            <Label for="youtube_url" class="text-sm font-medium">URL YouTube</Label>
            <Input
                id="youtube_url"
                :model-value="youtubeUrl"
                type="url"
                placeholder="https://www.youtube.com/watch?v=..."
                class="h-11"
                @update:model-value="emit('update:youtubeUrl', $event)"
            />
            <InputError :message="errors.youtube_url" />
            <p class="text-xs text-muted-foreground">
                Masukkan URL video YouTube yang valid
            </p>
        </div>

        <!-- Preview -->
        <div v-if="youtubeUrl">
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
    <div v-else-if="contentType === 'audio' && isEditMode && lessonId" class="space-y-4">
        <MediaUploader
            mediable-type="lesson"
            :mediable-id="lessonId"
            collection-name="audio"
            :existing-media="existingAudioMedia"
            @uploaded="emit('mediaUploaded')"
            @deleted="emit('mediaDeleted')"
            @error="emit('mediaError', $event)"
        />
    </div>

    <!-- Document Upload -->
    <div v-else-if="contentType === 'document' && isEditMode && lessonId" class="space-y-4">
        <MediaUploader
            mediable-type="lesson"
            :mediable-id="lessonId"
            collection-name="document"
            :existing-media="existingDocumentMedia"
            @uploaded="emit('mediaUploaded')"
            @deleted="emit('mediaDeleted')"
            @error="emit('mediaError', $event)"
        />
    </div>

    <!-- Conference -->
    <div v-else-if="contentType === 'conference'" class="space-y-5">
        <div class="space-y-2">
            <Label for="conference_type" class="text-sm font-medium">Platform Konferensi</Label>
            <select
                id="conference_type"
                :value="conferenceType"
                class="flex h-11 w-full rounded-lg border border-input bg-background px-4 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary"
                @change="emit('update:conferenceType', ($event.target as HTMLSelectElement).value)"
            >
                <option v-for="ct in conferenceTypes" :key="ct.value" :value="ct.value">
                    {{ ct.label }}
                </option>
            </select>
            <InputError :message="errors.conference_type" />
        </div>

        <div class="space-y-2">
            <Label for="conference_url" class="text-sm font-medium">URL Meeting</Label>
            <Input
                id="conference_url"
                :model-value="conferenceUrl"
                type="url"
                placeholder="https://zoom.us/j/... atau https://meet.google.com/..."
                class="h-11"
                @update:model-value="emit('update:conferenceUrl', $event)"
            />
            <InputError :message="errors.conference_url" />
            <p class="text-xs text-muted-foreground">
                Link meeting akan ditampilkan kepada peserta pada jadwal yang ditentukan
            </p>
        </div>
    </div>
</template>
