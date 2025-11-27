<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Clock,
    BookOpen,
    ChevronLeft,
    ChevronRight,
    ChevronDown,
    PlayCircle,
    FileText,
    Headphones,
    FileDown,
    Video as VideoCall,
    Youtube,
    ArrowLeft,
    Download,
    Play,
    Pause,
    PanelRight,
    X,
} from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import PaginatedTextContent from '@/components/lesson/PaginatedTextContent.vue';
import PaginatedPDFContent from '@/components/lesson/PaginatedPDFContent.vue';
import YouTubePlayer from '@/components/lesson/YouTubePlayer.vue';
import axios from 'axios';

interface Category {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
}

interface Media {
    id: number;
    name: string;
    file_name: string;
    mime_type: string;
    size: number;
    human_readable_size: string;
    url: string;
    duration_seconds: number | null;
    duration_formatted: string | null;
    is_video: boolean;
    is_audio: boolean;
    is_document: boolean;
    collection_name: string;
}

interface Lesson {
    id: number;
    title: string;
    description: string | null;
    content_type: 'text' | 'video' | 'youtube' | 'audio' | 'document' | 'conference';
    rich_content: Record<string, unknown> | null;
    rich_content_html: string | null;
    youtube_url: string | null;
    youtube_video_id: string | null;
    estimated_duration_minutes: number | null;
    is_free_preview: boolean;
    media: Media[];
}

interface SectionLesson {
    id: number;
    title: string;
    content_type: 'text' | 'video' | 'youtube' | 'audio' | 'document' | 'conference';
    is_free_preview: boolean;
    order: number;
    estimated_duration_minutes: number | null;
    is_completed?: boolean;
}

interface Section {
    id: number;
    title: string;
    order: number;
    lessons: SectionLesson[];
}

interface Course {
    id: number;
    title: string;
    slug: string;
    user: User;
    category: Category | null;
    sections: Section[];
}

interface Enrollment {
    id: number;
    status: string;
    progress_percentage: number;
}

interface LessonProgress {
    id: number;
    current_page: number;
    total_pages: number | null;
    highest_page_reached: number;
    is_completed: boolean;
    pagination_metadata: Record<string, unknown> | null;
    media_position_seconds: number | null;
    media_duration_seconds: number | null;
    media_progress_percentage: number;
}

interface NavigationLesson {
    id: number;
    title: string;
    section_title: string;
    is_completed?: boolean;
}

interface Props {
    course: Course;
    lesson: Lesson;
    enrollment: Enrollment | null;
    lessonProgress: LessonProgress | null;
    prevLesson: NavigationLesson | null;
    nextLesson: NavigationLesson | null;
    allLessons: NavigationLesson[];
}

const props = defineProps<Props>();

const page = usePage();

// UI State
const sidebarOpen = ref(true);
const activeTab = ref<'overview' | 'notes'>('overview');
const isAudioPlaying = ref(false);
const audioPlayer = ref<HTMLAudioElement | null>(null);
const videoPlayer = ref<HTMLVideoElement | null>(null);
const youtubePlayerRef = ref<InstanceType<typeof YouTubePlayer> | null>(null);
const isLessonCompleted = ref(props.lessonProgress?.is_completed ?? false);

// Section expand/collapse state
const expandedSections = ref<Set<number>>(new Set());

// Progress tracking state
const courseProgressPercentage = ref(props.enrollment?.progress_percentage ?? 0);
const isSavingProgress = ref(false);

// Progress tracking methods
const saveProgress = async (page: number, total: number, metadata?: Record<string, unknown>) => {
    if (!props.enrollment || isSavingProgress.value) return;

    isSavingProgress.value = true;

    try {
        const response = await axios.patch(`/courses/${props.course.id}/lessons/${props.lesson.id}/progress`, {
            current_page: page,
            total_pages: total,
            pagination_metadata: metadata ?? null,
        });

        // Update course progress from response
        if (response.data.enrollment?.progress_percentage !== undefined) {
            courseProgressPercentage.value = response.data.enrollment.progress_percentage;
        }
    } catch (error) {
        console.error('Failed to save progress:', error);
    } finally {
        isSavingProgress.value = false;
    }
};

// Debounced save
let saveDebounceTimer: ReturnType<typeof setTimeout> | null = null;
const debouncedSaveProgress = (page: number, total: number, metadata?: Record<string, unknown>) => {
    if (saveDebounceTimer) {
        clearTimeout(saveDebounceTimer);
    }
    saveDebounceTimer = setTimeout(() => {
        saveProgress(page, total, metadata);
    }, 500);
};

// Event handlers for paginated components
const handlePageChange = (page: number, total: number) => {
    debouncedSaveProgress(page, total);
};

const handlePaginationReady = (totalPages: number, metadata: Record<string, unknown>) => {
    // Save initial pagination info
    if (props.lessonProgress?.current_page) {
        saveProgress(props.lessonProgress.current_page, totalPages, metadata);
    } else {
        saveProgress(1, totalPages, metadata);
    }
};

const handleDocumentLoaded = (totalPages: number) => {
    // Save initial PDF info
    if (props.lessonProgress?.current_page) {
        saveProgress(props.lessonProgress.current_page, totalPages);
    } else {
        saveProgress(1, totalPages);
    }
};

// Media progress tracking (video/youtube/audio)
let mediaProgressTimer: ReturnType<typeof setTimeout> | null = null;
const isSavingMediaProgress = ref(false);

const saveMediaProgress = async (positionSeconds: number, durationSeconds: number) => {
    if (!props.enrollment || isSavingMediaProgress.value || isLessonCompleted.value) return;

    isSavingMediaProgress.value = true;

    try {
        const response = await axios.patch(`/courses/${props.course.id}/lessons/${props.lesson.id}/progress/media`, {
            position_seconds: Math.floor(positionSeconds),
            duration_seconds: Math.floor(durationSeconds),
        });

        // Update course progress from response
        if (response.data.enrollment?.progress_percentage !== undefined) {
            courseProgressPercentage.value = response.data.enrollment.progress_percentage;
        }

        // Check if lesson was auto-completed
        if (response.data.progress?.is_completed) {
            isLessonCompleted.value = true;
        }
    } catch (error) {
        console.error('Failed to save media progress:', error);
    } finally {
        isSavingMediaProgress.value = false;
    }
};

// Debounced media progress save (every 5 seconds)
const debouncedSaveMediaProgress = (positionSeconds: number, durationSeconds: number) => {
    if (mediaProgressTimer) {
        clearTimeout(mediaProgressTimer);
    }
    mediaProgressTimer = setTimeout(() => {
        saveMediaProgress(positionSeconds, durationSeconds);
    }, 5000);
};

// YouTube player handlers
const handleYoutubeTimeUpdate = (currentTime: number, duration: number) => {
    debouncedSaveMediaProgress(currentTime, duration);
};

const handleYoutubePause = () => {
    // Save immediately on pause
    if (youtubePlayerRef.value) {
        const currentTime = youtubePlayerRef.value.getCurrentTime();
        const duration = youtubePlayerRef.value.getDuration();
        if (duration > 0) {
            saveMediaProgress(currentTime, duration);
        }
    }
};

// Video player handlers
const handleVideoTimeUpdate = () => {
    if (videoPlayer.value) {
        const currentTime = videoPlayer.value.currentTime;
        const duration = videoPlayer.value.duration;
        if (duration > 0 && !isNaN(duration)) {
            debouncedSaveMediaProgress(currentTime, duration);
        }
    }
};

const handleVideoPause = () => {
    if (videoPlayer.value) {
        const currentTime = videoPlayer.value.currentTime;
        const duration = videoPlayer.value.duration;
        if (duration > 0 && !isNaN(duration)) {
            saveMediaProgress(currentTime, duration);
        }
    }
};

// Audio player handlers
const handleAudioTimeUpdate = () => {
    if (audioPlayer.value) {
        const currentTime = audioPlayer.value.currentTime;
        const duration = audioPlayer.value.duration;
        if (duration > 0 && !isNaN(duration)) {
            debouncedSaveMediaProgress(currentTime, duration);
        }
    }
};

const handleAudioPause = () => {
    if (audioPlayer.value) {
        const currentTime = audioPlayer.value.currentTime;
        const duration = audioPlayer.value.duration;
        if (duration > 0 && !isNaN(duration)) {
            saveMediaProgress(currentTime, duration);
        }
    }
};

onMounted(() => {
    // Auto-close sidebar on mobile
    if (window.innerWidth < 1024) {
        sidebarOpen.value = false;
    }

    // Find and expand the section containing the current lesson
    for (const section of props.course.sections) {
        if (section.lessons.some(l => l.id === props.lesson.id)) {
            expandedSections.value.add(section.id);
            break;
        }
    }
});

onUnmounted(() => {
    if (saveDebounceTimer) {
        clearTimeout(saveDebounceTimer);
    }
    if (mediaProgressTimer) {
        clearTimeout(mediaProgressTimer);
    }
});

const toggleSection = (sectionId: number) => {
    if (expandedSections.value.has(sectionId)) {
        expandedSections.value.delete(sectionId);
    } else {
        expandedSections.value.add(sectionId);
    }
};

const isSectionExpanded = (sectionId: number) => expandedSections.value.has(sectionId);

// Computed
const progressPercentage = computed(() => courseProgressPercentage.value);

const currentLessonIndex = computed(() => {
    return props.allLessons.findIndex(l => l.id === props.lesson.id);
});

// Map lesson IDs to completion status
const lessonCompletionMap = computed(() => {
    const map: Record<number, boolean> = {};
    props.allLessons.forEach(lesson => {
        map[lesson.id] = lesson.is_completed ?? false;
    });
    // Include current lesson's completion status (may have just been completed)
    map[props.lesson.id] = isLessonCompleted.value;
    return map;
});

const lessonTypeIcon = (type: string) => {
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

const lessonTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
        video: 'Video',
        youtube: 'YouTube',
        audio: 'Audio',
        document: 'Dokumen',
        conference: 'Konferensi',
        text: 'Teks',
    };
    return labels[type] || type;
};

const formatDuration = (minutes: number | null) => {
    if (!minutes) return '-';
    if (minutes < 60) return `${minutes} menit`;
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    if (remainingMinutes === 0) return `${hours} jam`;
    return `${hours}j ${remainingMinutes}m`;
};

const formatSectionDuration = (section: Section) => {
    const total = section.lessons.reduce((sum, l) => sum + (l.estimated_duration_minutes || 0), 0);
    return formatDuration(total);
};

// Get media by collection
const getMediaByCollection = (collection: string): Media | null => {
    return props.lesson.media?.find(m => m.collection_name === collection) ?? null;
};

const videoMedia = computed(() => getMediaByCollection('video'));
const audioMedia = computed(() => getMediaByCollection('audio'));
const documentMedia = computed(() => getMediaByCollection('document'));

// Audio player controls
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

// Get document file type for icon/styling
const getDocumentType = (mimeType: string): string => {
    if (mimeType === 'application/pdf') return 'PDF';
    if (mimeType.includes('word')) return 'Word';
    if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'PowerPoint';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'Excel';
    return 'Dokumen';
};

// Check if content needs dark background
const needsDarkBackground = computed(() => {
    return ['video', 'youtube'].includes(props.lesson.content_type);
});
</script>

<template>
    <Head :title="lesson.title" />

    <div class="h-screen flex flex-col bg-background">
        <!-- Compact Header -->
        <header class="h-14 border-b flex items-center px-4 shrink-0 bg-background">
            <Link
                :href="`/courses/${course.id}`"
                class="flex items-center gap-2 text-foreground hover:text-primary transition-colors"
            >
                <ArrowLeft class="h-4 w-4" />
                <span class="font-medium truncate max-w-[200px] sm:max-w-xs">{{ course.title }}</span>
            </Link>

            <div class="ml-auto flex items-center gap-4">
                <!-- Progress indicator -->
                <div v-if="enrollment" class="hidden sm:flex items-center gap-3">
                    <div class="w-32 bg-muted rounded-full h-1.5">
                        <div
                            class="bg-primary h-1.5 rounded-full transition-all"
                            :style="{ width: `${progressPercentage}%` }"
                        />
                    </div>
                    <span class="text-sm text-muted-foreground">{{ progressPercentage }}%</span>
                </div>

                <!-- Lesson counter (mobile) -->
                <span class="sm:hidden text-sm text-muted-foreground">
                    {{ currentLessonIndex + 1 }}/{{ allLessons.length }}
                </span>

                <!-- Sidebar toggle -->
                <Button
                    variant="ghost"
                    size="icon"
                    @click="sidebarOpen = !sidebarOpen"
                    class="shrink-0"
                >
                    <X v-if="sidebarOpen" class="h-5 w-5" />
                    <PanelRight v-else class="h-5 w-5" />
                </Button>
            </div>
        </header>

        <!-- Main Area -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Content Area -->
            <main class="flex-1 flex flex-col overflow-hidden">
                <!-- Video/Content Area -->
                <div
                    class="shrink-0 flex items-center justify-center"
                    :class="needsDarkBackground ? 'bg-black' : 'bg-muted/30'"
                >
                    <!-- YouTube Content -->
                    <div v-if="lesson.content_type === 'youtube' && lesson.youtube_video_id" class="w-full aspect-video max-h-[70vh]">
                        <YouTubePlayer
                            ref="youtubePlayerRef"
                            :video-id="lesson.youtube_video_id"
                            :initial-position="lessonProgress?.media_position_seconds ?? 0"
                            @timeupdate="handleYoutubeTimeUpdate"
                            @pause="handleYoutubePause"
                        />
                    </div>

                    <!-- Video Content (Uploaded) -->
                    <div v-else-if="lesson.content_type === 'video' && videoMedia" class="w-full aspect-video max-h-[70vh]">
                        <video
                            ref="videoPlayer"
                            :src="videoMedia.url"
                            class="w-full h-full"
                            controls
                            controlsList="nodownload"
                            @timeupdate="handleVideoTimeUpdate"
                            @pause="handleVideoPause"
                            @loadedmetadata="() => {
                                if (videoPlayer && lessonProgress?.media_position_seconds) {
                                    videoPlayer.currentTime = lessonProgress.media_position_seconds;
                                }
                            }"
                        />
                    </div>

                    <!-- Video Placeholder -->
                    <div v-else-if="lesson.content_type === 'video'" class="w-full aspect-video max-h-[70vh] flex items-center justify-center bg-muted">
                        <div class="text-center text-muted-foreground">
                            <PlayCircle class="h-16 w-16 mx-auto mb-2" />
                            <p>Video belum tersedia</p>
                        </div>
                    </div>

                    <!-- Audio Content -->
                    <div v-else-if="lesson.content_type === 'audio'" class="w-full p-8 max-w-2xl mx-auto">
                        <div v-if="audioMedia" class="space-y-6">
                            <div class="rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 p-8">
                                <div class="flex flex-col items-center gap-6">
                                    <div class="relative">
                                        <div class="flex h-24 w-24 items-center justify-center rounded-full bg-primary shadow-lg">
                                            <button
                                                type="button"
                                                @click="toggleAudioPlay"
                                                class="flex h-full w-full items-center justify-center rounded-full text-primary-foreground hover:scale-105 transition-transform"
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
                                @timeupdate="handleAudioTimeUpdate"
                                @pause="handleAudioPause"
                                @loadedmetadata="() => {
                                    if (audioPlayer && lessonProgress?.media_position_seconds) {
                                        audioPlayer.currentTime = lessonProgress.media_position_seconds;
                                    }
                                }"
                            />
                        </div>
                        <div v-else class="p-8 rounded-lg bg-muted flex items-center justify-center">
                            <div class="text-center text-muted-foreground">
                                <Headphones class="h-16 w-16 mx-auto mb-2" />
                                <p>Audio belum tersedia</p>
                            </div>
                        </div>
                    </div>

                    <!-- Document Content (PDF with pagination) -->
                    <div v-else-if="lesson.content_type === 'document'" class="w-full p-6 max-w-4xl mx-auto">
                        <div v-if="documentMedia">
                            <!-- PDF Viewer with pagination -->
                            <PaginatedPDFContent
                                v-if="documentMedia.mime_type === 'application/pdf'"
                                :pdf-url="documentMedia.url"
                                :initial-page="lessonProgress?.current_page ?? 1"
                                :course-id="course.id"
                                :lesson-id="lesson.id"
                                @page-change="handlePageChange"
                                @document-loaded="handleDocumentLoaded"
                            />
                            <!-- Other Documents (non-PDF) -->
                            <div v-else class="min-h-[50vh] flex items-center justify-center">
                                <div class="rounded-xl border bg-muted/20 p-8 max-w-md">
                                    <div class="flex flex-col items-center gap-4 text-center">
                                        <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                            <FileDown class="h-10 w-10" />
                                        </div>
                                        <div>
                                            <p class="font-medium text-lg">{{ documentMedia.file_name }}</p>
                                            <p class="text-sm text-muted-foreground">
                                                {{ getDocumentType(documentMedia.mime_type) }} - {{ documentMedia.human_readable_size }}
                                            </p>
                                        </div>
                                        <a :href="documentMedia.url" target="_blank">
                                            <Button variant="outline" class="gap-2">
                                                <Download class="h-4 w-4" />
                                                Buka Dokumen
                                            </Button>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="min-h-[50vh] flex items-center justify-center bg-muted rounded-lg">
                            <div class="text-center text-muted-foreground">
                                <FileDown class="h-16 w-16 mx-auto mb-2" />
                                <p>Dokumen belum tersedia</p>
                            </div>
                        </div>
                    </div>

                    <!-- Text Content (with pagination) -->
                    <div v-else-if="lesson.content_type === 'text'" class="w-full p-6 max-w-4xl mx-auto">
                        <PaginatedTextContent
                            v-if="lesson.rich_content_html"
                            :content="lesson.rich_content_html"
                            :initial-page="lessonProgress?.current_page ?? 1"
                            :saved-metadata="lessonProgress?.pagination_metadata ?? null"
                            :course-id="course.id"
                            :lesson-id="lesson.id"
                            @page-change="handlePageChange"
                            @pagination-ready="handlePaginationReady"
                        />
                        <div v-else class="p-8 rounded-lg bg-muted flex items-center justify-center min-h-[50vh]">
                            <div class="text-center text-muted-foreground">
                                <FileText class="h-16 w-16 mx-auto mb-2" />
                                <p>Konten teks belum tersedia</p>
                            </div>
                        </div>
                    </div>

                    <!-- Conference -->
                    <div v-else-if="lesson.content_type === 'conference'" class="w-full p-8 max-w-2xl mx-auto">
                        <div class="p-8 rounded-lg bg-muted flex items-center justify-center">
                            <div class="text-center text-muted-foreground">
                                <VideoCall class="h-16 w-16 mx-auto mb-2" />
                                <p>Informasi konferensi akan segera tersedia</p>
                            </div>
                        </div>
                    </div>

                    <!-- Fallback -->
                    <div v-else class="w-full p-8">
                        <div class="p-8 rounded-lg bg-muted flex items-center justify-center">
                            <div class="text-center text-muted-foreground">
                                <BookOpen class="h-16 w-16 mx-auto mb-2" />
                                <p>Konten belum tersedia</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Below Content Area -->
                <div class="flex-1 overflow-auto border-t">
                    <!-- Prev/Next + Lesson Title Bar -->
                    <div class="border-b px-4 py-3 flex items-center gap-2">
                        <Link
                            v-if="prevLesson"
                            :href="`/courses/${course.id}/lessons/${prevLesson.id}`"
                            class="shrink-0"
                        >
                            <Button variant="ghost" size="sm" class="gap-1">
                                <ChevronLeft class="h-4 w-4" />
                                <span class="hidden sm:inline">Sebelumnya</span>
                            </Button>
                        </Link>
                        <div v-else class="w-24 shrink-0" />

                        <h1 class="font-medium text-center flex-1 truncate px-2">
                            {{ lesson.title }}
                        </h1>

                        <Link
                            v-if="nextLesson"
                            :href="`/courses/${course.id}/lessons/${nextLesson.id}`"
                            class="shrink-0"
                        >
                            <Button variant="ghost" size="sm" class="gap-1">
                                <span class="hidden sm:inline">Selanjutnya</span>
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </Link>
                        <div v-else class="w-24 shrink-0" />
                    </div>

                    <!-- Tabs -->
                    <div class="border-b bg-background">
                        <nav class="flex gap-6 px-4">
                            <button
                                type="button"
                                @click="activeTab = 'overview'"
                                class="py-3 text-sm transition-colors relative"
                                :class="activeTab === 'overview'
                                    ? 'text-foreground font-medium'
                                    : 'text-muted-foreground hover:text-foreground'"
                            >
                                Ikhtisar
                                <span
                                    v-if="activeTab === 'overview'"
                                    class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary"
                                />
                            </button>
                            <button
                                type="button"
                                @click="activeTab = 'notes'"
                                class="py-3 text-sm transition-colors relative"
                                :class="activeTab === 'notes'
                                    ? 'text-foreground font-medium'
                                    : 'text-muted-foreground hover:text-foreground'"
                            >
                                Catatan
                                <span
                                    v-if="activeTab === 'notes'"
                                    class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary"
                                />
                            </button>
                        </nav>
                    </div>

                    <!-- Tab Content -->
                    <div class="p-4">
                        <!-- Overview Tab -->
                        <div v-if="activeTab === 'overview'" class="space-y-4">
                            <div>
                                <h2 class="font-semibold mb-2">Tentang Materi Ini</h2>
                                <p v-if="lesson.description" class="text-muted-foreground">
                                    {{ lesson.description }}
                                </p>
                                <p v-else class="text-muted-foreground italic">
                                    Tidak ada deskripsi untuk materi ini.
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-4 pt-2">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Clock class="h-4 w-4" />
                                    <span>{{ formatDuration(lesson.estimated_duration_minutes) }}</span>
                                </div>
                                <Badge variant="outline" class="gap-1">
                                    <component :is="lessonTypeIcon(lesson.content_type)" class="h-3 w-3" />
                                    {{ lessonTypeLabel(lesson.content_type) }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Notes Tab -->
                        <div v-if="activeTab === 'notes'" class="py-8 text-center">
                            <p class="text-muted-foreground">Fitur catatan akan segera hadir.</p>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Sidebar (Collapsible) -->
            <aside
                v-if="sidebarOpen"
                class="w-80 border-l flex flex-col shrink-0 bg-background absolute lg:relative right-0 top-14 bottom-0 z-10 lg:z-auto shadow-lg lg:shadow-none"
            >
                <div class="p-4 border-b flex items-center justify-between shrink-0">
                    <span class="font-medium">Konten Kursus</span>
                    <Button variant="ghost" size="icon" @click="sidebarOpen = false" class="lg:hidden">
                        <X class="h-4 w-4" />
                    </Button>
                </div>
                <div class="flex-1 overflow-auto">
                    <div
                        v-for="section in course.sections"
                        :key="section.id"
                        class="border-b last:border-b-0"
                    >
                        <!-- Section Header -->
                        <button
                            type="button"
                            @click="toggleSection(section.id)"
                            class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-muted/50 transition-colors"
                        >
                            <div class="flex items-center gap-2 min-w-0">
                                <ChevronDown
                                    v-if="isSectionExpanded(section.id)"
                                    class="h-4 w-4 shrink-0"
                                />
                                <ChevronRight v-else class="h-4 w-4 shrink-0" />
                                <span class="text-sm font-medium truncate">{{ section.title }}</span>
                            </div>
                            <span class="text-xs text-muted-foreground shrink-0 ml-2">
                                {{ section.lessons.length }} | {{ formatSectionDuration(section) }}
                            </span>
                        </button>

                        <!-- Section Lessons -->
                        <div
                            v-if="isSectionExpanded(section.id)"
                            class="bg-muted/30"
                        >
                            <Link
                                v-for="lessonItem in section.lessons"
                                :key="lessonItem.id"
                                :href="`/courses/${course.id}/lessons/${lessonItem.id}`"
                                class="flex items-center gap-3 px-4 py-2.5 border-t hover:bg-muted/50 transition-colors"
                                :class="{ 'bg-primary/10': lessonItem.id === lesson.id }"
                            >
                                <component
                                    :is="lessonTypeIcon(lessonItem.content_type)"
                                    class="h-4 w-4 shrink-0"
                                    :class="{
                                        'text-green-500': lessonCompletionMap[lessonItem.id],
                                        'text-primary': !lessonCompletionMap[lessonItem.id] && lessonItem.id === lesson.id,
                                        'text-muted-foreground/50': !lessonCompletionMap[lessonItem.id] && lessonItem.id !== lesson.id,
                                    }"
                                />
                                <div class="flex-1 min-w-0">
                                    <p
                                        class="text-sm truncate"
                                        :class="lessonItem.id === lesson.id
                                            ? 'font-medium text-primary'
                                            : 'text-foreground'"
                                    >
                                        {{ lessonItem.title }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ formatDuration(lessonItem.estimated_duration_minutes) }}
                                    </p>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Progress Footer -->
                <div v-if="enrollment" class="p-4 border-t bg-muted/30 shrink-0">
                    <div class="flex items-center justify-between text-sm mb-2">
                        <span class="text-muted-foreground">Progress Kursus</span>
                        <span class="font-medium">{{ progressPercentage }}%</span>
                    </div>
                    <div class="w-full bg-muted rounded-full h-2">
                        <div
                            class="bg-primary h-2 rounded-full transition-all"
                            :style="{ width: `${progressPercentage}%` }"
                        />
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
