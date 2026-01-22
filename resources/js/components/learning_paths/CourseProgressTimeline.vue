<script setup lang="ts">
// =============================================================================
// CourseProgressTimeline Component
// Visual timeline showing course progression within a learning path
// =============================================================================

import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import {
    BookOpen,
    Clock,
    CheckCircle,
    Play,
    Lock,
    ChevronRight,
    RotateCcw,
} from 'lucide-vue-next';
import { formatDuration } from '@/lib/utils';
import type { CourseProgressItem, CourseProgressStatus } from '@/types/learning-path';

// =============================================================================
// Types
// =============================================================================

interface Props {
    courses: CourseProgressItem[];
}

const props = defineProps<Props>();

// =============================================================================
// Methods
// =============================================================================

const getStatusIcon = (status: CourseProgressStatus) => {
    switch (status) {
        case 'completed':
            return CheckCircle;
        case 'in_progress':
            return Play;
        case 'locked':
            return Lock;
        default:
            return BookOpen;
    }
};

const getStatusColor = (status: CourseProgressStatus) => {
    const colors: Record<CourseProgressStatus, { bg: string; border: string; icon: string }> = {
        completed: {
            bg: 'bg-green-100 dark:bg-green-900',
            border: 'border-green-500',
            icon: 'text-green-600 dark:text-green-400',
        },
        in_progress: {
            bg: 'bg-yellow-100 dark:bg-yellow-900',
            border: 'border-yellow-500',
            icon: 'text-yellow-600 dark:text-yellow-400',
        },
        available: {
            bg: 'bg-blue-100 dark:bg-blue-900',
            border: 'border-blue-500',
            icon: 'text-blue-600 dark:text-blue-400',
        },
        locked: {
            bg: 'bg-gray-100 dark:bg-gray-800',
            border: 'border-gray-300 dark:border-gray-600',
            icon: 'text-gray-400 dark:text-gray-500',
        },
    };
    return colors[status];
};

const getLineColor = (status: CourseProgressStatus) => {
    return status === 'completed' ? 'bg-green-500' : 'bg-gray-200 dark:bg-gray-700';
};

const getActionLabel = (status: CourseProgressStatus) => {
    switch (status) {
        case 'completed':
            return 'Ulangi';
        case 'in_progress':
            return 'Lanjutkan';
        case 'available':
            return 'Mulai';
        case 'locked':
            return 'Terkunci';
    }
};

const getActionVariant = (status: CourseProgressStatus): 'default' | 'secondary' | 'outline' | 'ghost' => {
    switch (status) {
        case 'in_progress':
            return 'default';
        case 'available':
            return 'secondary';
        case 'completed':
            return 'outline';
        default:
            return 'ghost';
    }
};
</script>

<template>
    <div class="space-y-0">
        <div
            v-for="(course, index) in courses"
            :key="course.course_id"
            class="relative"
        >
            <!-- Connecting Line -->
            <div
                v-if="index < courses.length - 1"
                class="absolute left-5 top-12 w-0.5 h-full -translate-x-1/2"
                :class="getLineColor(courses[index + 1].status)"
            />

            <!-- Course Item -->
            <div class="flex gap-4 pb-6">
                <!-- Status Icon -->
                <div
                    class="relative z-10 flex h-10 w-10 items-center justify-center rounded-full border-2 shrink-0"
                    :class="[getStatusColor(course.status).bg, getStatusColor(course.status).border]"
                >
                    <component
                        :is="getStatusIcon(course.status)"
                        class="h-5 w-5"
                        :class="getStatusColor(course.status).icon"
                    />
                </div>

                <!-- Course Content -->
                <div class="flex-1 min-w-0 pt-1">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <!-- Course Title -->
                            <div class="flex items-center gap-2">
                                <h3
                                    class="font-medium"
                                    :class="{ 'text-muted-foreground': course.status === 'locked' }"
                                >
                                    {{ course.course_title }}
                                </h3>
                                <Badge v-if="course.is_required" variant="outline" class="text-xs">
                                    Wajib
                                </Badge>
                            </div>

                            <!-- Course Metadata -->
                            <div class="flex flex-wrap items-center gap-3 mt-1 text-xs text-muted-foreground">
                                <span class="flex items-center gap-1">
                                    <BookOpen class="h-3 w-3" />
                                    {{ course.completed_lessons }}/{{ course.lessons_count }} materi
                                </span>
                                <span class="flex items-center gap-1">
                                    <Clock class="h-3 w-3" />
                                    {{ formatDuration(course.estimated_duration_minutes, 'short') }}
                                </span>
                                <span v-if="course.time_spent_minutes > 0" class="flex items-center gap-1">
                                    Waktu: {{ formatDuration(course.time_spent_minutes, 'short') }}
                                </span>
                            </div>

                            <!-- Progress Bar (for in-progress courses) -->
                            <div v-if="course.status === 'in_progress'" class="mt-2 max-w-xs">
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="text-muted-foreground">Progres</span>
                                    <span class="font-medium">{{ course.completion_percentage }}%</span>
                                </div>
                                <Progress :model-value="course.completion_percentage" class="h-2" />
                            </div>

                            <!-- Lock Reason -->
                            <p
                                v-if="course.status === 'locked' && course.lock_reason"
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                {{ course.lock_reason }}
                            </p>
                        </div>

                        <!-- Action Button -->
                        <div class="shrink-0">
                            <Link
                                v-if="course.status !== 'locked'"
                                :href="`/courses/${course.course_id}`"
                            >
                                <Button size="sm" :variant="getActionVariant(course.status)">
                                    <RotateCcw v-if="course.status === 'completed'" class="mr-1 h-4 w-4" />
                                    <Play v-else-if="course.status === 'in_progress'" class="mr-1 h-4 w-4" />
                                    {{ getActionLabel(course.status) }}
                                    <ChevronRight class="ml-1 h-4 w-4" />
                                </Button>
                            </Link>
                            <Button
                                v-else
                                size="sm"
                                variant="ghost"
                                disabled
                            >
                                <Lock class="mr-1 h-4 w-4" />
                                Terkunci
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
