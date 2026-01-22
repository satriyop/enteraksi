<script setup lang="ts">
// =============================================================================
// BrowseCourseCard Component
// Displays a course card for browsing/discovery with full details
// =============================================================================

import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ProgressBar } from '@/components/features/shared';
import { Clock, Users, BookOpen, CheckCircle, PlayCircle, RotateCcw } from 'lucide-vue-next';
import { formatDuration, difficultyLabel, DIFFICULTY_COLORS } from '@/lib/utils';
import type { DifficultyLevel, UserSummary, Category } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface BrowseCourse {
    id: number;
    title: string;
    slug: string;
    short_description: string;
    thumbnail_path: string | null;
    difficulty_level: DifficultyLevel;
    estimated_duration_minutes: number;
    manual_duration_minutes: number | null;
    user: UserSummary;
    category: Category | null;
    lessons_count: number;
    enrollments_count: number;
}

interface EnrollmentStatus {
    status: 'active' | 'completed' | 'dropped';
    progress_percentage: number;
}

interface Props {
    course: BrowseCourse;
    enrollment?: EnrollmentStatus | null;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    enrollment: null,
});

// =============================================================================
// Computed
// =============================================================================

const isActive = computed(() => props.enrollment?.status === 'active');
const isCompleted = computed(() => props.enrollment?.status === 'completed');
const isDropped = computed(() => props.enrollment?.status === 'dropped');
const isEnrolled = computed(() => !!props.enrollment);

const getDifficultyColor = (level: DifficultyLevel) => {
    const colors = DIFFICULTY_COLORS[level];
    return colors ? `${colors.bg} ${colors.text}` : '';
};

const getCourseDuration = () => {
    const minutes = props.course.manual_duration_minutes ?? props.course.estimated_duration_minutes ?? 0;
    return formatDuration(minutes, 'long');
};

const ctaLabel = computed(() => {
    if (isCompleted.value) return 'Tinjau Kembali';
    if (isDropped.value) return 'Lanjutkan Belajar';
    if (isActive.value) return 'Lanjutkan';
    return 'Lihat Detail';
});

const ctaVariant = computed(() => {
    if (isActive.value) return 'default';
    return 'outline';
});
</script>

<template>
    <Card class="group overflow-hidden">
        <Link :href="`/courses/${course.id}`">
            <div class="relative aspect-video bg-muted">
                <img
                    v-if="course.thumbnail_path"
                    :src="`/storage/${course.thumbnail_path}`"
                    :alt="course.title"
                    class="h-full w-full object-cover transition-transform group-hover:scale-105"
                />
                <div v-else class="flex h-full items-center justify-center">
                    <BookOpen class="h-12 w-12 text-muted-foreground" />
                </div>
                <!-- Difficulty Badge -->
                <Badge
                    class="absolute left-2 top-2"
                    :class="getDifficultyColor(course.difficulty_level)"
                >
                    {{ difficultyLabel(course.difficulty_level) }}
                </Badge>
                <!-- Enrollment Status Badge -->
                <Badge
                    v-if="isCompleted"
                    class="absolute right-2 top-2 bg-amber-500 text-white"
                >
                    <CheckCircle class="mr-1 h-3 w-3" />
                    Selesai
                </Badge>
                <Badge
                    v-else-if="isActive"
                    class="absolute right-2 top-2 bg-green-500 text-white"
                >
                    <PlayCircle class="mr-1 h-3 w-3" />
                    Sedang Belajar
                </Badge>
                <Badge
                    v-else-if="isDropped"
                    class="absolute right-2 top-2 bg-orange-500 text-white"
                >
                    <RotateCcw class="mr-1 h-3 w-3" />
                    Dibatalkan
                </Badge>
            </div>
        </Link>
        <CardContent class="p-4">
            <Link :href="`/courses/${course.id}`">
                <h3 class="font-semibold line-clamp-2 hover:text-primary">
                    {{ course.title }}
                </h3>
            </Link>
            <p class="mt-1 text-sm text-muted-foreground line-clamp-2">
                {{ course.short_description }}
            </p>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ course.user.name }}
            </p>
            <!-- Progress bar for enrolled users -->
            <div v-if="isActive || isDropped" class="mt-2">
                <div class="flex items-center justify-between text-xs text-muted-foreground mb-1">
                    <span>Progress</span>
                    <span>{{ enrollment?.progress_percentage || 0 }}%</span>
                </div>
                <ProgressBar
                    :value="enrollment?.progress_percentage || 0"
                    size="sm"
                    :class="{ 'opacity-50': isDropped }"
                />
            </div>
            <div class="mt-3 flex items-center gap-3 text-xs text-muted-foreground">
                <span class="flex items-center gap-1">
                    <Clock class="h-3 w-3" />
                    {{ getCourseDuration() }}
                </span>
                <span class="flex items-center gap-1">
                    <BookOpen class="h-3 w-3" />
                    {{ course.lessons_count }} materi
                </span>
                <span v-if="!isEnrolled" class="flex items-center gap-1">
                    <Users class="h-3 w-3" />
                    {{ course.enrollments_count }}
                </span>
            </div>
            <Link :href="`/courses/${course.id}`" class="mt-4 block">
                <Button class="w-full" :variant="ctaVariant" size="sm">
                    {{ ctaLabel }}
                </Button>
            </Link>
        </CardContent>
    </Card>
</template>
