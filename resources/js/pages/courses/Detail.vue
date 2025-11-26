<script setup lang="ts">
import Navbar from '@/components/home/Navbar.vue';
import Footer from '@/components/home/Footer.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Clock,
    Users,
    BookOpen,
    ChevronDown,
    ChevronRight,
    PlayCircle,
    FileText,
    CheckCircle,
    User,
    BarChart,
    FolderOpen,
    Eye,
    Lock,
    Headphones,
    FileDown,
    Video as VideoCall,
    Youtube,
    AlertTriangle,
} from 'lucide-vue-next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { ref, computed } from 'vue';

interface Category {
    id: number;
    name: string;
}

interface Tag {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
}

interface Lesson {
    id: number;
    title: string;
    content_type: 'text' | 'video' | 'youtube' | 'audio' | 'document' | 'conference';
    estimated_duration_minutes: number | null;
    order: number;
    is_free_preview: boolean;
}

interface Section {
    id: number;
    title: string;
    order: number;
    lessons: Lesson[];
}

interface Course {
    id: number;
    title: string;
    slug: string;
    short_description: string;
    description: string | null;
    thumbnail_path: string | null;
    difficulty_level: 'beginner' | 'intermediate' | 'advanced';
    estimated_duration_minutes: number;
    manual_duration_minutes: number | null;
    status: string;
    visibility: string;
    user: User;
    category: Category | null;
    tags: Tag[];
    sections: Section[];
    lessons_count: number;
    enrollments_count: number;
}

interface Enrollment {
    id: number;
    status: string;
    enrolled_at: string;
    progress_percentage: number;
}

interface Props {
    course: Course;
    enrollment: Enrollment | null;
    isUnderRevision: boolean;
    can: {
        update: boolean;
        delete: boolean;
        publish: boolean;
        enroll: boolean;
    };
}

const props = defineProps<Props>();

const page = usePage();
const appName = computed(() => page.props.name || 'E-Learning');

// Auto-expand sections: all sections for enrolled users, or sections with preview for non-enrolled
const isUserEnrolled = props.enrollment && props.enrollment.status === 'active';
const initialExpandedSections = isUserEnrolled
    ? props.course.sections.map(section => section.id)
    : props.course.sections
        .filter(section => section.lessons.some(lesson => lesson.is_free_preview))
        .map(section => section.id);

const expandedSections = ref<number[]>(initialExpandedSections);
const isEnrolling = ref(false);

const toggleSection = (sectionId: number) => {
    const index = expandedSections.value.indexOf(sectionId);
    if (index === -1) {
        expandedSections.value.push(sectionId);
    } else {
        expandedSections.value.splice(index, 1);
    }
};

const isSectionExpanded = (sectionId: number) => {
    return expandedSections.value.includes(sectionId);
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

const formatDuration = (minutes: number | null) => {
    if (!minutes) return '-';
    if (minutes < 60) return `${minutes} menit`;
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    if (remainingMinutes === 0) return `${hours} jam`;
    return `${hours}j ${remainingMinutes}m`;
};

const totalDuration = computed(() => {
    const minutes = props.course.manual_duration_minutes ?? props.course.estimated_duration_minutes ?? 0;
    return formatDuration(minutes);
});

const totalLessons = computed(() => {
    return props.course.sections.reduce((total, section) => total + section.lessons.length, 0);
});

const lessonTypeIcon = (type: string) => {
    switch (type) {
        case 'video':
            return PlayCircle;
        case 'youtube':
            return Youtube;
        case 'audio':
            return Headphones;
        case 'document':
            return FileDown;
        case 'conference':
            return VideoCall;
        case 'text':
        default:
            return FileText;
    }
};

const lessonTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
        video: 'Video',
        youtube: 'YouTube',
        audio: 'Audio',
        document: 'Dokumen',
        conference: 'Konferensi',
        text: 'Teks',
    };
    return labels[type] || type;
};

const previewLessonsCount = computed(() => {
    return props.course.sections.reduce((total, section) => {
        return total + section.lessons.filter(l => l.is_free_preview).length;
    }, 0);
});

const handleEnroll = () => {
    isEnrolling.value = true;
    router.post(`/courses/${props.course.id}/enroll`, {}, {
        onFinish: () => {
            isEnrolling.value = false;
        },
    });
};

const handleUnenroll = () => {
    if (!confirm('Apakah Anda yakin ingin membatalkan pendaftaran dari kursus ini?')) {
        return;
    }
    router.delete(`/courses/${props.course.id}/unenroll`);
};

const isEnrolled = computed(() => {
    return props.enrollment && props.enrollment.status === 'active';
});

// Get the first lesson for "Continue Learning" button
const firstLesson = computed(() => {
    for (const section of props.course.sections) {
        if (section.lessons.length > 0) {
            return section.lessons.sort((a, b) => a.order - b.order)[0];
        }
    }
    return null;
});
</script>

<template>
    <Head :title="course.title" />

    <div class="min-h-screen bg-background">
        <Navbar :app-name="appName" />

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Breadcrumb -->
            <nav class="mb-6 text-sm">
                <ol class="flex items-center gap-2 text-muted-foreground">
                    <li>
                        <Link href="/" class="hover:text-foreground">Beranda</Link>
                    </li>
                    <li>/</li>
                    <li>
                        <Link href="/courses" class="hover:text-foreground">Kursus</Link>
                    </li>
                    <li>/</li>
                    <li class="text-foreground">{{ course.title }}</li>
                </ol>
            </nav>

            <!-- Under Revision Alert -->
            <Alert v-if="isUnderRevision" variant="destructive" class="mb-6 border-yellow-500 bg-yellow-50 dark:bg-yellow-950">
                <AlertTriangle class="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                <AlertTitle class="text-yellow-800 dark:text-yellow-200">Kursus Sedang Dalam Revisi</AlertTitle>
                <AlertDescription class="text-yellow-700 dark:text-yellow-300">
                    Kursus ini sedang dalam proses revisi oleh admin. Anda mungkin tidak dapat mengakses konten baru sampai revisi selesai dan kursus dipublikasikan kembali.
                    Progress pembelajaran Anda tetap tersimpan.
                </AlertDescription>
            </Alert>

            <div class="grid gap-8 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="lg:col-span-2">
                    <!-- Course Header -->
                    <div class="mb-6">
                        <div class="flex flex-wrap items-center gap-2 mb-3">
                            <Badge :class="difficultyColor(course.difficulty_level)">
                                {{ difficultyLabel(course.difficulty_level) }}
                            </Badge>
                            <Badge v-if="course.category" variant="outline">
                                {{ course.category.name }}
                            </Badge>
                        </div>
                        <h1 class="text-3xl font-bold mb-3">{{ course.title }}</h1>
                        <p class="text-lg text-muted-foreground">{{ course.short_description }}</p>
                    </div>

                    <!-- Thumbnail -->
                    <div class="mb-6 aspect-video rounded-lg bg-muted overflow-hidden">
                        <img
                            v-if="course.thumbnail_path"
                            :src="`/storage/${course.thumbnail_path}`"
                            :alt="course.title"
                            class="h-full w-full object-cover"
                        />
                        <div v-else class="flex h-full items-center justify-center">
                            <BookOpen class="h-20 w-20 text-muted-foreground" />
                        </div>
                    </div>

                    <!-- Course Stats (Mobile) -->
                    <div class="mb-6 grid grid-cols-3 gap-4 lg:hidden">
                        <div class="text-center">
                            <div class="text-2xl font-bold">{{ totalLessons }}</div>
                            <div class="text-sm text-muted-foreground">Materi</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold">{{ totalDuration }}</div>
                            <div class="text-sm text-muted-foreground">Durasi</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold">{{ course.enrollments_count }}</div>
                            <div class="text-sm text-muted-foreground">Peserta</div>
                        </div>
                    </div>

                    <!-- Description -->
                    <Card v-if="course.description" class="mb-6">
                        <CardHeader>
                            <CardTitle>Tentang Kursus</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="prose prose-sm dark:prose-invert max-w-none" v-html="course.description" />
                        </CardContent>
                    </Card>

                    <!-- Course Content -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <FolderOpen class="h-5 w-5" />
                                Konten Kursus
                            </CardTitle>
                            <p class="text-sm text-muted-foreground">
                                {{ course.sections.length }} bagian • {{ totalLessons }} materi • {{ totalDuration }} total durasi
                            </p>
                        </CardHeader>
                        <CardContent>
                            <div v-if="course.sections.length === 0" class="py-8 text-center text-muted-foreground">
                                Belum ada konten untuk kursus ini.
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="section in course.sections"
                                    :key="section.id"
                                    class="rounded-lg border"
                                >
                                    <!-- Section Header -->
                                    <button
                                        type="button"
                                        @click="toggleSection(section.id)"
                                        class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-muted/50 transition-colors"
                                    >
                                        <div class="flex items-center gap-2">
                                            <ChevronDown
                                                v-if="isSectionExpanded(section.id)"
                                                class="h-4 w-4 shrink-0"
                                            />
                                            <ChevronRight v-else class="h-4 w-4 shrink-0" />
                                            <span class="font-medium">{{ section.title }}</span>
                                        </div>
                                        <span class="text-sm text-muted-foreground">
                                            {{ section.lessons.length }} materi
                                        </span>
                                    </button>

                                    <!-- Section Lessons -->
                                    <div
                                        v-if="isSectionExpanded(section.id)"
                                        class="border-t bg-muted/30"
                                    >
                                        <template v-for="lesson in section.lessons" :key="lesson.id">
                                            <!-- Enrolled users can click any lesson -->
                                            <Link
                                                v-if="isEnrolled"
                                                :href="`/courses/${course.id}/lessons/${lesson.id}`"
                                                class="flex items-center gap-3 px-4 py-3 border-b last:border-b-0 transition-colors hover:bg-primary/5 cursor-pointer"
                                            >
                                                <component
                                                    :is="lessonTypeIcon(lesson.content_type)"
                                                    class="h-4 w-4 shrink-0 text-primary"
                                                />
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm truncate text-foreground">
                                                        {{ lesson.title }}
                                                    </p>
                                                </div>
                                                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                                    <Badge variant="outline" class="text-xs">
                                                        {{ lessonTypeLabel(lesson.content_type) }}
                                                    </Badge>
                                                    <span v-if="lesson.estimated_duration_minutes">
                                                        {{ lesson.estimated_duration_minutes }} min
                                                    </span>
                                                </div>
                                            </Link>
                                            <!-- Non-enrolled users: preview lessons are clickable -->
                                            <Link
                                                v-else-if="lesson.is_free_preview"
                                                :href="`/courses/${course.id}/lessons/${lesson.id}/preview`"
                                                class="flex items-center gap-3 px-4 py-3 border-b last:border-b-0 transition-colors hover:bg-primary/5 cursor-pointer"
                                            >
                                                <component
                                                    :is="lessonTypeIcon(lesson.content_type)"
                                                    class="h-4 w-4 shrink-0 text-primary"
                                                />
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm truncate text-primary font-medium">
                                                        {{ lesson.title }}
                                                    </p>
                                                </div>
                                                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                                    <Badge class="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300 gap-1">
                                                        <Eye class="h-3 w-3" />
                                                        Preview
                                                    </Badge>
                                                    <Badge variant="outline" class="text-xs">
                                                        {{ lessonTypeLabel(lesson.content_type) }}
                                                    </Badge>
                                                    <span v-if="lesson.estimated_duration_minutes">
                                                        {{ lesson.estimated_duration_minutes }} min
                                                    </span>
                                                </div>
                                            </Link>
                                            <!-- Non-enrolled users: locked lessons -->
                                            <div
                                                v-else
                                                class="flex items-center gap-3 px-4 py-3 border-b last:border-b-0 transition-colors opacity-60"
                                            >
                                                <component
                                                    :is="lessonTypeIcon(lesson.content_type)"
                                                    class="h-4 w-4 shrink-0 text-muted-foreground"
                                                />
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm truncate">
                                                        {{ lesson.title }}
                                                    </p>
                                                </div>
                                                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                                    <Lock class="h-3 w-3" />
                                                    <Badge variant="outline" class="text-xs">
                                                        {{ lessonTypeLabel(lesson.content_type) }}
                                                    </Badge>
                                                    <span v-if="lesson.estimated_duration_minutes">
                                                        {{ lesson.estimated_duration_minutes }} min
                                                    </span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Tags -->
                    <div v-if="course.tags.length > 0" class="mt-6">
                        <h3 class="text-sm font-medium mb-2">Tags</h3>
                        <div class="flex flex-wrap gap-2">
                            <Badge v-for="tag in course.tags" :key="tag.id" variant="secondary">
                                {{ tag.name }}
                            </Badge>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1">
                    <div class="sticky top-4 space-y-4">
                        <!-- Enrollment Card -->
                        <Card>
                            <CardContent class="p-6">
                                <!-- Already Enrolled -->
                                <div v-if="isEnrolled" class="space-y-4">
                                    <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
                                        <CheckCircle class="h-5 w-5" />
                                        <span class="font-medium">Anda sudah terdaftar</span>
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        Progress: {{ enrollment?.progress_percentage || 0 }}%
                                    </div>
                                    <div class="w-full bg-muted rounded-full h-2">
                                        <div
                                            class="bg-primary h-2 rounded-full transition-all"
                                            :style="{ width: `${enrollment?.progress_percentage || 0}%` }"
                                        />
                                    </div>
                                    <Link
                                        v-if="firstLesson"
                                        :href="`/courses/${course.id}/lessons/${firstLesson.id}`"
                                        class="block"
                                    >
                                        <Button class="w-full" size="lg">
                                            Lanjutkan Belajar
                                        </Button>
                                    </Link>
                                    <Button
                                        variant="outline"
                                        class="w-full"
                                        size="sm"
                                        @click="handleUnenroll"
                                    >
                                        Batalkan Pendaftaran
                                    </Button>
                                </div>

                                <!-- Not Enrolled -->
                                <div v-else class="space-y-4">
                                    <div class="text-center">
                                        <div class="text-3xl font-bold text-primary mb-1">Gratis</div>
                                        <p class="text-sm text-muted-foreground">Akses penuh ke semua materi</p>
                                    </div>
                                    <Button
                                        v-if="can.enroll"
                                        class="w-full"
                                        size="lg"
                                        @click="handleEnroll"
                                        :disabled="isEnrolling"
                                    >
                                        {{ isEnrolling ? 'Mendaftar...' : 'Daftar Sekarang' }}
                                    </Button>
                                    <p v-else class="text-sm text-center text-muted-foreground">
                                        Kursus ini tidak tersedia untuk pendaftaran.
                                    </p>
                                    <div v-if="previewLessonsCount > 0" class="flex items-center gap-2 text-sm text-muted-foreground pt-2 border-t">
                                        <Eye class="h-4 w-4 text-green-600" />
                                        <span>{{ previewLessonsCount }} materi dapat dipreview gratis</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Course Info Card -->
                        <Card>
                            <CardContent class="p-6 space-y-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                        <User class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <div class="text-sm text-muted-foreground">Instruktur</div>
                                        <div class="font-medium">{{ course.user.name }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                        <BookOpen class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <div class="text-sm text-muted-foreground">Jumlah Materi</div>
                                        <div class="font-medium">{{ totalLessons }} materi</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                        <Clock class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <div class="text-sm text-muted-foreground">Total Durasi</div>
                                        <div class="font-medium">{{ totalDuration }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                        <Users class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <div class="text-sm text-muted-foreground">Peserta</div>
                                        <div class="font-medium">{{ course.enrollments_count }} peserta</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                        <BarChart class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <div class="text-sm text-muted-foreground">Tingkat Kesulitan</div>
                                        <div class="font-medium">{{ difficultyLabel(course.difficulty_level) }}</div>
                                    </div>
                                </div>

                                <div v-if="course.category" class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                        <FolderOpen class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <div class="text-sm text-muted-foreground">Kategori</div>
                                        <div class="font-medium">{{ course.category.name }}</div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </main>

        <Footer :app-name="appName" />
    </div>
</template>
