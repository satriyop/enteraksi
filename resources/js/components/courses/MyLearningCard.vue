<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { BookOpen, Play, Clock, CheckCircle } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';

interface Props {
    course: {
        id: number;
        title: string;
        slug: string;
        thumbnail_path: string | null;
        instructor: string;
        progress_percentage?: number;
        last_lesson_id?: number | null;
        duration: number;
        difficulty_level: 'beginner' | 'intermediate' | 'advanced';
        lessons_count?: number;
    };
}

const props = defineProps<Props>();

const formatDuration = (minutes: number) => {
    if (!minutes) return '-';
    if (minutes < 60) return `${minutes} menit`;
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    if (remainingMinutes === 0) return `${hours} jam`;
    return `${hours}j ${remainingMinutes}m`;
};

const difficultyLabel = (level: string) => {
    const labels: Record<string, string> = {
        beginner: 'Pemula',
        intermediate: 'Menengah',
        advanced: 'Lanjutan',
    };
    return labels[level] || level;
};

const difficultyColor = (level: string) => {
    const colors: Record<string, string> = {
        beginner: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        intermediate: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        advanced: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    };
    return colors[level] || '';
};
</script>

<template>
    <Card class="group overflow-hidden hover:shadow-lg transition-shadow">
        <div class="relative aspect-video bg-muted">
            <img
                v-if="course.thumbnail_path"
                :src="course.thumbnail_path"
                :alt="course.title"
                class="h-full w-full object-cover"
            />
            <div v-else class="flex h-full items-center justify-center">
                <BookOpen class="h-12 w-12 text-muted-foreground" />
            </div>
            
            <!-- Progress Overlay -->
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-muted">
                <div
                    class="h-full bg-primary transition-all"
                    :style="{ width: `${course.progress_percentage || 0}%` }"
                />
            </div>
            
            <!-- Completion Badge -->
            <div
                v-if="(course.progress_percentage || 0) >= 100"
                class="absolute top-2 right-2"
            >
                <Badge variant="secondary" class="gap-1 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                    <CheckCircle class="h-3 w-3" />
                    Selesai
                </Badge>
            </div>
            
            <!-- Play Button -->
            <Link
                :href="course.last_lesson_id ? `/courses/${course.id}/lessons/${course.last_lesson_id}` : `/courses/${course.id}`"
                class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100"
            >
                <div class="rounded-full bg-white p-3">
                    <Play class="h-6 w-6 text-primary" />
                </div>
            </Link>
        </div>
        
        <CardContent class="p-4">
            <Link :href="`/courses/${course.id}`">
                <h3 class="font-semibold line-clamp-2 hover:text-primary">
                    {{ course.title }}
                </h3>
            </Link>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ course.instructor }}
            </p>
            
            <div class="mt-2 flex items-center justify-between text-sm">
                <span class="text-muted-foreground">
                    {{ course.progress_percentage || 0 }}% selesai
                </span>
                <Link
                    :href="course.last_lesson_id ? `/courses/${course.id}/lessons/${course.last_lesson_id}` : `/courses/${course.id}`"
                    class="text-primary hover:underline font-medium"
                >
                    Lanjutkan
                </Link>
            </div>
            
            <!-- Progress Bar -->
            <div class="mt-3">
                <Progress :model-value="course.progress_percentage || 0" class="h-2" />
            </div>
            
            <!-- Course Details -->
            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                <Badge :class="difficultyColor(course.difficulty_level)">
                    {{ difficultyLabel(course.difficulty_level) }}
                </Badge>
                <Badge variant="outline" class="gap-1">
                    <Clock class="h-3 w-3" />
                    {{ formatDuration(course.duration) }}
                </Badge>
                <Badge variant="outline" v-if="course.lessons_count">
                    {{ course.lessons_count }} materi
                </Badge>
            </div>
        </CardContent>
    </Card>
</template>