<script setup lang="ts">
import { show, edit } from '@/actions/App/Http/Controllers/LearningPathController';
import PageHeader from '@/components/crud/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft, Pencil, Eye, BookOpen, Clock, Layers, CheckCircle, AlertCircle } from 'lucide-vue-next';

interface Course {
    id: number;
    title: string;
    description: string;
    slug: string;
    estimated_duration: number;
    difficulty_level: string;
    thumbnail_url: string;
    sections_count: number;
    enrollments_count: number;
    pivot: {
        is_required: boolean;
        min_completion_percentage: number;
        prerequisites: string | null;
    };
}

interface LearningPath {
    id: number;
    title: string;
    description: string;
    objectives: string[];
    slug: string;
    estimated_duration: number;
    difficulty_level: string;
    thumbnail_url: string;
    is_published: boolean;
    creator: {
        name: string;
    };
    courses: Course[];
}

interface Props {
    learningPath: LearningPath;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Jalur Pembelajaran',
        href: '/learning-paths',
    },
    {
        title: props.learningPath.title,
        href: show(props.learningPath.id).url,
    },
];

const formatDuration = (minutes: number | null | undefined) => {
    if (!minutes) return 'Not specified';
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins}m`;
};

const getProgress = (course: Course | any) => {
    // This would be replaced with actual progress calculation
    return Math.min(100, Math.floor(Math.random() * 100));
};

const difficultyLabel = (level: string) => {
    switch (level) {
        case 'beginner':
            return 'Beginner';
        case 'intermediate':
            return 'Intermediate';
        case 'advanced':
            return 'Advanced';
        case 'expert':
            return 'Expert';
        default:
            return level;
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="learningPath.title" />

        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="'/learning-paths'">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-2xl font-bold">{{ learningPath.title }}</h1>
                            <Badge v-if="learningPath.is_published" variant="default">
                                Terbit
                            </Badge>
                            <Badge v-else variant="secondary">
                                Draft
                            </Badge>
                        </div>
                        <p class="text-muted-foreground">Detail Jalur Pembelajaran</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link :href="edit(props.learningPath.id).url">
                        <Button variant="outline" class="gap-2">
                            <Pencil class="h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Ringkasan Jalur Pembelajaran</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h3 class="text-lg font-medium mb-2">Deskripsi</h3>
                            <p class="text-muted-foreground">
                                {{ learningPath.description || 'Tidak ada deskripsi yang disediakan.' }}
                            </p>
                        </div>

                        <div>
                            <h3 class="text-lg font-medium mb-2">Detail</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">Dibuat oleh:</span>
                                    <span>{{ learningPath.creator?.name || 'Tidak ada' }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Clock class="h-4 w-4" />
                                    <span class="font-medium">Durasi Perkiraan:</span>
                                    <span>{{ formatDuration(learningPath.estimated_duration) }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">Tingkat Kesulitan:</span>
                                    <Badge variant="outline">{{ difficultyLabel(learningPath.difficulty_level) }}</Badge>
                                </div>
                                <div class="flex items-center gap-2">
                                    <BookOpen class="h-4 w-4" />
                                    <span class="font-medium">Jumlah Kursus:</span>
                                    <span>{{ learningPath.courses.length }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="learningPath.objectives && learningPath.objectives.length > 0" class="mb-6">
                        <h3 class="text-lg font-medium mb-2">Tujuan Pembelajaran</h3>
                        <ul class="list-disc list-inside space-y-1 text-muted-foreground">
                            <li v-for="(objective, index) in learningPath.objectives" :key="index">
                                {{ objective }}
                            </li>
                        </ul>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Kursus dalam Jalur Pembelajaran</CardTitle>
                    <CardDescription>Lengkapi kursus-kursus ini untuk menyelesaikan jalur pembelajaran</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <div
                            v-for="(course, index) in learningPath.courses"
                            :key="course.id"
                            class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
                        >
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
                                        :style="{ width: getProgress(course) + '%' }"
                                    ></div>
                                </div>
                                <p class="text-sm text-muted-foreground mt-1">
                                    {{ getProgress(course) }}% Selesai
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
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Add any component-specific styles here */
</style>