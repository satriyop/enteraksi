# Phase 4: Learner Experience

> **Phase**: 4 of 6
> **Estimated Effort**: Medium-High
> **Prerequisites**: Phase 3 (Prerequisites and Branching)

---

## Objectives

- Create learner-focused UI pages
- Build "My Learning Paths" dashboard
- Display path progress with course status
- Show locked/unlocked courses with clear messaging
- Implement enroll/drop functionality in UI

---

## 4.1 Page Structure

```
resources/js/pages/learner/paths/
├── Index.vue          # My Learning Paths dashboard
├── Overview.vue       # Path overview (not enrolled)
├── Show.vue           # Path progress (enrolled)
└── components/
    ├── PathProgressCard.vue
    ├── CourseProgressItem.vue
    ├── PathProgressBar.vue
    └── LockedCourseOverlay.vue
```

---

## 4.2 My Learning Paths Index

### File: `resources/js/pages/learner/paths/Index.vue`

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { BookOpen, Clock, Trophy, ArrowRight, GraduationCap } from 'lucide-vue-next';
import PathProgressCard from './components/PathProgressCard.vue';

interface LearningPath {
    id: number;
    title: string;
    description: string | null;
    slug: string;
    thumbnail_url: string | null;
    difficulty_level: string | null;
    estimated_duration: number | null;
    courses_count: number;
}

interface ActivePathEnrollment {
    id: number;
    learning_path: LearningPath;
    status: string;
    enrolled_at: string;
    progress_percentage: number;
    completed_courses: number;
    total_courses: number;
    current_course: {
        id: number;
        title: string;
        status: string;
    } | null;
}

interface CompletedPathEnrollment {
    id: number;
    learning_path: {
        id: number;
        title: string;
        slug: string;
    };
    completed_at: string;
}

interface Props {
    activePaths: ActivePathEnrollment[];
    completedPaths: CompletedPathEnrollment[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Jalur Pembelajaran Saya', href: '/my-learning-paths' },
];

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Jalur Pembelajaran Saya" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Jalur Pembelajaran Saya</h1>
                    <p class="text-muted-foreground">
                        Lacak progress Anda di berbagai jalur pembelajaran
                    </p>
                </div>
                <Link href="/learning-paths">
                    <Button variant="outline" class="gap-2">
                        <BookOpen class="h-4 w-4" />
                        Jelajahi Jalur Lainnya
                    </Button>
                </Link>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
                                <BookOpen class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold">{{ activePaths.length }}</p>
                                <p class="text-sm text-muted-foreground">Jalur Aktif</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-green-100 dark:bg-green-900 rounded-lg">
                                <Trophy class="h-6 w-6 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold">{{ completedPaths.length }}</p>
                                <p class="text-sm text-muted-foreground">Jalur Selesai</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-lg">
                                <GraduationCap class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold">
                                    {{ activePaths.reduce((sum, p) => sum + p.completed_courses, 0) }}
                                </p>
                                <p class="text-sm text-muted-foreground">Kursus Selesai</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Tabs for Active/Completed -->
            <Tabs default-value="active" class="w-full">
                <TabsList>
                    <TabsTrigger value="active">
                        Sedang Berlangsung ({{ activePaths.length }})
                    </TabsTrigger>
                    <TabsTrigger value="completed">
                        Selesai ({{ completedPaths.length }})
                    </TabsTrigger>
                </TabsList>

                <!-- Active Paths Tab -->
                <TabsContent value="active" class="mt-6">
                    <div v-if="activePaths.length === 0" class="text-center py-12">
                        <BookOpen class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium mb-2">Belum Ada Jalur Pembelajaran</h3>
                        <p class="text-muted-foreground mb-4">
                            Mulai perjalanan belajar Anda dengan mendaftar di jalur pembelajaran.
                        </p>
                        <Link href="/learning-paths">
                            <Button>Jelajahi Jalur Pembelajaran</Button>
                        </Link>
                    </div>

                    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <PathProgressCard
                            v-for="enrollment in activePaths"
                            :key="enrollment.id"
                            :enrollment="enrollment"
                        />
                    </div>
                </TabsContent>

                <!-- Completed Paths Tab -->
                <TabsContent value="completed" class="mt-6">
                    <div v-if="completedPaths.length === 0" class="text-center py-12">
                        <Trophy class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium mb-2">Belum Ada Jalur Selesai</h3>
                        <p class="text-muted-foreground">
                            Selesaikan jalur pembelajaran untuk melihatnya di sini.
                        </p>
                    </div>

                    <div v-else class="space-y-4">
                        <Card v-for="enrollment in completedPaths" :key="enrollment.id">
                            <CardContent class="flex items-center justify-between py-4">
                                <div class="flex items-center gap-4">
                                    <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                                        <Trophy class="h-5 w-5 text-green-600 dark:text-green-400" />
                                    </div>
                                    <div>
                                        <h4 class="font-medium">{{ enrollment.learning_path.title }}</h4>
                                        <p class="text-sm text-muted-foreground">
                                            Selesai pada {{ formatDate(enrollment.completed_at) }}
                                        </p>
                                    </div>
                                </div>
                                <Link :href="`/my-learning-paths/${enrollment.learning_path.id}`">
                                    <Button variant="outline" size="sm">
                                        Lihat Detail
                                    </Button>
                                </Link>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
```

---

## 4.3 Path Progress Card Component

### File: `resources/js/pages/learner/paths/components/PathProgressCard.vue`

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { ArrowRight, BookOpen, Clock, Play } from 'lucide-vue-next';

interface Props {
    enrollment: {
        id: number;
        learning_path: {
            id: number;
            title: string;
            description: string | null;
            slug: string;
            thumbnail_url: string | null;
            difficulty_level: string | null;
            estimated_duration: number | null;
            courses_count: number;
        };
        status: string;
        enrolled_at: string;
        progress_percentage: number;
        completed_courses: number;
        total_courses: number;
        current_course: {
            id: number;
            title: string;
            status: string;
        } | null;
    };
}

const props = defineProps<Props>();

const formatDuration = (minutes: number | null) => {
    if (!minutes) return null;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours === 0) return `${mins} menit`;
    if (mins === 0) return `${hours} jam`;
    return `${hours} jam ${mins} menit`;
};

const difficultyLabel = (level: string | null) => {
    if (!level) return null;
    const labels: Record<string, string> = {
        beginner: 'Pemula',
        intermediate: 'Menengah',
        advanced: 'Lanjutan',
        expert: 'Ahli',
    };
    return labels[level] || level;
};

const difficultyColor = (level: string | null) => {
    const colors: Record<string, string> = {
        beginner: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        intermediate: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        advanced: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        expert: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };
    return colors[level || ''] || '';
};
</script>

<template>
    <Card class="hover:shadow-lg transition-shadow">
        <CardHeader class="pb-3">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <CardTitle class="text-lg">
                        {{ enrollment.learning_path.title }}
                    </CardTitle>
                    <p class="text-sm text-muted-foreground line-clamp-2 mt-1">
                        {{ enrollment.learning_path.description || 'Tidak ada deskripsi' }}
                    </p>
                </div>
                <Badge
                    v-if="enrollment.learning_path.difficulty_level"
                    :class="difficultyColor(enrollment.learning_path.difficulty_level)"
                    variant="secondary"
                >
                    {{ difficultyLabel(enrollment.learning_path.difficulty_level) }}
                </Badge>
            </div>
        </CardHeader>

        <CardContent class="space-y-4">
            <!-- Progress Bar -->
            <div>
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-muted-foreground">Progress</span>
                    <span class="font-medium">{{ enrollment.progress_percentage }}%</span>
                </div>
                <Progress :model-value="enrollment.progress_percentage" class="h-2" />
                <p class="text-xs text-muted-foreground mt-1">
                    {{ enrollment.completed_courses }} dari {{ enrollment.total_courses }} kursus selesai
                </p>
            </div>

            <!-- Current Course -->
            <div v-if="enrollment.current_course" class="p-3 bg-muted rounded-lg">
                <p class="text-xs text-muted-foreground mb-1">
                    {{ enrollment.current_course.status === 'in_progress' ? 'Sedang dikerjakan' : 'Selanjutnya' }}
                </p>
                <p class="font-medium text-sm">{{ enrollment.current_course.title }}</p>
            </div>

            <!-- Meta Info -->
            <div class="flex items-center gap-4 text-sm text-muted-foreground">
                <div class="flex items-center gap-1">
                    <BookOpen class="h-4 w-4" />
                    <span>{{ enrollment.total_courses }} kursus</span>
                </div>
                <div v-if="enrollment.learning_path.estimated_duration" class="flex items-center gap-1">
                    <Clock class="h-4 w-4" />
                    <span>{{ formatDuration(enrollment.learning_path.estimated_duration) }}</span>
                </div>
            </div>

            <!-- Action Button -->
            <Link :href="`/my-learning-paths/${enrollment.learning_path.id}`" class="block">
                <Button class="w-full gap-2">
                    <Play class="h-4 w-4" />
                    Lanjutkan Belajar
                    <ArrowRight class="h-4 w-4 ml-auto" />
                </Button>
            </Link>
        </CardContent>
    </Card>
</template>
```

---

## 4.4 Path Progress Show Page (Enrolled)

### File: `resources/js/pages/learner/paths/Show.vue`

```vue
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    CheckCircle,
    Clock,
    Lock,
    Play,
    Trophy,
    XCircle,
} from 'lucide-vue-next';
import CourseProgressItem from './components/CourseProgressItem.vue';

interface CourseProgress {
    courseId: number;
    courseTitle: string;
    status: 'locked' | 'available' | 'in_progress' | 'completed';
    position: number;
    isRequired: boolean;
    completionPercentage: number;
    minRequiredPercentage: number | null;
    prerequisites: number[] | null;
    lockReason: string | null;
    unlockedAt: string | null;
    startedAt: string | null;
    completedAt: string | null;
}

interface Props {
    learningPath: {
        id: number;
        title: string;
        description: string | null;
        objectives: string[] | null;
        slug: string;
        thumbnail_url: string | null;
        difficulty_level: string | null;
        estimated_duration: number | null;
    };
    enrollment: {
        id: number;
        status: string;
        enrolled_at: string;
        completed_at: string | null;
    };
    progress: {
        overall_percentage: number;
        total_courses: number;
        completed_courses: number;
        in_progress_courses: number;
        available_courses: number;
        locked_courses: number;
        is_completed: boolean;
        courses: CourseProgress[];
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Jalur Pembelajaran Saya', href: '/my-learning-paths' },
    { title: props.learningPath.title, href: `/my-learning-paths/${props.learningPath.id}` },
];

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const handleDrop = () => {
    router.delete(`/my-learning-paths/${props.learningPath.id}/drop`);
};

const getNextCourse = () => {
    return props.progress.courses.find(c => c.status === 'in_progress' || c.status === 'available');
};

const nextCourse = getNextCourse();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="learningPath.title" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <Link href="/my-learning-paths">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <h1 class="text-2xl font-bold">{{ learningPath.title }}</h1>
                            <Badge v-if="progress.is_completed" class="bg-green-100 text-green-800">
                                <Trophy class="h-3 w-3 mr-1" />
                                Selesai
                            </Badge>
                        </div>
                        <p class="text-muted-foreground">
                            Terdaftar sejak {{ formatDate(enrollment.enrolled_at) }}
                        </p>
                    </div>
                </div>

                <div class="flex gap-2">
                    <AlertDialog v-if="!progress.is_completed">
                        <AlertDialogTrigger as-child>
                            <Button variant="outline" class="text-red-600 hover:text-red-700">
                                <XCircle class="h-4 w-4 mr-2" />
                                Keluar dari Jalur
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Keluar dari Jalur Pembelajaran?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    Anda akan keluar dari jalur pembelajaran ini. Progress Anda akan disimpan
                                    jika Anda mendaftar kembali nanti.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                <AlertDialogAction @click="handleDrop" class="bg-red-600 hover:bg-red-700">
                                    Ya, Keluar
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>
            </div>

            <!-- Progress Overview Card -->
            <Card>
                <CardHeader>
                    <CardTitle>Progress Keseluruhan</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <!-- Progress Bar -->
                        <div>
                            <div class="flex justify-between text-sm mb-2">
                                <span>Progress</span>
                                <span class="font-bold text-lg">{{ progress.overall_percentage }}%</span>
                            </div>
                            <Progress :model-value="progress.overall_percentage" class="h-3" />
                        </div>

                        <!-- Stats Grid -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4">
                            <div class="text-center p-3 bg-green-50 dark:bg-green-950 rounded-lg">
                                <CheckCircle class="h-5 w-5 mx-auto text-green-600 mb-1" />
                                <p class="text-2xl font-bold text-green-600">{{ progress.completed_courses }}</p>
                                <p class="text-xs text-muted-foreground">Selesai</p>
                            </div>
                            <div class="text-center p-3 bg-blue-50 dark:bg-blue-950 rounded-lg">
                                <Play class="h-5 w-5 mx-auto text-blue-600 mb-1" />
                                <p class="text-2xl font-bold text-blue-600">{{ progress.in_progress_courses }}</p>
                                <p class="text-xs text-muted-foreground">Berlangsung</p>
                            </div>
                            <div class="text-center p-3 bg-yellow-50 dark:bg-yellow-950 rounded-lg">
                                <BookOpen class="h-5 w-5 mx-auto text-yellow-600 mb-1" />
                                <p class="text-2xl font-bold text-yellow-600">{{ progress.available_courses }}</p>
                                <p class="text-xs text-muted-foreground">Tersedia</p>
                            </div>
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <Lock class="h-5 w-5 mx-auto text-gray-500 mb-1" />
                                <p class="text-2xl font-bold text-gray-500">{{ progress.locked_courses }}</p>
                                <p class="text-xs text-muted-foreground">Terkunci</p>
                            </div>
                        </div>

                        <!-- Continue Button -->
                        <div v-if="nextCourse && !progress.is_completed" class="pt-4">
                            <Link :href="`/courses/${nextCourse.courseId}`">
                                <Button class="w-full md:w-auto gap-2">
                                    <Play class="h-4 w-4" />
                                    {{ nextCourse.status === 'in_progress' ? 'Lanjutkan' : 'Mulai' }}:
                                    {{ nextCourse.courseTitle }}
                                    <ArrowRight class="h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Objectives -->
            <Card v-if="learningPath.objectives && learningPath.objectives.length > 0">
                <CardHeader>
                    <CardTitle>Tujuan Pembelajaran</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="space-y-2">
                        <li
                            v-for="(objective, index) in learningPath.objectives"
                            :key="index"
                            class="flex items-start gap-2"
                        >
                            <CheckCircle class="h-5 w-5 text-green-600 mt-0.5 shrink-0" />
                            <span>{{ objective }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Course List -->
            <Card>
                <CardHeader>
                    <CardTitle>Daftar Kursus</CardTitle>
                    <CardDescription>
                        Selesaikan kursus secara berurutan untuk membuka kursus selanjutnya
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <CourseProgressItem
                            v-for="course in progress.courses"
                            :key="course.courseId"
                            :course="course"
                            :is-last="course.position === progress.total_courses - 1"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
```

---

## 4.5 Course Progress Item Component

### File: `resources/js/pages/learner/paths/components/CourseProgressItem.vue`

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    ArrowRight,
    CheckCircle,
    Circle,
    Lock,
    Play,
    Info,
} from 'lucide-vue-next';

interface Props {
    course: {
        courseId: number;
        courseTitle: string;
        status: 'locked' | 'available' | 'in_progress' | 'completed';
        position: number;
        isRequired: boolean;
        completionPercentage: number;
        minRequiredPercentage: number | null;
        lockReason: string | null;
    };
    isLast: boolean;
}

const props = defineProps<Props>();

const statusConfig = {
    locked: {
        icon: Lock,
        color: 'text-gray-400',
        bgColor: 'bg-gray-100 dark:bg-gray-800',
        label: 'Terkunci',
        badgeVariant: 'secondary' as const,
    },
    available: {
        icon: Circle,
        color: 'text-yellow-500',
        bgColor: 'bg-yellow-50 dark:bg-yellow-950',
        label: 'Tersedia',
        badgeVariant: 'outline' as const,
    },
    in_progress: {
        icon: Play,
        color: 'text-blue-500',
        bgColor: 'bg-blue-50 dark:bg-blue-950',
        label: 'Berlangsung',
        badgeVariant: 'default' as const,
    },
    completed: {
        icon: CheckCircle,
        color: 'text-green-500',
        bgColor: 'bg-green-50 dark:bg-green-950',
        label: 'Selesai',
        badgeVariant: 'default' as const,
    },
};

const config = statusConfig[props.course.status];
const StatusIcon = config.icon;
</script>

<template>
    <div class="relative">
        <!-- Connector Line -->
        <div
            v-if="!isLast"
            class="absolute left-5 top-12 w-0.5 h-full -ml-px"
            :class="course.status === 'completed' ? 'bg-green-300' : 'bg-gray-200 dark:bg-gray-700'"
        />

        <div
            class="flex items-start gap-4 p-4 rounded-lg transition-colors"
            :class="[
                config.bgColor,
                course.status === 'locked' ? 'opacity-60' : '',
            ]"
        >
            <!-- Status Icon -->
            <div
                class="flex items-center justify-center w-10 h-10 rounded-full shrink-0"
                :class="course.status === 'completed' ? 'bg-green-500' : 'bg-white dark:bg-gray-900'"
            >
                <StatusIcon
                    class="h-5 w-5"
                    :class="course.status === 'completed' ? 'text-white' : config.color"
                />
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <h4 class="font-medium">
                                {{ course.position + 1 }}. {{ course.courseTitle }}
                            </h4>
                            <Badge v-if="!course.isRequired" variant="outline" class="text-xs">
                                Opsional
                            </Badge>
                        </div>

                        <!-- Progress bar for in_progress -->
                        <div v-if="course.status === 'in_progress'" class="mt-2">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-muted-foreground">Progress</span>
                                <span>{{ course.completionPercentage }}%</span>
                            </div>
                            <Progress :model-value="course.completionPercentage" class="h-1.5" />
                        </div>

                        <!-- Lock reason for locked courses -->
                        <div v-if="course.status === 'locked' && course.lockReason" class="mt-2">
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger class="flex items-center gap-1 text-sm text-muted-foreground">
                                        <Info class="h-4 w-4" />
                                        <span>{{ course.lockReason }}</span>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Selesaikan kursus sebelumnya untuk membuka kursus ini</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>

                        <!-- Min completion percentage requirement -->
                        <div
                            v-if="course.minRequiredPercentage && course.status !== 'completed'"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Minimal {{ course.minRequiredPercentage }}% untuk lanjut
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="shrink-0">
                        <Link
                            v-if="course.status !== 'locked'"
                            :href="`/courses/${course.courseId}`"
                        >
                            <Button
                                :variant="course.status === 'completed' ? 'outline' : 'default'"
                                size="sm"
                                class="gap-1"
                            >
                                <template v-if="course.status === 'completed'">
                                    Lihat
                                </template>
                                <template v-else-if="course.status === 'in_progress'">
                                    Lanjutkan
                                    <ArrowRight class="h-4 w-4" />
                                </template>
                                <template v-else>
                                    Mulai
                                    <ArrowRight class="h-4 w-4" />
                                </template>
                            </Button>
                        </Link>
                        <Button v-else variant="outline" size="sm" disabled>
                            <Lock class="h-4 w-4 mr-1" />
                            Terkunci
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
```

---

## 4.6 Path Overview Page (Not Enrolled)

### File: `resources/js/pages/learner/paths/Overview.vue`

```vue
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    ArrowLeft,
    BookOpen,
    CheckCircle,
    Clock,
    Users,
} from 'lucide-vue-next';

interface Course {
    id: number;
    title: string;
    description: string | null;
    estimated_duration: number | null;
    is_required: boolean;
    position: number;
}

interface Props {
    learningPath: {
        id: number;
        title: string;
        description: string | null;
        objectives: string[] | null;
        slug: string;
        thumbnail_url: string | null;
        difficulty_level: string | null;
        estimated_duration: number | null;
        is_published: boolean;
        creator: string | null;
        courses_count: number;
        courses: Course[];
    };
    can_enroll: boolean;
    enrolled_count: number;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Jalur Pembelajaran', href: '/learning-paths' },
    { title: props.learningPath.title, href: '#' },
];

const handleEnroll = () => {
    router.post(`/learning-paths/${props.learningPath.id}/enroll`);
};

const formatDuration = (minutes: number | null) => {
    if (!minutes) return 'Tidak ditentukan';
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours === 0) return `${mins} menit`;
    if (mins === 0) return `${hours} jam`;
    return `${hours} jam ${mins} menit`;
};

const difficultyLabel = (level: string | null) => {
    if (!level) return null;
    const labels: Record<string, string> = {
        beginner: 'Pemula',
        intermediate: 'Menengah',
        advanced: 'Lanjutan',
        expert: 'Ahli',
    };
    return labels[level] || level;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="learningPath.title" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <Link href="/learning-paths">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold">{{ learningPath.title }}</h1>
                        <p class="text-muted-foreground">
                            Dibuat oleh {{ learningPath.creator || 'Admin' }}
                        </p>
                    </div>
                </div>

                <Button
                    v-if="can_enroll"
                    @click="handleEnroll"
                    size="lg"
                    class="gap-2"
                >
                    <BookOpen class="h-5 w-5" />
                    Daftar Sekarang
                </Button>
                <Badge v-else variant="secondary">Sudah Terdaftar</Badge>
            </div>

            <!-- Overview Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <Card>
                    <CardContent class="pt-6 text-center">
                        <BookOpen class="h-8 w-8 mx-auto text-muted-foreground mb-2" />
                        <p class="text-2xl font-bold">{{ learningPath.courses_count }}</p>
                        <p class="text-sm text-muted-foreground">Kursus</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6 text-center">
                        <Clock class="h-8 w-8 mx-auto text-muted-foreground mb-2" />
                        <p class="text-2xl font-bold">{{ formatDuration(learningPath.estimated_duration) }}</p>
                        <p class="text-sm text-muted-foreground">Durasi</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6 text-center">
                        <Users class="h-8 w-8 mx-auto text-muted-foreground mb-2" />
                        <p class="text-2xl font-bold">{{ enrolled_count }}</p>
                        <p class="text-sm text-muted-foreground">Peserta</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6 text-center">
                        <Badge variant="outline" class="text-lg px-3 py-1">
                            {{ difficultyLabel(learningPath.difficulty_level) || 'Semua Level' }}
                        </Badge>
                        <p class="text-sm text-muted-foreground mt-2">Tingkat</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Description -->
            <Card>
                <CardHeader>
                    <CardTitle>Deskripsi</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="whitespace-pre-wrap">
                        {{ learningPath.description || 'Tidak ada deskripsi.' }}
                    </p>
                </CardContent>
            </Card>

            <!-- Objectives -->
            <Card v-if="learningPath.objectives && learningPath.objectives.length > 0">
                <CardHeader>
                    <CardTitle>Yang Akan Anda Pelajari</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div
                            v-for="(objective, index) in learningPath.objectives"
                            :key="index"
                            class="flex items-start gap-2"
                        >
                            <CheckCircle class="h-5 w-5 text-green-600 mt-0.5 shrink-0" />
                            <span>{{ objective }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Course List -->
            <Card>
                <CardHeader>
                    <CardTitle>Kurikulum</CardTitle>
                    <CardDescription>
                        {{ learningPath.courses_count }} kursus dalam jalur pembelajaran ini
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div
                            v-for="(course, index) in learningPath.courses"
                            :key="course.id"
                            class="flex items-center gap-4 p-3 bg-muted rounded-lg"
                        >
                            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-primary/10 text-primary font-medium">
                                {{ index + 1 }}
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <h4 class="font-medium">{{ course.title }}</h4>
                                    <Badge v-if="!course.is_required" variant="outline" class="text-xs">
                                        Opsional
                                    </Badge>
                                </div>
                                <p class="text-sm text-muted-foreground line-clamp-1">
                                    {{ course.description || 'Tidak ada deskripsi' }}
                                </p>
                            </div>
                            <div v-if="course.estimated_duration" class="text-sm text-muted-foreground">
                                <Clock class="h-4 w-4 inline mr-1" />
                                {{ formatDuration(course.estimated_duration) }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- CTA -->
            <div v-if="can_enroll" class="text-center py-8">
                <Button @click="handleEnroll" size="lg" class="gap-2">
                    <BookOpen class="h-5 w-5" />
                    Daftar Sekarang - Gratis
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
```

---

## 4.7 Navigation Updates

### Add to Learner Navigation

```vue
<!-- In navigation component, add: -->
<Link href="/my-learning-paths" class="nav-item">
    <BookOpen class="h-4 w-4" />
    <span>Jalur Pembelajaran Saya</span>
</Link>
```

---

## Implementation Checklist

- [ ] Create `learner/paths/Index.vue`
- [ ] Create `learner/paths/Show.vue`
- [ ] Create `learner/paths/Overview.vue`
- [ ] Create `PathProgressCard.vue` component
- [ ] Create `CourseProgressItem.vue` component
- [ ] Update navigation to include "My Learning Paths"
- [ ] Generate Wayfinder routes for new controller
- [ ] Add TypeScript types for props
- [ ] Test responsive design on mobile

---

## Next Phase

Continue to [Phase 5: Auto-Enrollment](./05-AUTO-ENROLLMENT.md)
