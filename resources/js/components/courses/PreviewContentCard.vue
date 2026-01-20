<script setup lang="ts">
// =============================================================================
// PreviewContentCard Component
// Renders lesson content for preview mode (no progress tracking)
// =============================================================================

import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    PlayCircle,
    FileText,
    Headphones,
    FileDown,
    BookOpen,
    Users,
    Download,
    Play,
    Pause,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import type { ContentType, Media } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface Props {
    contentType: ContentType;
    richContent: { content?: string } | null;
    youtubeVideoId: string | null;
    media: Media[];
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

// =============================================================================
// State
// =============================================================================

const isAudioPlaying = ref(false);
const audioPlayer = ref<HTMLAudioElement | null>(null);

// =============================================================================
// Computed
// =============================================================================

const youtubeEmbedUrl = computed(() => {
    if (!props.youtubeVideoId) return null;
    return `https://www.youtube.com/embed/${props.youtubeVideoId}`;
});

const getMediaByCollection = (collection: string): Media | null => {
    return props.media?.find(m => m.collection_name === collection) ?? null;
};

const videoMedia = computed(() => getMediaByCollection('video'));
const audioMedia = computed(() => getMediaByCollection('audio'));
const documentMedia = computed(() => getMediaByCollection('document'));

// =============================================================================
// Methods
// =============================================================================

const toggleAudioPlay = () => {
    if (audioPlayer.value) {
        if (isAudioPlaying.value) {
            audioPlayer.value.pause();
        } else {
            audioPlayer.value.play();
        }
        isAudioPlaying.value = !isAudioPlaying.value;
    }
};

const handleAudioEnded = () => {
    isAudioPlaying.value = false;
};

const getDocumentType = (mimeType: string): string => {
    if (mimeType === 'application/pdf') return 'PDF';
    if (mimeType.includes('word')) return 'Word';
    if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'PowerPoint';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'Excel';
    return 'Dokumen';
};
</script>

<template>
    <Card class="mb-6">
        <CardContent class="p-6">
            <!-- YouTube Content -->
            <div v-if="contentType === 'youtube' && youtubeEmbedUrl" class="aspect-video w-full rounded-lg overflow-hidden bg-black">
                <iframe
                    :src="youtubeEmbedUrl"
                    class="h-full w-full"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                />
            </div>

            <!-- Text Content -->
            <div v-else-if="contentType === 'text' && richContent?.content" class="prose prose-sm dark:prose-invert max-w-none">
                <div v-html="richContent.content" />
            </div>

            <!-- Video Content (Uploaded) -->
            <div v-else-if="contentType === 'video' && videoMedia" class="space-y-4">
                <div class="aspect-video w-full rounded-lg overflow-hidden bg-black">
                    <video
                        :src="videoMedia.url"
                        class="w-full h-full"
                        controls
                        controlsList="nodownload"
                    />
                </div>
                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    <span>{{ videoMedia.file_name }}</span>
                    <span v-if="videoMedia.duration_formatted">{{ videoMedia.duration_formatted }}</span>
                </div>
            </div>

            <!-- Video Placeholder -->
            <div v-else-if="contentType === 'video'" class="aspect-video w-full rounded-lg bg-muted flex items-center justify-center">
                <div class="text-center text-muted-foreground">
                    <PlayCircle class="h-16 w-16 mx-auto mb-2" />
                    <p>Video belum tersedia</p>
                </div>
            </div>

            <!-- Audio Content (Uploaded) -->
            <div v-else-if="contentType === 'audio' && audioMedia" class="space-y-4">
                <div class="rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 p-8">
                    <div class="flex flex-col items-center gap-6">
                        <div class="relative">
                            <div class="flex h-24 w-24 items-center justify-center rounded-full bg-primary shadow-lg">
                                <button
                                    type="button"
                                    class="flex h-full w-full items-center justify-center rounded-full text-primary-foreground hover:scale-105 transition-transform"
                                    @click="toggleAudioPlay"
                                >
                                    <Pause v-if="isAudioPlaying" class="h-10 w-10" />
                                    <Play v-else class="h-10 w-10 ml-1" />
                                </button>
                            </div>
                            <div v-if="isAudioPlaying" class="absolute inset-0 rounded-full border-4 border-primary/30 animate-ping" />
                        </div>
                        <div class="text-center">
                            <p class="font-medium">{{ audioMedia.file_name }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ audioMedia.duration_formatted || audioMedia.human_readable_size }}
                            </p>
                        </div>
                    </div>
                </div>
                <audio
                    ref="audioPlayer"
                    :src="audioMedia.url"
                    class="w-full"
                    controls
                    @ended="handleAudioEnded"
                />
            </div>

            <!-- Audio Placeholder -->
            <div v-else-if="contentType === 'audio'" class="p-8 rounded-lg bg-muted flex items-center justify-center">
                <div class="text-center text-muted-foreground">
                    <Headphones class="h-16 w-16 mx-auto mb-2" />
                    <p>Audio belum tersedia</p>
                </div>
            </div>

            <!-- Document Content (Uploaded) -->
            <div v-else-if="contentType === 'document' && documentMedia" class="space-y-4">
                <!-- PDF Viewer -->
                <div v-if="documentMedia.mime_type === 'application/pdf'" class="rounded-lg overflow-hidden border">
                    <iframe
                        :src="documentMedia.url"
                        class="w-full h-[600px]"
                        frameborder="0"
                    />
                </div>

                <!-- Other Documents -->
                <div v-else class="rounded-xl border bg-muted/20 p-8">
                    <div class="flex flex-col items-center gap-4 text-center">
                        <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <FileDown class="h-10 w-10" />
                        </div>
                        <div>
                            <p class="font-medium text-lg">{{ documentMedia.file_name }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ getDocumentType(documentMedia.mime_type) }} • {{ documentMedia.human_readable_size }}
                            </p>
                        </div>
                        <a :href="documentMedia.url" target="_blank" class="inline-flex items-center gap-2">
                            <Button variant="outline" class="gap-2">
                                <Download class="h-4 w-4" />
                                Buka Dokumen
                            </Button>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Document Placeholder -->
            <div v-else-if="contentType === 'document'" class="p-8 rounded-lg bg-muted flex items-center justify-center">
                <div class="text-center text-muted-foreground">
                    <FileDown class="h-16 w-16 mx-auto mb-2" />
                    <p>Dokumen belum tersedia</p>
                </div>
            </div>

            <!-- Conference -->
            <div v-else-if="contentType === 'conference'" class="p-8 rounded-lg bg-muted flex items-center justify-center">
                <div class="text-center text-muted-foreground">
                    <Users class="h-16 w-16 mx-auto mb-2" />
                    <p>Informasi konferensi akan ditampilkan setelah Anda terdaftar</p>
                </div>
            </div>

            <!-- Fallback -->
            <div v-else class="p-8 rounded-lg bg-muted flex items-center justify-center">
                <div class="text-center text-muted-foreground">
                    <BookOpen class="h-16 w-16 mx-auto mb-2" />
                    <p>Konten tidak tersedia untuk preview</p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
