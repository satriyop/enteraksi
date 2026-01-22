<script setup lang="ts">
// =============================================================================
// Learning Path Progress Page
// Detailed progress view for enrolled learning path
// =============================================================================

import Navbar from '@/components/home/Navbar.vue';
import Footer from '@/components/home/Footer.vue';
import CourseProgressTimeline from '@/components/learning_paths/CourseProgressTimeline.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    DialogClose,
} from '@/components/ui/dialog';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Route,
    Clock,
    BookOpen,
    CheckCircle,
    ChevronRight,
    XCircle,
    Play,
    Award,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { formatDuration, formatDate } from '@/lib/utils';
import type {
    LearningPathDetail,
    LearningPathEnrollment,
    PathProgressData,
} from '@/types/learning-path';

// =============================================================================
// Types
// =============================================================================

interface Props {
    learningPath: LearningPathDetail;
    enrollment: LearningPathEnrollment;
    progress: PathProgressData;
}

const props = defineProps<Props>();

const page = usePage();
const appName = computed(() => page.props.name || 'E-Learning');

const isDropping = ref(false);
const showDropDialog = ref(false);

// =============================================================================
// Computed
// =============================================================================

const isCompleted = computed(() => props.enrollment.state === 'completed');
const isActive = computed(() => props.enrollment.state === 'active');

// =============================================================================
// Methods
// =============================================================================

const dropEnrollment = () => {
    isDropping.value = true;
    router.delete(`/learner/learning-paths/${props.learningPath.id}/drop`, {
        preserveScroll: true,
        onFinish: () => {
            isDropping.value = false;
            showDropDialog.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`Progres - ${learningPath.title}`" />

    <div class="min-h-screen bg-background">
        <Navbar :app-name="appName" />

        <main class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Breadcrumb -->
            <nav class="mb-6 text-sm text-muted-foreground">
                <ol class="flex items-center gap-2">
                    <li>
                        <Link href="/learner/learning-paths" class="hover:text-foreground">
                            Learning Path
                        </Link>
                    </li>
                    <ChevronRight class="h-4 w-4" />
                    <li>
                        <Link :href="`/learner/learning-paths/${learningPath.id}`" class="hover:text-foreground">
                            {{ learningPath.title }}
                        </Link>
                    </li>
                    <ChevronRight class="h-4 w-4" />
                    <li class="text-foreground font-medium">
                        Progres
                    </li>
                </ol>
            </nav>

            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Route class="h-7 w-7" />
                    {{ learningPath.title }}
                </h1>
            </div>

            <!-- Completion Banner -->
            <Card v-if="isCompleted" class="mb-6 border-green-500 bg-green-50 dark:bg-green-950">
                <CardContent class="flex items-center gap-4 p-6">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                        <Award class="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-green-700 dark:text-green-300">
                            Selamat! Anda telah menyelesaikan Learning Path ini
                        </h2>
                        <p class="text-sm text-green-600 dark:text-green-400">
                            Diselesaikan pada {{ formatDate(enrollment.completed_at!) }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Overall Progress Summary -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle class="text-lg">Ringkasan Progres</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <!-- Progress Percentage -->
                        <div class="text-center sm:text-left">
                            <p class="text-sm text-muted-foreground mb-1">Progres Keseluruhan</p>
                            <div class="flex items-center gap-3">
                                <span class="text-4xl font-bold">{{ progress.overall_percentage }}%</span>
                            </div>
                            <Progress :model-value="progress.overall_percentage" class="h-2 mt-2" />
                        </div>

                        <!-- Courses Completed -->
                        <div class="text-center sm:text-left">
                            <p class="text-sm text-muted-foreground mb-1">Kursus Selesai</p>
                            <div class="flex items-center gap-2">
                                <CheckCircle class="h-5 w-5 text-green-500" />
                                <span class="text-2xl font-bold">
                                    {{ progress.completed_courses }}/{{ progress.total_courses }}
                                </span>
                            </div>
                        </div>

                        <!-- In Progress -->
                        <div class="text-center sm:text-left">
                            <p class="text-sm text-muted-foreground mb-1">Sedang Dikerjakan</p>
                            <div class="flex items-center gap-2">
                                <Play class="h-5 w-5 text-yellow-500" />
                                <span class="text-2xl font-bold">{{ progress.in_progress_courses }}</span>
                            </div>
                        </div>

                        <!-- Time Spent -->
                        <div class="text-center sm:text-left">
                            <p class="text-sm text-muted-foreground mb-1">Waktu Belajar</p>
                            <div class="flex items-center gap-2">
                                <Clock class="h-5 w-5 text-blue-500" />
                                <span class="text-2xl font-bold">
                                    {{ formatDuration(progress.total_time_spent_minutes, 'short') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Courses Timeline -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <BookOpen class="h-5 w-5" />
                        Progres Kursus
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <CourseProgressTimeline :courses="progress.courses" />
                </CardContent>
            </Card>

            <!-- Actions -->
            <div class="flex flex-col gap-4 sm:flex-row sm:justify-between">
                <Link :href="`/learner/learning-paths/${learningPath.id}`">
                    <Button variant="outline">
                        <ChevronRight class="mr-2 h-4 w-4 rotate-180" />
                        Kembali ke Detail
                    </Button>
                </Link>

                <!-- Drop Enrollment (only for active enrollments) -->
                <Dialog v-if="isActive" v-model:open="showDropDialog">
                    <DialogTrigger as-child>
                        <Button variant="destructive" :disabled="isDropping">
                            <XCircle class="mr-2 h-4 w-4" />
                            Hentikan Pendaftaran
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Hentikan Pendaftaran?</DialogTitle>
                            <DialogDescription>
                                Apakah Anda yakin ingin menghentikan pendaftaran di learning path ini?
                                Progres Anda akan disimpan, tetapi Anda tidak dapat melanjutkan belajar
                                kecuali mendaftar ulang.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose as-child>
                                <Button variant="outline">Batal</Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                :disabled="isDropping"
                                @click="dropEnrollment"
                            >
                                {{ isDropping ? 'Menghentikan...' : 'Ya, Hentikan' }}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </main>

        <Footer :app-name="appName" />
    </div>
</template>
