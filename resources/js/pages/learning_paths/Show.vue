<script setup lang="ts">
import { show, edit } from '@/actions/App/Http/Controllers/LearningPathController';
import PageHeader from '@/components/crud/PageHeader.vue';
import LearningPathCourseCard from '@/components/learning_paths/LearningPathCourseCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, DifficultyLevel } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, BookOpen, Clock } from 'lucide-vue-next';

// =============================================================================
// Page-Specific Types
// =============================================================================

/** Course pivot data from learning path relationship */
interface CoursePivot {
    is_required: boolean;
    min_completion_percentage: number;
    prerequisites: string | null;
}

/** Course in learning path with pivot data */
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

/** Creator info */
interface Creator {
    name: string;
}

/** Full learning path details for show page */
interface LearningPathDetails {
    id: number;
    title: string;
    description: string | null;
    objectives: string[];
    slug: string;
    estimated_duration: number;
    difficulty_level: DifficultyLevel | 'expert';
    thumbnail_url: string | null;
    is_published: boolean;
    creator: Creator | null;
    courses: LearningPathCourse[];
}

interface Props {
    learningPath: LearningPathDetails;
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
                        <LearningPathCourseCard
                            v-for="(course, index) in learningPath.courses"
                            :key="course.id"
                            :course="course"
                            :index="index"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>