<script setup lang="ts">
// =============================================================================
// LessonHeader Component
// Top header bar with course link, progress indicator, and sidebar toggle
// =============================================================================

import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { ProgressBar } from '@/components/features/shared';
import { ArrowLeft, X, PanelRight } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface Props {
    /** Course ID */
    courseId: number;
    /** Course title */
    courseTitle: string;
    /** Whether user is enrolled */
    isEnrolled: boolean;
    /** Course progress percentage */
    progressPercentage: number;
    /** Current lesson index (1-based) */
    currentLessonIndex: number;
    /** Total lessons count */
    totalLessons: number;
    /** Whether sidebar is open */
    sidebarOpen: boolean;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

const emit = defineEmits<{
    toggleSidebar: [];
}>();
</script>

<template>
    <header class="h-14 border-b flex items-center px-4 shrink-0 bg-background">
        <Link
            :href="`/courses/${courseId}`"
            class="flex items-center gap-2 text-foreground hover:text-primary transition-colors"
        >
            <ArrowLeft class="h-4 w-4" />
            <span class="font-medium truncate max-w-[200px] sm:max-w-xs">{{ courseTitle }}</span>
        </Link>

        <div class="ml-auto flex items-center gap-4">
            <!-- Progress indicator (desktop) -->
            <div v-if="isEnrolled" class="hidden sm:flex items-center gap-3">
                <ProgressBar :value="progressPercentage" size="sm" class="w-32" />
                <span class="text-sm text-muted-foreground">{{ progressPercentage }}%</span>
            </div>

            <!-- Lesson counter (mobile) -->
            <span class="sm:hidden text-sm text-muted-foreground">
                {{ currentLessonIndex }}/{{ totalLessons }}
            </span>

            <!-- Sidebar toggle -->
            <Button
                variant="ghost"
                size="icon"
                class="shrink-0"
                @click="emit('toggleSidebar')"
            >
                <X v-if="sidebarOpen" class="h-5 w-5" />
                <PanelRight v-else class="h-5 w-5" />
            </Button>
        </div>
    </header>
</template>
