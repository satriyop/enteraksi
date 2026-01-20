<script setup lang="ts">
// =============================================================================
// LessonNavigationBar Component
// Navigation bar with prev/next buttons and lesson title
// =============================================================================

import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface NavigationLesson {
    id: number;
    title: string;
}

interface Props {
    /** Course ID */
    courseId: number;
    /** Current lesson title */
    lessonTitle: string;
    /** Previous lesson info */
    prevLesson: NavigationLesson | null;
    /** Next lesson info */
    nextLesson: NavigationLesson | null;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();
</script>

<template>
    <div class="border-b px-4 py-3 flex items-center gap-2">
        <!-- Previous button -->
        <Link
            v-if="prevLesson"
            :href="`/courses/${courseId}/lessons/${prevLesson.id}`"
            class="shrink-0"
        >
            <Button variant="ghost" size="sm" class="gap-1">
                <ChevronLeft class="h-4 w-4" />
                <span class="hidden sm:inline">Sebelumnya</span>
            </Button>
        </Link>
        <div v-else class="w-24 shrink-0" />

        <!-- Lesson title -->
        <h1 class="font-medium text-center flex-1 truncate px-2">
            {{ lessonTitle }}
        </h1>

        <!-- Next button -->
        <Link
            v-if="nextLesson"
            :href="`/courses/${courseId}/lessons/${nextLesson.id}`"
            class="shrink-0"
        >
            <Button variant="ghost" size="sm" class="gap-1">
                <span class="hidden sm:inline">Selanjutnya</span>
                <ChevronRight class="h-4 w-4" />
            </Button>
        </Link>
        <div v-else class="w-24 shrink-0" />
    </div>
</template>
