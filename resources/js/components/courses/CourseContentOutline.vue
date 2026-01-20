<script setup lang="ts">
// =============================================================================
// CourseContentOutline Component
// Displays expandable course sections and lessons for public view
// =============================================================================

import { ref, computed, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    ChevronDown,
    ChevronRight,
    FolderOpen,
    Eye,
    Lock,
} from 'lucide-vue-next';
import { formatDuration, getContentTypeIcon } from '@/lib/utils';
import type { ContentType } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface OutlineLesson {
    id: number;
    title: string;
    content_type: ContentType;
    estimated_duration_minutes: number | null;
    order: number;
    is_free_preview: boolean;
}

interface OutlineSection {
    id: number;
    title: string;
    order: number;
    lessons: OutlineLesson[];
}

interface Props {
    /** Course ID for generating links */
    courseId: number;
    /** Sections with lessons */
    sections: OutlineSection[];
    /** Total duration in minutes */
    totalDurationMinutes: number;
    /** Whether user is enrolled */
    isEnrolled?: boolean;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    isEnrolled: false,
});

// =============================================================================
// State
// =============================================================================

const expandedSections = ref<number[]>([]);

// =============================================================================
// Computed
// =============================================================================

const totalLessons = computed(() =>
    props.sections.reduce((total, section) => total + section.lessons.length, 0)
);

// =============================================================================
// Methods
// =============================================================================

const toggleSection = (sectionId: number) => {
    const index = expandedSections.value.indexOf(sectionId);
    if (index === -1) {
        expandedSections.value.push(sectionId);
    } else {
        expandedSections.value.splice(index, 1);
    }
};

const isSectionExpanded = (sectionId: number) => {
    return expandedSections.value.includes(sectionId);
};

// =============================================================================
// Lifecycle
// =============================================================================

onMounted(() => {
    // Auto-expand: all sections for enrolled, or sections with preview for non-enrolled
    if (props.isEnrolled) {
        expandedSections.value = props.sections.map(s => s.id);
    } else {
        expandedSections.value = props.sections
            .filter(section => section.lessons.some(lesson => lesson.is_free_preview))
            .map(section => section.id);
    }
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <FolderOpen class="h-5 w-5" />
                Konten Kursus
            </CardTitle>
            <p class="text-sm text-muted-foreground">
                {{ sections.length }} bagian • {{ totalLessons }} materi • {{ formatDuration(totalDurationMinutes, 'long') }} total durasi
            </p>
        </CardHeader>
        <CardContent>
            <!-- Empty State -->
            <div v-if="sections.length === 0" class="py-8 text-center text-muted-foreground">
                Belum ada konten untuk kursus ini.
            </div>

            <!-- Sections List -->
            <div v-else class="space-y-2">
                <div
                    v-for="section in sections"
                    :key="section.id"
                    class="rounded-lg border"
                >
                    <!-- Section Header -->
                    <button
                        type="button"
                        @click="toggleSection(section.id)"
                        class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-muted/50 transition-colors"
                    >
                        <div class="flex items-center gap-2">
                            <ChevronDown
                                v-if="isSectionExpanded(section.id)"
                                class="h-4 w-4 shrink-0"
                            />
                            <ChevronRight v-else class="h-4 w-4 shrink-0" />
                            <span class="font-medium">{{ section.title }}</span>
                        </div>
                        <span class="text-sm text-muted-foreground">
                            {{ section.lessons.length }} materi
                        </span>
                    </button>

                    <!-- Section Lessons -->
                    <div
                        v-if="isSectionExpanded(section.id)"
                        class="border-t bg-muted/30"
                    >
                        <template v-for="lesson in section.lessons" :key="lesson.id">
                            <!-- Enrolled users can click any lesson -->
                            <Link
                                v-if="isEnrolled"
                                :href="`/courses/${courseId}/lessons/${lesson.id}`"
                                class="flex items-center gap-3 px-4 py-3 border-b last:border-b-0 transition-colors hover:bg-primary/5 cursor-pointer"
                            >
                                <component
                                    :is="getContentTypeIcon(lesson.content_type)"
                                    class="h-4 w-4 shrink-0 text-primary"
                                />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm truncate text-foreground">
                                        {{ lesson.title }}
                                    </p>
                                </div>
                                <span v-if="lesson.estimated_duration_minutes" class="text-xs text-muted-foreground">
                                    {{ lesson.estimated_duration_minutes }} min
                                </span>
                            </Link>

                            <!-- Non-enrolled users: preview lessons are clickable -->
                            <Link
                                v-else-if="lesson.is_free_preview"
                                :href="`/courses/${courseId}/lessons/${lesson.id}/preview`"
                                class="flex items-center gap-3 px-4 py-3 border-b last:border-b-0 transition-colors hover:bg-primary/5 cursor-pointer"
                            >
                                <component
                                    :is="getContentTypeIcon(lesson.content_type)"
                                    class="h-4 w-4 shrink-0 text-primary"
                                />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm truncate text-primary font-medium">
                                        {{ lesson.title }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                    <Badge class="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300 gap-1">
                                        <Eye class="h-3 w-3" />
                                        Preview
                                    </Badge>
                                    <span v-if="lesson.estimated_duration_minutes">
                                        {{ lesson.estimated_duration_minutes }} min
                                    </span>
                                </div>
                            </Link>

                            <!-- Non-enrolled users: locked lessons -->
                            <div
                                v-else
                                class="flex items-center gap-3 px-4 py-3 border-b last:border-b-0 transition-colors opacity-60"
                            >
                                <component
                                    :is="getContentTypeIcon(lesson.content_type)"
                                    class="h-4 w-4 shrink-0 text-muted-foreground"
                                />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm truncate">
                                        {{ lesson.title }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                    <Lock class="h-3 w-3" />
                                    <span v-if="lesson.estimated_duration_minutes">
                                        {{ lesson.estimated_duration_minutes }} min
                                    </span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
