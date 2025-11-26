<script setup lang="ts">
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import {
    Upload,
    X,
    FileIcon,
    Video,
    Mic,
    CheckCircle,
    AlertCircle,
    Trash2,
    Play,
    Pause,
} from 'lucide-vue-next';

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
}

interface Props {
    mediableType: 'course' | 'lesson';
    mediableId: number;
    collectionName: 'video' | 'audio' | 'document';
    existingMedia?: Media[];
    accept?: string;
    maxSize?: string;
    multiple?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    existingMedia: () => [],
    multiple: false,
});

const emit = defineEmits<{
    (e: 'uploaded', media: Media): void;
    (e: 'deleted', mediaId: number): void;
    (e: 'error', message: string): void;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const isUploading = ref(false);
const uploadProgress = ref(0);
const uploadError = ref<string | null>(null);
const localMedia = ref<Media[]>([...props.existingMedia]);

// Video/Audio player state
const isPlaying = ref(false);
const mediaPlayer = ref<HTMLVideoElement | HTMLAudioElement | null>(null);

const acceptTypes = computed(() => {
    if (props.accept) return props.accept;
    switch (props.collectionName) {
        case 'video':
            return 'video/mp4,video/webm,video/quicktime,video/x-msvideo,video/x-matroska';
        case 'audio':
            return 'audio/mpeg,audio/wav,audio/ogg,audio/mp4,audio/aac';
        case 'document':
            return 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation';
        default:
            return '*/*';
    }
});

const maxSizeText = computed(() => {
    if (props.maxSize) return props.maxSize;
    switch (props.collectionName) {
        case 'video':
            return '512MB';
        case 'audio':
            return '100MB';
        case 'document':
            return '50MB';
        default:
            return '100MB';
    }
});

const formatDescription = computed(() => {
    switch (props.collectionName) {
        case 'video':
            return 'MP4, WebM, MOV, AVI, MKV';
        case 'audio':
            return 'MP3, WAV, OGG, M4A, AAC';
        case 'document':
            return 'PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX';
        default:
            return 'Semua format file';
    }
});

const iconComponent = computed(() => {
    switch (props.collectionName) {
        case 'video':
            return Video;
        case 'audio':
            return Mic;
        default:
            return FileIcon;
    }
});

const getCsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

const handleDragOver = (e: DragEvent) => {
    e.preventDefault();
    isDragging.value = true;
};

const handleDragLeave = (e: DragEvent) => {
    e.preventDefault();
    isDragging.value = false;
};

const handleDrop = (e: DragEvent) => {
    e.preventDefault();
    isDragging.value = false;
    const files = e.dataTransfer?.files;
    if (files && files.length > 0) {
        uploadFile(files[0]);
    }
};

const handleFileSelect = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const files = target.files;
    if (files && files.length > 0) {
        uploadFile(files[0]);
    }
    // Reset input so same file can be selected again
    target.value = '';
};

const uploadFile = async (file: File) => {
    uploadError.value = null;
    isUploading.value = true;
    uploadProgress.value = 0;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('mediable_type', props.mediableType);
    formData.append('mediable_id', props.mediableId.toString());
    formData.append('collection_name', props.collectionName);

    try {
        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                uploadProgress.value = Math.round((e.loaded / e.total) * 100);
            }
        });

        const response = await new Promise<{ media: Media; message: string }>((resolve, reject) => {
            xhr.open('POST', '/media');
            xhr.setRequestHeader('X-XSRF-TOKEN', getCsrfToken());
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(JSON.parse(xhr.responseText));
                } else {
                    try {
                        const error = JSON.parse(xhr.responseText);
                        reject(new Error(error.message || 'Upload gagal'));
                    } catch {
                        reject(new Error('Upload gagal'));
                    }
                }
            };

            xhr.onerror = () => reject(new Error('Koneksi gagal'));
            xhr.send(formData);
        });

        localMedia.value.push(response.media);
        emit('uploaded', response.media);
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Upload gagal';
        uploadError.value = message;
        emit('error', message);
    } finally {
        isUploading.value = false;
        uploadProgress.value = 0;
    }
};

const deleteMedia = async (mediaId: number) => {
    if (!confirm('Apakah Anda yakin ingin menghapus file ini?')) {
        return;
    }

    try {
        const response = await fetch(`/media/${mediaId}`, {
            method: 'DELETE',
            headers: {
                'X-XSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Gagal menghapus file');
        }

        localMedia.value = localMedia.value.filter(m => m.id !== mediaId);
        emit('deleted', mediaId);
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Gagal menghapus file';
        emit('error', message);
    }
};

const togglePlay = () => {
    if (mediaPlayer.value) {
        if (isPlaying.value) {
            mediaPlayer.value.pause();
        } else {
            mediaPlayer.value.play();
        }
        isPlaying.value = !isPlaying.value;
    }
};

const handleMediaEnded = () => {
    isPlaying.value = false;
};

const primaryMedia = computed(() => localMedia.value[0] ?? null);
</script>

<template>
    <div class="space-y-4">
        <!-- Existing Media Preview -->
        <div v-if="primaryMedia" class="space-y-4">
            <!-- Video Preview -->
            <div v-if="primaryMedia.is_video" class="relative rounded-xl overflow-hidden bg-black">
                <video
                    ref="mediaPlayer"
                    :src="primaryMedia.url"
                    class="w-full aspect-video"
                    @ended="handleMediaEnded"
                    controls
                />
            </div>

            <!-- Audio Preview -->
            <div v-else-if="primaryMedia.is_audio" class="rounded-xl bg-muted/30 p-6">
                <div class="flex items-center gap-4">
                    <button
                        type="button"
                        @click="togglePlay"
                        class="flex h-14 w-14 items-center justify-center rounded-full bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
                    >
                        <Pause v-if="isPlaying" class="h-6 w-6" />
                        <Play v-else class="h-6 w-6 ml-1" />
                    </button>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium truncate">{{ primaryMedia.file_name }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ primaryMedia.duration_formatted || primaryMedia.human_readable_size }}
                        </p>
                    </div>
                </div>
                <audio
                    ref="mediaPlayer"
                    :src="primaryMedia.url"
                    class="hidden"
                    @ended="handleMediaEnded"
                />
            </div>

            <!-- Document Preview -->
            <div v-else-if="primaryMedia.is_document" class="rounded-xl border bg-muted/20 p-6">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <FileIcon class="h-7 w-7" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium truncate">{{ primaryMedia.file_name }}</p>
                        <p class="text-sm text-muted-foreground">{{ primaryMedia.human_readable_size }}</p>
                    </div>
                    <a
                        :href="primaryMedia.url"
                        target="_blank"
                        class="text-sm text-primary hover:underline"
                    >
                        Buka
                    </a>
                </div>
            </div>

            <!-- Media Info & Delete -->
            <div class="flex items-center justify-between rounded-lg bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 p-3">
                <div class="flex items-center gap-2 text-green-700 dark:text-green-300">
                    <CheckCircle class="h-4 w-4" />
                    <span class="text-sm">File berhasil diunggah</span>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:text-destructive hover:bg-destructive/10"
                    @click="deleteMedia(primaryMedia.id)"
                >
                    <Trash2 class="h-4 w-4 mr-1" />
                    Hapus
                </Button>
            </div>
        </div>

        <!-- Upload Area -->
        <div
            v-else
            class="relative rounded-xl border-2 border-dashed p-10 text-center transition-all"
            :class="{
                'border-primary bg-primary/5': isDragging,
                'border-muted-foreground/25 bg-muted/20 hover:border-primary/50 hover:bg-muted/30': !isDragging && !isUploading,
                'border-primary/50 bg-primary/5': isUploading,
            }"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
        >
            <input
                ref="fileInput"
                type="file"
                :accept="acceptTypes"
                class="hidden"
                @change="handleFileSelect"
            />

            <!-- Uploading State -->
            <div v-if="isUploading" class="space-y-4">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                    <Upload class="h-8 w-8 text-primary animate-pulse" />
                </div>
                <div>
                    <p class="font-semibold">Mengunggah...</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ uploadProgress }}%</p>
                </div>
                <Progress :model-value="uploadProgress" class="h-2 max-w-xs mx-auto" />
            </div>

            <!-- Default State -->
            <div v-else>
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                    <component :is="iconComponent" class="h-8 w-8 text-muted-foreground" />
                </div>
                <h3 class="mt-5 font-semibold">
                    {{ isDragging ? 'Lepaskan file di sini' : 'Upload File' }}
                </h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    Drag and drop file atau klik untuk memilih
                </p>
                <Button
                    type="button"
                    variant="outline"
                    class="mt-5 gap-2"
                    @click="triggerFileInput"
                >
                    <Upload class="h-4 w-4" />
                    Pilih File
                </Button>
                <p class="mt-3 text-xs text-muted-foreground">
                    Format: {{ formatDescription }} (Maks. {{ maxSizeText }})
                </p>
            </div>
        </div>

        <!-- Error Message -->
        <div v-if="uploadError" class="flex items-center gap-2 rounded-lg bg-destructive/10 border border-destructive/20 p-3 text-destructive">
            <AlertCircle class="h-4 w-4 shrink-0" />
            <span class="text-sm">{{ uploadError }}</span>
            <button type="button" @click="uploadError = null" class="ml-auto">
                <X class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>
