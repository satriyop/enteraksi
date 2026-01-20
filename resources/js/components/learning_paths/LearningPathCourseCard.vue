<script setup lang="ts">
// =============================================================================
// LearningPathCourseCard Component
// Displays a course card within a learning path with progress
// =============================================================================

import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Link } from '@inertiajs/vue3';
import { Eye, BookOpen, Layers, CheckCircle, AlertCircle } from 'lucide-vue-next';
import type { DifficultyLevel } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface CoursePivot {
    is_required: boolean;
    min_completion_percentage: number;
    prerequisites: string | null;
}

interface LearningPathCourse {
    id: number;
    title: string;
    description: string | null;
    slug: string;
    estimated_duration: number;
    difficulty_level: DifficultyLevel;
    thumbnail_url: string | null;
    sections_count: number;
    enrollments_count: number;
    pivot: CoursePivot;
}

interface Props {
    course: LearningPathCourse;
    index: number;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

// Placeholder for actual progress calculation
const getProgress = () => {
    return Math.min(100, Math.floor(Math.random() * 100));
};
</script>

<template>
    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <div class="flex justify-between items-start mb-3">
            <h4 class="text-lg font-medium">
                {{ index + 1 }}. {{ course.title }}
            </h4>
            <div class="flex items-center gap-2">
                <Badge v-if="course.pivot.is_required" variant="destructive">
                    Wajib
                </Badge>
                <Badge v-else variant="secondary">
                    Opsional
                </Badge>
            </div>
        </div>

        <div class="mb-3">
            <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                <div
                    class="bg-blue-600 h-2.5 rounded-full"
                    :style="{ width: getProgress() + '%' }"
                ></div>
            </div>
            <p class="text-sm text-muted-foreground mt-1">
                {{ getProgress() }}% Selesai
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-muted-foreground">
                    {{ course.description || 'No description available.' }}
                </p>
            </div>
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <Layers class="h-4 w-4" />
                    <span class="font-medium">Sections:</span>
                    <span>{{ course.sections_count || 0 }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="font-medium">Enrollments:</span>
                    <span>{{ course.enrollments_count || 0 }}</span>
                </div>
                <div v-if="course.pivot.min_completion_percentage" class="flex items-center gap-2">
                    <CheckCircle class="h-4 w-4" />
                    <span class="font-medium">Persentase Penyelesaian Minimum:</span>
                    <span>{{ course.pivot.min_completion_percentage }}%</span>
                </div>
                <div v-if="course.pivot.prerequisites" class="flex items-center gap-2">
                    <AlertCircle class="h-4 w-4" />
                    <span class="font-medium">Prasyarat:</span>
                    <span>{{ course.pivot.prerequisites }}</span>
                </div>
            </div>
        </div>

        <div class="mt-4 flex gap-2">
            <Link :href="'/courses/' + course.id">
                <Button variant="outline" size="sm" class="gap-2">
                    <Eye class="h-4 w-4" />
                    Lihat Kursus
                </Button>
            </Link>
            <Link :href="'/courses/' + course.id + '/lessons'">
                <Button size="sm" class="gap-2">
                    <BookOpen class="h-4 w-4" />
                    Mulai Belajar
                </Button>
            </Link>
        </div>
    </div>
</template>
