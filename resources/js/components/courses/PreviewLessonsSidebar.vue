<script setup lang="ts">
// =============================================================================
// PreviewLessonsSidebar Component
// Sidebar showing lesson info and preview lessons navigation
// =============================================================================

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import {
    Clock,
    ChevronDown,
    ChevronRight,
    PlayCircle,
    FileText,
    Headphones,
    FileDown,
    Video as VideoCall,
    Youtube,
    Eye,
} from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import type { ContentType } from '@/types';
import { formatDuration, contentTypeLabel } from '@/lib/formatters';

// =============================================================================
// Types
// =============================================================================

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

interface Props {
    courseId: number;
    currentLessonId: number;
    contentType: ContentType;
    estimatedDurationMinutes: number | null;
    sections: SidebarSection[];
    isEnrolled: boolean;
    isEnrolling: boolean;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

const emit = defineEmits<{
    enroll: [];
}>();

// =============================================================================
// State
// =============================================================================

const expandedSections = ref<Set<number>>(new Set());

// =============================================================================
// Lifecycle
// =============================================================================

onMounted(() => {
    // Auto-expand the section containing the current lesson
    for (const section of props.sections) {
        if (section.lessons.some(l => l.id === props.currentLessonId)) {
            expandedSections.value.add(section.id);
            break;
        }
    }
});

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

const sectionHasPreviewLessons = (section: SidebarSection) => {
    return section.lessons.some(l => l.is_free_preview);
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

// =============================================================================
// Computed
// =============================================================================

const allPreviewLessons = computed(() => {
    const lessons: { id: number; title: string; sectionTitle: string }[] = [];
    props.sections.forEach(section => {
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
</script>

<template>
    <div class="sticky top-4 space-y-4">
        <!-- Lesson Info -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Informasi Materi</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="flex items-center gap-2 text-sm">
                    <Clock class="h-4 w-4 text-muted-foreground" />
                    <span>Durasi: {{ formatDuration(estimatedDurationMinutes, 'long') }}</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <component :is="lessonTypeIcon(contentType)" class="h-4 w-4 text-muted-foreground" />
                    <span>Tipe: {{ contentTypeLabel(contentType) }}</span>
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
                    <template v-for="section in sections" :key="section.id">
                        <div
                            v-if="sectionHasPreviewLessons(section)"
                            class="border-b last:border-b-0"
                        >
                            <!-- Section Header -->
                            <button
                                type="button"
                                class="flex w-full items-center justify-between px-4 py-2 text-left hover:bg-muted/50 transition-colors"
                                @click="toggleSection(section.id)"
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
                                        :href="`/courses/${courseId}/lessons/${lessonItem.id}/preview`"
                                        class="flex items-center gap-3 px-4 py-2 border-t hover:bg-muted/50 transition-colors"
                                        :class="{ 'bg-primary/5': lessonItem.id === currentLessonId }"
                                    >
                                        <span
                                            class="flex h-5 w-5 items-center justify-center rounded-full text-xs shrink-0"
                                            :class="lessonItem.id === currentLessonId
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'"
                                        >
                                            {{ index + 1 }}
                                        </span>
                                        <p
                                            class="text-sm truncate flex-1"
                                            :class="{ 'font-medium text-primary': lessonItem.id === currentLessonId }"
                                        >
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
                <Button class="w-full" :disabled="isEnrolling" @click="emit('enroll')">
                    {{ isEnrolling ? 'Mendaftar...' : 'Daftar Sekarang - Gratis' }}
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
