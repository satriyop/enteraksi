<script setup lang="ts">
// =============================================================================
// CourseSidebar Component
// Displays course curriculum with expandable sections and lessons
// =============================================================================

import { ref, computed, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { ProgressBar } from '@/components/features/shared';
import {
    ChevronDown,
    ChevronRight,
    X,
    PlayCircle,
    FileText,
    Headphones,
    FileDown,
    Video as VideoCall,
    Youtube,
} from 'lucide-vue-next';
import type { ContentType, EnrollmentStatus } from '@/types';

// =============================================================================
// Types
// =============================================================================

/** Lesson item in sidebar */
interface SidebarLesson {
    id: number;
    title: string;
    content_type: ContentType;
    is_free_preview: boolean;
    order: number;
    estimated_duration_minutes: number | null;
    is_completed?: boolean;
}

/** Section in sidebar */
interface SidebarSection {
    id: number;
    title: string;
    order: number;
    lessons: SidebarLesson[];
}

/** Enrollment info */
interface SidebarEnrollment {
    id: number;
    status: EnrollmentStatus;
    progress_percentage: number;
}

interface Props {
    /** Course ID for generating links */
    courseId: number;
    /** Sections with lessons */
    sections: SidebarSection[];
    /** Current active lesson ID */
    currentLessonId?: number;
    /** Enrollment info (null if not enrolled) */
    enrollment?: SidebarEnrollment | null;
    /** Map of lesson ID to completion status */
    completionMap?: Record<number, boolean>;
    /** Whether sidebar is visible */
    visible?: boolean;
    /** Whether to show close button (mobile) */
    showCloseButton?: boolean;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    currentLessonId: undefined,
    enrollment: null,
    completionMap: () => ({}),
    visible: true,
    showCloseButton: true,
});

const emit = defineEmits<{
    close: [];
}>();

// =============================================================================
// State
// =============================================================================

const expandedSections = ref<Set<number>>(new Set());

// =============================================================================
// Computed Properties
// =============================================================================

const progressPercentage = computed(() => props.enrollment?.progress_percentage ?? 0);

// =============================================================================
// Methods
// =============================================================================

const toggleSection = (sectionId: number) => {
    if (expandedSections.value.has(sectionId)) {
        expandedSections.value.delete(sectionId);
    } else {
        expandedSections.value.add(sectionId);
    }
};

const isSectionExpanded = (sectionId: number) => expandedSections.value.has(sectionId);

const getLessonIcon = (contentType: ContentType) => {
    const icons = {
        video: PlayCircle,
        youtube: Youtube,
        audio: Headphones,
        document: FileDown,
        conference: VideoCall,
        text: FileText,
    };
    return icons[contentType] || FileText;
};

const formatDuration = (minutes: number | null) => {
    if (!minutes) return '-';
    if (minutes < 60) return `${minutes} menit`;
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    if (remainingMinutes === 0) return `${hours} jam`;
    return `${hours}j ${remainingMinutes}m`;
};

const formatSectionDuration = (section: SidebarSection) => {
    const total = section.lessons.reduce((sum, l) => sum + (l.estimated_duration_minutes || 0), 0);
    return formatDuration(total);
};

const isLessonCompleted = (lessonId: number) => {
    return props.completionMap[lessonId] ?? false;
};

const isCurrentLesson = (lessonId: number) => {
    return lessonId === props.currentLessonId;
};

// =============================================================================
// Lifecycle
// =============================================================================

onMounted(() => {
    // Auto-expand section containing current lesson
    if (props.currentLessonId) {
        for (const section of props.sections) {
            if (section.lessons.some(l => l.id === props.currentLessonId)) {
                expandedSections.value.add(section.id);
                break;
            }
        }
    }
});
</script>

<template>
    <aside
        v-if="visible"
        class="w-80 border-l flex flex-col shrink-0 bg-background absolute lg:relative right-0 top-0 bottom-0 z-10 lg:z-auto shadow-lg lg:shadow-none h-full"
    >
        <!-- Header -->
        <div class="p-4 border-b flex items-center justify-between shrink-0">
            <span class="font-medium">Konten Kursus</span>
            <Button
                v-if="showCloseButton"
                variant="ghost"
                size="icon"
                @click="emit('close')"
                class="lg:hidden"
            >
                <X class="h-4 w-4" />
            </Button>
        </div>

        <!-- Sections List -->
        <div class="flex-1 overflow-auto">
            <div
                v-for="section in sections"
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
                        v-for="lesson in section.lessons"
                        :key="lesson.id"
                        :href="`/courses/${courseId}/lessons/${lesson.id}`"
                        class="flex items-center gap-3 px-4 py-2.5 border-t hover:bg-muted/50 transition-colors"
                        :class="{ 'bg-primary/10': isCurrentLesson(lesson.id) }"
                    >
                        <component
                            :is="getLessonIcon(lesson.content_type)"
                            class="h-4 w-4 shrink-0"
                            :class="{
                                'text-green-500': isLessonCompleted(lesson.id),
                                'text-primary': !isLessonCompleted(lesson.id) && isCurrentLesson(lesson.id),
                                'text-muted-foreground/50': !isLessonCompleted(lesson.id) && !isCurrentLesson(lesson.id),
                            }"
                        />
                        <div class="flex-1 min-w-0">
                            <p
                                class="text-sm truncate"
                                :class="isCurrentLesson(lesson.id)
                                    ? 'font-medium text-primary'
                                    : 'text-foreground'"
                            >
                                {{ lesson.title }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatDuration(lesson.estimated_duration_minutes) }}
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
            <ProgressBar :value="progressPercentage" size="sm" />
        </div>
    </aside>
</template>
