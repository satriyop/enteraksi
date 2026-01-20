<script setup lang="ts">
// =============================================================================
// LessonContentRouter Component
// Routes to appropriate content renderer based on lesson content type
// =============================================================================

import { computed } from 'vue';
import type { ContentType, Media, LessonProgress } from '@/types';
import PaginatedTextContent from './PaginatedTextContent.vue';
import PaginatedPDFContent from './PaginatedPDFContent.vue';
import YouTubePlayer from './YouTubePlayer.vue';
import VideoContent from './content/VideoContent.vue';
import AudioContent from './content/AudioContent.vue';
import DocumentPreview from './content/DocumentPreview.vue';
import ContentPlaceholder from './content/ContentPlaceholder.vue';
import {
    PlayCircle,
    FileText,
    Headphones,
    FileDown,
    Video as VideoCall,
    BookOpen,
} from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface Props {
    /** Content type of the lesson */
    contentType: ContentType;
    /** Course ID for progress tracking */
    courseId: number;
    /** Lesson ID for progress tracking */
    lessonId: number;
    /** Rich text content (HTML) */
    richContentHtml?: string | null;
    /** YouTube video ID */
    youtubeVideoId?: string | null;
    /** Media collection */
    media?: Media[];
    /** Existing lesson progress */
    lessonProgress?: LessonProgress | null;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

const emit = defineEmits<{
    /** Emitted when page changes (for text/document content) */
    pageChange: [page: number, total: number];
    /** Emitted when pagination is ready (for text content) */
    paginationReady: [totalPages: number, metadata: Record<string, unknown>];
    /** Emitted when document is loaded (for PDF) */
    documentLoaded: [totalPages: number];
    /** Emitted when media time updates */
    mediaTimeUpdate: [currentTime: number, duration: number];
    /** Emitted when media is paused */
    mediaPause: [];
}>();

// =============================================================================
// Computed Properties
// =============================================================================

/** Get media by collection name */
const getMediaByCollection = (collection: string): Media | null => {
    return props.media?.find(m => m.collection_name === collection) ?? null;
};

const videoMedia = computed(() => getMediaByCollection('video'));
const audioMedia = computed(() => getMediaByCollection('audio'));
const documentMedia = computed(() => getMediaByCollection('document'));

/** Whether content needs dark background (video/youtube) */
const needsDarkBackground = computed(() => {
    return ['video', 'youtube'].includes(props.contentType);
});

/** Icon for content type placeholder */
const placeholderIcon = computed(() => {
    const icons = {
        video: PlayCircle,
        youtube: PlayCircle,
        audio: Headphones,
        document: FileDown,
        text: FileText,
        conference: VideoCall,
    };
    return icons[props.contentType] || BookOpen;
});

/** Placeholder message for unavailable content */
const placeholderMessage = computed(() => {
    const messages: Record<string, string> = {
        video: 'Video belum tersedia',
        youtube: 'Video YouTube belum tersedia',
        audio: 'Audio belum tersedia',
        document: 'Dokumen belum tersedia',
        text: 'Konten teks belum tersedia',
        conference: 'Informasi konferensi akan segera tersedia',
    };
    return messages[props.contentType] || 'Konten belum tersedia';
});

// =============================================================================
// Event Handlers
// =============================================================================

const handlePageChange = (page: number, total: number) => {
    emit('pageChange', page, total);
};

const handlePaginationReady = (totalPages: number, metadata: Record<string, unknown>) => {
    emit('paginationReady', totalPages, metadata);
};

const handleDocumentLoaded = (totalPages: number) => {
    emit('documentLoaded', totalPages);
};

const handleMediaTimeUpdate = (currentTime: number, duration: number) => {
    emit('mediaTimeUpdate', currentTime, duration);
};

const handleMediaPause = () => {
    emit('mediaPause');
};
</script>

<template>
    <div
        class="lesson-content-router shrink-0 flex items-center justify-center"
        :class="needsDarkBackground ? 'bg-black' : 'bg-muted/30'"
    >
        <!-- YouTube Content -->
        <div v-if="contentType === 'youtube' && youtubeVideoId" class="w-full aspect-video max-h-[70vh]">
            <YouTubePlayer
                :video-id="youtubeVideoId"
                :initial-position="lessonProgress?.media_position_seconds ?? 0"
                @timeupdate="handleMediaTimeUpdate"
                @pause="handleMediaPause"
            />
        </div>

        <!-- Video Content (Uploaded) -->
        <div v-else-if="contentType === 'video' && videoMedia" class="w-full aspect-video max-h-[70vh]">
            <VideoContent
                :media="videoMedia"
                :initial-position="lessonProgress?.media_position_seconds ?? 0"
                @timeupdate="handleMediaTimeUpdate"
                @pause="handleMediaPause"
            />
        </div>

        <!-- Video Placeholder -->
        <div v-else-if="contentType === 'video'" class="w-full aspect-video max-h-[70vh]">
            <ContentPlaceholder
                :icon="PlayCircle"
                message="Video belum tersedia"
            />
        </div>

        <!-- Audio Content -->
        <div v-else-if="contentType === 'audio'" class="w-full p-8 max-w-2xl mx-auto">
            <AudioContent
                v-if="audioMedia"
                :media="audioMedia"
                :initial-position="lessonProgress?.media_position_seconds ?? 0"
                @timeupdate="handleMediaTimeUpdate"
                @pause="handleMediaPause"
            />
            <ContentPlaceholder v-else :icon="Headphones" message="Audio belum tersedia" />
        </div>

        <!-- Document Content (PDF with pagination) -->
        <div v-else-if="contentType === 'document'" class="w-full p-6 max-w-4xl mx-auto">
            <template v-if="documentMedia">
                <!-- PDF Viewer with pagination -->
                <PaginatedPDFContent
                    v-if="documentMedia.mime_type === 'application/pdf'"
                    :pdf-url="documentMedia.url"
                    :initial-page="lessonProgress?.current_page ?? 1"
                    :course-id="courseId"
                    :lesson-id="lessonId"
                    @page-change="handlePageChange"
                    @document-loaded="handleDocumentLoaded"
                />
                <!-- Other Documents (non-PDF) -->
                <DocumentPreview
                    v-else
                    :media="documentMedia"
                />
            </template>
            <ContentPlaceholder v-else :icon="FileDown" message="Dokumen belum tersedia" />
        </div>

        <!-- Text Content (with pagination) -->
        <div v-else-if="contentType === 'text'" class="w-full p-6 max-w-4xl mx-auto">
            <PaginatedTextContent
                v-if="richContentHtml"
                :content="richContentHtml"
                :initial-page="lessonProgress?.current_page ?? 1"
                :saved-metadata="lessonProgress?.pagination_metadata ?? null"
                :course-id="courseId"
                :lesson-id="lessonId"
                @page-change="handlePageChange"
                @pagination-ready="handlePaginationReady"
            />
            <ContentPlaceholder v-else :icon="FileText" message="Konten teks belum tersedia" />
        </div>

        <!-- Conference -->
        <div v-else-if="contentType === 'conference'" class="w-full p-8 max-w-2xl mx-auto">
            <ContentPlaceholder
                :icon="VideoCall"
                message="Informasi konferensi akan segera tersedia"
            />
        </div>

        <!-- Fallback -->
        <div v-else class="w-full p-8">
            <ContentPlaceholder
                :icon="BookOpen"
                message="Konten belum tersedia"
            />
        </div>
    </div>
</template>
