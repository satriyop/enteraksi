<script setup lang="ts">
// =============================================================================
// Lesson Preview Page
// Public preview for unenrolled users to view free preview lessons
// =============================================================================

import Navbar from '@/components/home/Navbar.vue';
import Footer from '@/components/home/Footer.vue';
import { Badge } from '@/components/ui/badge';
import PreviewBanner from '@/components/courses/PreviewBanner.vue';
import PreviewContentCard from '@/components/courses/PreviewContentCard.vue';
import PreviewLessonsSidebar from '@/components/courses/PreviewLessonsSidebar.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    PlayCircle,
    FileText,
    Eye,
    Headphones,
    FileDown,
    Video as VideoCall,
    Youtube,
    ArrowLeft,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import type { Category, ContentType, Media, UserSummary } from '@/types';

// =============================================================================
// Page-Specific Types
// =============================================================================

interface PreviewLesson {
    id: number;
    title: string;
    description: string | null;
    content_type: ContentType;
    rich_content: { content?: string } | null;
    youtube_url: string | null;
    youtube_video_id: string | null;
    estimated_duration_minutes: number | null;
    is_free_preview: boolean;
    media: Media[];
}

interface SidebarLesson {
    id: number;
    title: string;
    is_free_preview: boolean;
    order: number;
}

interface SidebarSection {
    id: number;
    title: string;
    order: number;
    lessons: SidebarLesson[];
}

interface PreviewCourse {
    id: number;
    title: string;
    slug: string;
    user: UserSummary;
    category: Category | null;
    sections: SidebarSection[];
}

interface BasicEnrollment {
    id: number;
    status: string;
}

interface Props {
    course: PreviewCourse;
    lesson: PreviewLesson;
    enrollment: BasicEnrollment | null;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

const page = usePage();
const appName = computed(() => page.props.name || 'E-Learning');

// =============================================================================
// State
// =============================================================================

const isEnrolling = ref(false);

// =============================================================================
// Computed
// =============================================================================

const isEnrolled = computed(() => {
    return props.enrollment && props.enrollment.status === 'active';
});

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

// =============================================================================
// Methods
// =============================================================================

const handleEnroll = () => {
    isEnrolling.value = true;
    router.post(`/courses/${props.course.id}/enroll`, {}, {
        onFinish: () => {
            isEnrolling.value = false;
        },
    });
};

const lessonTypeIcon = (type: string) => {
    const icons: Record<string, typeof PlayCircle> = {
        video: PlayCircle,
        youtube: Youtube,
        audio: Headphones,
        document: FileDown,
        conference: VideoCall,
        text: FileText,
    };
    return icons[type] || FileText;
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
            <PreviewBanner
                :is-enrolled="isEnrolled"
                :is-enrolling="isEnrolling"
                @enroll="handleEnroll"
            />

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
                    <PreviewContentCard
                        :content-type="lesson.content_type"
                        :rich-content="lesson.rich_content"
                        :youtube-video-id="lesson.youtube_video_id"
                        :media="lesson.media"
                    />

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
                    <PreviewLessonsSidebar
                        :course-id="course.id"
                        :current-lesson-id="lesson.id"
                        :content-type="lesson.content_type"
                        :estimated-duration-minutes="lesson.estimated_duration_minutes"
                        :sections="course.sections"
                        :is-enrolled="isEnrolled"
                        :is-enrolling="isEnrolling"
                        @enroll="handleEnroll"
                    />
                </div>
            </div>
        </main>

        <Footer :app-name="appName" />
    </div>
</template>
