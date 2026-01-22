<script setup lang="ts">
// =============================================================================
// Learning Path Show Page
// View learning path details with enrollment/progress
// =============================================================================

import Navbar from '@/components/home/Navbar.vue';
import Footer from '@/components/home/Footer.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Route,
    Clock,
    BookOpen,
    Users,
    Play,
    Lock,
    CheckCircle,
    ChevronRight,
    Target,
    AlertCircle,
    User,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { formatDuration, difficultyLabel, DIFFICULTY_COLORS } from '@/lib/utils';
import type { DifficultyLevel } from '@/types';
import type {
    LearningPathDetail,
    LearningPathEnrollment,
    PathProgressData,
    CourseProgressItem,
    CourseProgressStatus,
    COURSE_PROGRESS_STATUS_COLORS,
} from '@/types/learning-path';

// =============================================================================
// Types
// =============================================================================

interface Props {
    learningPath: LearningPathDetail;
    enrollment: LearningPathEnrollment | null;
    progress: PathProgressData | null;
    canEnroll: boolean;
}

const props = defineProps<Props>();

const page = usePage();
const appName = computed(() => page.props.name || 'E-Learning');

const isEnrolling = ref(false);

// =============================================================================
// Computed
// =============================================================================

const isEnrolled = computed(() => props.enrollment !== null);
const isCompleted = computed(() => props.enrollment?.state === 'completed');
const isActive = computed(() => props.enrollment?.state === 'active');

const progressPercentage = computed(() => props.progress?.overall_percentage ?? 0);
const completedCourses = computed(() => props.progress?.completed_courses ?? 0);
const totalCourses = computed(() => props.progress?.total_courses ?? props.learningPath.courses_count);

// =============================================================================
// Methods
// =============================================================================

const getDifficultyColor = (level: DifficultyLevel) => {
    const colors = DIFFICULTY_COLORS[level];
    return colors ? `${colors.bg} ${colors.text}` : '';
};

const enroll = () => {
    isEnrolling.value = true;
    router.post(`/learner/learning-paths/${props.learningPath.id}/enroll`, {}, {
        preserveScroll: true,
        onFinish: () => {
            isEnrolling.value = false;
        },
    });
};

const getCourseStatus = (courseId: number): CourseProgressItem | null => {
    if (!props.progress) return null;
    return props.progress.courses.find(c => c.course_id === courseId) ?? null;
};

const getCourseStatusIcon = (status: CourseProgressStatus | null) => {
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

const getCourseStatusColor = (status: CourseProgressStatus | null) => {
    const colors: Record<CourseProgressStatus, string> = {
        locked: 'text-gray-400',
        available: 'text-blue-500',
        in_progress: 'text-yellow-500',
        completed: 'text-green-500',
    };
    return status ? colors[status] : 'text-muted-foreground';
};

const getCourseActionLabel = (status: CourseProgressStatus | null) => {
    switch (status) {
        case 'completed':
            return 'Selesai';
        case 'in_progress':
            return 'Lanjutkan';
        case 'available':
            return 'Mulai';
        case 'locked':
            return 'Terkunci';
        default:
            return 'Lihat';
    }
};
</script>

<template>
    <Head :title="learningPath.title" />

    <div class="min-h-screen bg-background">
        <Navbar :app-name="appName" />

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Breadcrumb -->
            <nav class="mb-6 text-sm text-muted-foreground">
                <ol class="flex items-center gap-2">
                    <li>
                        <Link href="/learner/learning-paths" class="hover:text-foreground">
                            Learning Path
                        </Link>
                    </li>
                    <ChevronRight class="h-4 w-4" />
                    <li class="text-foreground font-medium truncate max-w-[200px] sm:max-w-none">
                        {{ learningPath.title }}
                    </li>
                </ol>
            </nav>

            <!-- Main Content Grid -->
            <div class="grid gap-8 lg:grid-cols-3">
                <!-- Left Column - Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Header Section -->
                    <div>
                        <!-- Badges -->
                        <div class="flex flex-wrap gap-2 mb-4">
                            <Badge :class="getDifficultyColor(learningPath.difficulty_level)">
                                {{ difficultyLabel(learningPath.difficulty_level) }}
                            </Badge>
                            <Badge v-if="isCompleted" class="bg-green-600 text-white">
                                <CheckCircle class="mr-1 h-3 w-3" />
                                Selesai
                            </Badge>
                            <Badge v-else-if="isEnrolled" class="bg-blue-600 text-white">
                                Terdaftar
                            </Badge>
                        </div>

                        <!-- Title -->
                        <h1 class="text-3xl font-bold mb-4">{{ learningPath.title }}</h1>

                        <!-- Description -->
                        <p v-if="learningPath.description" class="text-muted-foreground text-lg">
                            {{ learningPath.description }}
                        </p>
                    </div>

                    <!-- Thumbnail -->
                    <div v-if="learningPath.thumbnail_url" class="aspect-video rounded-lg overflow-hidden bg-muted">
                        <img
                            :src="learningPath.thumbnail_url"
                            :alt="learningPath.title"
                            class="w-full h-full object-cover"
                        />
                    </div>
                    <div v-else class="aspect-video rounded-lg bg-muted flex items-center justify-center">
                        <Route class="h-24 w-24 text-muted-foreground" />
                    </div>

                    <!-- Learning Objectives -->
                    <Card v-if="learningPath.learning_objectives?.length">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-lg">
                                <Target class="h-5 w-5" />
                                Tujuan Pembelajaran
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul class="space-y-2">
                                <li
                                    v-for="(objective, index) in learningPath.learning_objectives"
                                    :key="index"
                                    class="flex items-start gap-2"
                                >
                                    <CheckCircle class="h-4 w-4 mt-1 text-green-500 shrink-0" />
                                    <span>{{ objective }}</span>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>

                    <!-- Courses List -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-lg">
                                <BookOpen class="h-5 w-5" />
                                Daftar Kursus ({{ learningPath.courses_count }})
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div class="divide-y">
                                <div
                                    v-for="(course, index) in learningPath.courses"
                                    :key="course.id"
                                    class="flex items-center gap-4 p-4 hover:bg-muted/50 transition-colors"
                                >
                                    <!-- Course Number -->
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-muted text-sm font-medium shrink-0">
                                        {{ index + 1 }}
                                    </div>

                                    <!-- Course Status Icon -->
                                    <component
                                        :is="getCourseStatusIcon(getCourseStatus(course.course_id)?.status ?? null)"
                                        :class="[
                                            'h-5 w-5 shrink-0',
                                            getCourseStatusColor(getCourseStatus(course.course_id)?.status ?? null)
                                        ]"
                                    />

                                    <!-- Course Info -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <h3 class="font-medium truncate">{{ course.title }}</h3>
                                            <Badge v-if="course.is_required" variant="outline" class="text-xs shrink-0">
                                                Wajib
                                            </Badge>
                                        </div>
                                        <div class="flex items-center gap-3 text-xs text-muted-foreground mt-1">
                                            <span class="flex items-center gap-1">
                                                <BookOpen class="h-3 w-3" />
                                                {{ course.lessons_count }} materi
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <Clock class="h-3 w-3" />
                                                {{ formatDuration(course.estimated_duration_minutes, 'short') }}
                                            </span>
                                        </div>
                                        <!-- Progress bar for in-progress courses -->
                                        <div
                                            v-if="getCourseStatus(course.course_id)?.status === 'in_progress'"
                                            class="mt-2"
                                        >
                                            <Progress
                                                :model-value="getCourseStatus(course.course_id)?.completion_percentage ?? 0"
                                                class="h-1"
                                            />
                                        </div>
                                    </div>

                                    <!-- Course Action -->
                                    <div class="shrink-0">
                                        <span
                                            v-if="getCourseStatus(course.course_id)?.status === 'locked'"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ getCourseStatus(course.course_id)?.lock_reason || 'Selesaikan kursus sebelumnya' }}
                                        </span>
                                        <Link
                                            v-else-if="isEnrolled"
                                            :href="`/courses/${course.course_id}`"
                                        >
                                            <Button size="sm" variant="ghost">
                                                {{ getCourseActionLabel(getCourseStatus(course.course_id)?.status ?? 'available') }}
                                                <ChevronRight class="ml-1 h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <span v-else class="text-xs text-muted-foreground">
                                            Daftar untuk mengakses
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right Column - Sidebar -->
                <div class="space-y-6">
                    <!-- Enrollment Card -->
                    <Card class="sticky top-4">
                        <CardContent class="p-6">
                            <!-- Progress Display (if enrolled) -->
                            <div v-if="isEnrolled && progress" class="mb-6">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium">Progres Anda</span>
                                    <span class="text-2xl font-bold">{{ progressPercentage }}%</span>
                                </div>
                                <Progress :model-value="progressPercentage" class="h-3 mb-2" />
                                <p class="text-sm text-muted-foreground">
                                    {{ completedCourses }} dari {{ totalCourses }} kursus selesai
                                </p>
                            </div>

                            <!-- Action Button -->
                            <div v-if="!isEnrolled">
                                <Button
                                    v-if="canEnroll"
                                    class="w-full"
                                    size="lg"
                                    :disabled="isEnrolling"
                                    @click="enroll"
                                >
                                    <Play v-if="!isEnrolling" class="mr-2 h-5 w-5" />
                                    {{ isEnrolling ? 'Mendaftar...' : 'Daftar Sekarang' }}
                                </Button>
                                <p v-else class="text-center text-muted-foreground">
                                    Anda tidak dapat mendaftar ke learning path ini
                                </p>
                            </div>
                            <div v-else-if="isActive">
                                <Link :href="`/learner/learning-paths/${learningPath.id}/progress`">
                                    <Button class="w-full" size="lg">
                                        <Play class="mr-2 h-5 w-5" />
                                        Lanjutkan Belajar
                                    </Button>
                                </Link>
                            </div>
                            <div v-else-if="isCompleted">
                                <Link :href="`/learner/learning-paths/${learningPath.id}/progress`">
                                    <Button class="w-full" variant="outline" size="lg">
                                        <CheckCircle class="mr-2 h-5 w-5" />
                                        Lihat Progress
                                    </Button>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Metadata Card -->
                    <Card>
                        <CardContent class="p-6 space-y-4">
                            <div class="flex items-center gap-3">
                                <BookOpen class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Jumlah Kursus</p>
                                    <p class="font-medium">{{ learningPath.courses_count }} kursus</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <Clock class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Estimasi Durasi</p>
                                    <p class="font-medium">{{ formatDuration(learningPath.estimated_duration, 'long') }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <Users class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Peserta</p>
                                    <p class="font-medium">{{ learningPath.enrollments_count }} orang</p>
                                </div>
                            </div>
                            <div v-if="learningPath.creator" class="flex items-center gap-3">
                                <User class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Dibuat oleh</p>
                                    <p class="font-medium">{{ learningPath.creator.name }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Prerequisites Card -->
                    <Card v-if="learningPath.prerequisites?.length">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <AlertCircle class="h-5 w-5" />
                                Prasyarat
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul class="space-y-2 text-sm">
                                <li
                                    v-for="(prereq, index) in learningPath.prerequisites"
                                    :key="index"
                                    class="flex items-start gap-2"
                                >
                                    <ChevronRight class="h-4 w-4 mt-0.5 text-muted-foreground shrink-0" />
                                    <span>{{ prereq }}</span>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </main>

        <Footer :app-name="appName" />
    </div>
</template>
