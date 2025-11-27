<script setup lang="ts">
import Navbar from '@/components/home/Navbar.vue';
import Footer from '@/components/home/Footer.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Clock,
    BookOpen,
    ChevronLeft,
    ChevronRight,
    ChevronDown,
    PlayCircle,
    FileText,
    Eye,
    Headphones,
    FileDown,
    Video as VideoCall,
    Youtube,
    ArrowLeft,
    Download,
    Play,
    Pause,
} from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';

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
    rich_content: { content?: string } | null;
    youtube_url: string | null;
    youtube_video_id: string | null;
    estimated_duration_minutes: number | null;
    is_free_preview: boolean;
    media: Media[];
}

interface SectionLesson {
    id: number;
    title: string;
    is_free_preview: boolean;
    order: number;
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
}

interface Props {
    course: Course;
    lesson: Lesson;
    enrollment: Enrollment | null;
}

const props = defineProps<Props>();

const page = usePage();
const appName = computed(() => page.props.name || 'E-Learning');

const isEnrolling = ref(false);
const isAudioPlaying = ref(false);
const audioPlayer = ref<HTMLAudioElement | null>(null);

// Section expand/collapse state - auto-expand current lesson's section
const expandedSections = ref<Set<number>>(new Set());

onMounted(() => {
    // Find and expand the section containing the current lesson
    for (const section of props.course.sections) {
        if (section.lessons.some(l => l.id === props.lesson.id)) {
            expandedSections.value.add(section.id);
            break;
        }
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

// Check if a section has any preview lessons
const sectionHasPreviewLessons = (section: Section) => {
    return section.lessons.some(l => l.is_free_preview);
};

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

const youtubeEmbedUrl = computed(() => {
    if (!props.lesson.youtube_video_id) return null;
    return `https://www.youtube.com/embed/${props.lesson.youtube_video_id}`;
});

const formatDuration = (minutes: number | null) => {
    if (!minutes) return '-';
    if (minutes < 60) return `${minutes} menit`;
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    if (remainingMinutes === 0) return `${hours} jam`;
    return `${hours}j ${remainingMinutes}m`;
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

// Get all preview lessons for navigation
const allPreviewLessons = computed(() => {
    const lessons: { id: number; title: string; sectionTitle: string }[] = [];
    props.course.sections.forEach(section => {
        section.lessons.forEach(lesson => {
            if (lesson.is_free_preview) {
                lessons.push({
                    id: lesson.id,
                    title: lesson.title,
                    sectionTitle: section.title,
                });
            }
        });
    });
    return lessons;
});

const currentPreviewIndex = computed(() => {
    return allPreviewLessons.value.findIndex(l => l.id === props.lesson.id);
});

const prevPreviewLesson = computed(() => {
    if (currentPreviewIndex.value <= 0) return null;
    return allPreviewLessons.value[currentPreviewIndex.value - 1];
});

const nextPreviewLesson = computed(() => {
    if (currentPreviewIndex.value >= allPreviewLessons.value.length - 1) return null;
    return allPreviewLessons.value[currentPreviewIndex.value + 1];
});

const handleEnroll = () => {
    isEnrolling.value = true;
    router.post(`/courses/${props.course.id}/enroll`, {}, {
        onFinish: () => {
            isEnrolling.value = false;
        },
    });
};

const isEnrolled = computed(() => {
    return props.enrollment && props.enrollment.status === 'active';
});

// Get document file type for icon/styling
const getDocumentType = (mimeType: string): string => {
    if (mimeType === 'application/pdf') return 'PDF';
    if (mimeType.includes('word')) return 'Word';
    if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'PowerPoint';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'Excel';
    return 'Dokumen';
};
</script>

<template>
    <Head :title="`Preview: ${lesson.title}`" />

    <div class="min-h-screen bg-background">
        <Navbar :app-name="appName" />

        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Back Link -->
            <div class="mb-6">
                <Link
                    :href="`/courses/${course.id}`"
                    class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Kembali ke {{ course.title }}
                </Link>
            </div>

            <!-- Preview Banner -->
            <div class="mb-6 rounded-lg bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                        <Eye class="h-5 w-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-green-800 dark:text-green-200">Mode Preview</p>
                        <p class="text-sm text-green-700 dark:text-green-300">
                            Anda sedang melihat preview materi. Daftar ke kursus untuk akses penuh.
                        </p>
                    </div>
                    <Button
                        v-if="!isEnrolled"
                        @click="handleEnroll"
                        :disabled="isEnrolling"
                        size="sm"
                    >
                        {{ isEnrolling ? 'Mendaftar...' : 'Daftar Kursus' }}
                    </Button>
                </div>
            </div>

            <div class="grid gap-8 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="lg:col-span-2">
                    <!-- Lesson Header -->
                    <div class="mb-6">
                        <div class="flex items-center gap-2 mb-2">
                            <Badge variant="outline" class="gap-1">
                                <component :is="lessonTypeIcon(lesson.content_type)" class="h-3 w-3" />
                                {{ lessonTypeLabel(lesson.content_type) }}
                            </Badge>
                            <Badge class="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300 gap-1">
                                <Eye class="h-3 w-3" />
                                Preview
                            </Badge>
                        </div>
                        <h1 class="text-2xl font-bold mb-2">{{ lesson.title }}</h1>
                        <p v-if="lesson.description" class="text-muted-foreground">{{ lesson.description }}</p>
                    </div>

                    <!-- Content Area -->
                    <Card class="mb-6">
                        <CardContent class="p-6">
                            <!-- YouTube Content -->
                            <div v-if="lesson.content_type === 'youtube' && youtubeEmbedUrl" class="aspect-video w-full rounded-lg overflow-hidden bg-black">
                                <iframe
                                    :src="youtubeEmbedUrl"
                                    class="h-full w-full"
                                    frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                />
                            </div>

                            <!-- Text Content -->
                            <div v-else-if="lesson.content_type === 'text' && lesson.rich_content?.content" class="prose prose-sm dark:prose-invert max-w-none">
                                <div v-html="lesson.rich_content.content" />
                            </div>

                            <!-- Video Content (Uploaded) -->
                            <div v-else-if="lesson.content_type === 'video' && videoMedia" class="space-y-4">
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

                            <!-- Video Placeholder (No media uploaded) -->
                            <div v-else-if="lesson.content_type === 'video'" class="aspect-video w-full rounded-lg bg-muted flex items-center justify-center">
                                <div class="text-center text-muted-foreground">
                                    <PlayCircle class="h-16 w-16 mx-auto mb-2" />
                                    <p>Video belum tersedia</p>
                                </div>
                            </div>

                            <!-- Audio Content (Uploaded) -->
                            <div v-else-if="lesson.content_type === 'audio' && audioMedia" class="space-y-4">
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
                                />
                            </div>

                            <!-- Audio Placeholder (No media uploaded) -->
                            <div v-else-if="lesson.content_type === 'audio'" class="p-8 rounded-lg bg-muted flex items-center justify-center">
                                <div class="text-center text-muted-foreground">
                                    <Headphones class="h-16 w-16 mx-auto mb-2" />
                                    <p>Audio belum tersedia</p>
                                </div>
                            </div>

                            <!-- Document Content (Uploaded) -->
                            <div v-else-if="lesson.content_type === 'document' && documentMedia" class="space-y-4">
                                <!-- PDF Viewer -->
                                <div v-if="documentMedia.mime_type === 'application/pdf'" class="rounded-lg overflow-hidden border">
                                    <iframe
                                        :src="documentMedia.url"
                                        class="w-full h-[600px]"
                                        frameborder="0"
                                    />
                                </div>

                                <!-- Other Documents (Download link) -->
                                <div v-else class="rounded-xl border bg-muted/20 p-8">
                                    <div class="flex flex-col items-center gap-4 text-center">
                                        <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                            <FileIcon class="h-10 w-10" />
                                        </div>
                                        <div>
                                            <p class="font-medium text-lg">{{ documentMedia.file_name }}</p>
                                            <p class="text-sm text-muted-foreground">
                                                {{ getDocumentType(documentMedia.mime_type) }} • {{ documentMedia.human_readable_size }}
                                            </p>
                                        </div>
                                        <a
                                            :href="documentMedia.url"
                                            target="_blank"
                                            class="inline-flex items-center gap-2"
                                        >
                                            <Button variant="outline" class="gap-2">
                                                <Download class="h-4 w-4" />
                                                Buka Dokumen
                                            </Button>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Document Placeholder (No media uploaded) -->
                            <div v-else-if="lesson.content_type === 'document'" class="p-8 rounded-lg bg-muted flex items-center justify-center">
                                <div class="text-center text-muted-foreground">
                                    <FileIcon class="h-16 w-16 mx-auto mb-2" />
                                    <p>Dokumen belum tersedia</p>
                                </div>
                            </div>

                            <!-- Conference info -->
                            <div v-else-if="lesson.content_type === 'conference'" class="p-8 rounded-lg bg-muted flex items-center justify-center">
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

                    <!-- Navigation -->
                    <div class="flex items-center justify-between gap-4">
                        <Link
                            v-if="prevPreviewLesson"
                            :href="`/courses/${course.id}/lessons/${prevPreviewLesson.id}/preview`"
                            class="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
                        >
                            <ChevronLeft class="h-4 w-4" />
                            <span class="hidden sm:inline">{{ prevPreviewLesson.title }}</span>
                            <span class="sm:hidden">Sebelumnya</span>
                        </Link>
                        <div v-else />

                        <Link
                            v-if="nextPreviewLesson"
                            :href="`/courses/${course.id}/lessons/${nextPreviewLesson.id}/preview`"
                            class="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
                        >
                            <span class="hidden sm:inline">{{ nextPreviewLesson.title }}</span>
                            <span class="sm:hidden">Selanjutnya</span>
                            <ChevronRight class="h-4 w-4" />
                        </Link>
                        <div v-else />
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1">
                    <div class="sticky top-4 space-y-4">
                        <!-- Lesson Info -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-base">Informasi Materi</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="flex items-center gap-2 text-sm">
                                    <Clock class="h-4 w-4 text-muted-foreground" />
                                    <span>Durasi: {{ formatDuration(lesson.estimated_duration_minutes) }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                    <component :is="lessonTypeIcon(lesson.content_type)" class="h-4 w-4 text-muted-foreground" />
                                    <span>Tipe: {{ lessonTypeLabel(lesson.content_type) }}</span>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Preview Lessons by Section -->
                        <Card v-if="allPreviewLessons.length > 0">
                            <CardHeader>
                                <CardTitle class="text-base flex items-center gap-2">
                                    <Eye class="h-4 w-4" />
                                    Materi Preview
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="p-0">
                                <div class="max-h-96 overflow-y-auto">
                                    <template v-for="section in course.sections" :key="section.id">
                                        <div
                                            v-if="sectionHasPreviewLessons(section)"
                                            class="border-b last:border-b-0"
                                        >
                                            <!-- Section Header -->
                                            <button
                                                type="button"
                                                @click="toggleSection(section.id)"
                                                class="flex w-full items-center justify-between px-4 py-2 text-left hover:bg-muted/50 transition-colors"
                                            >
                                                <div class="flex items-center gap-2">
                                                    <ChevronDown
                                                        v-if="isSectionExpanded(section.id)"
                                                        class="h-4 w-4 shrink-0"
                                                    />
                                                    <ChevronRight v-else class="h-4 w-4 shrink-0" />
                                                    <span class="text-sm font-medium truncate">{{ section.title }}</span>
                                                </div>
                                                <span class="text-xs text-muted-foreground shrink-0">
                                                    {{ section.lessons.filter(l => l.is_free_preview).length }} preview
                                                </span>
                                            </button>

                                            <!-- Section Preview Lessons -->
                                            <div
                                                v-if="isSectionExpanded(section.id)"
                                                class="bg-muted/30"
                                            >
                                                <template v-for="(lessonItem, index) in section.lessons" :key="lessonItem.id">
                                                    <Link
                                                        v-if="lessonItem.is_free_preview"
                                                        :href="`/courses/${course.id}/lessons/${lessonItem.id}/preview`"
                                                        class="flex items-center gap-3 px-4 py-2 border-t hover:bg-muted/50 transition-colors"
                                                        :class="{ 'bg-primary/5': lessonItem.id === lesson.id }"
                                                    >
                                                        <span class="flex h-5 w-5 items-center justify-center rounded-full text-xs shrink-0"
                                                            :class="lessonItem.id === lesson.id ? 'bg-primary text-primary-foreground' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'"
                                                        >
                                                            {{ index + 1 }}
                                                        </span>
                                                        <p class="text-sm truncate flex-1" :class="{ 'font-medium text-primary': lessonItem.id === lesson.id }">
                                                            {{ lessonItem.title }}
                                                        </p>
                                                    </Link>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Enroll CTA -->
                        <Card v-if="!isEnrolled" class="bg-primary/5 border-primary/20">
                            <CardContent class="p-4 text-center">
                                <p class="text-sm mb-3">Ingin akses ke semua materi?</p>
                                <Button class="w-full" @click="handleEnroll" :disabled="isEnrolling">
                                    {{ isEnrolling ? 'Mendaftar...' : 'Daftar Sekarang - Gratis' }}
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </main>

        <Footer :app-name="appName" />
    </div>
</template>
