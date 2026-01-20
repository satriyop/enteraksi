<script setup lang="ts">
// =============================================================================
// Course Detail Page (Public View)
// Uses CourseContentOutline, CourseRatingsSection, CourseEnrollmentCard, CourseMetaCard
// =============================================================================

import Navbar from '@/components/home/Navbar.vue';
import Footer from '@/components/home/Footer.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import CourseContentOutline from '@/components/courses/CourseContentOutline.vue';
import CourseRatingsSection from '@/components/courses/CourseRatingsSection.vue';
import CourseEnrollmentCard from '@/components/courses/CourseEnrollmentCard.vue';
import CourseMetaCard from '@/components/courses/CourseMetaCard.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookOpen, AlertTriangle, Clock, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { formatDuration, difficultyLabel, difficultyColor } from '@/lib/utils';
import type {
    Category,
    Tag,
    ContentType,
    DifficultyLevel,
    UserSummary,
} from '@/types';

// =============================================================================
// Page-Specific Types
// =============================================================================

interface OutlineLesson {
    id: number;
    title: string;
    content_type: ContentType;
    estimated_duration_minutes: number | null;
    order: number;
    is_free_preview: boolean;
}

interface OutlineSection {
    id: number;
    title: string;
    order: number;
    lessons: OutlineLesson[];
}

interface PublicCourse {
    id: number;
    title: string;
    slug: string;
    short_description: string;
    description: string | null;
    thumbnail_path: string | null;
    difficulty_level: DifficultyLevel;
    estimated_duration_minutes: number;
    manual_duration_minutes: number | null;
    status: string;
    visibility: string;
    user: UserSummary;
    category: Category | null;
    tags: Tag[];
    sections: OutlineSection[];
    lessons_count: number;
    enrollments_count: number;
}

interface UserEnrollment {
    id: number;
    status: string;
    enrolled_at: string;
    progress_percentage: number;
}

interface CourseRating {
    id: number;
    user_id: number;
    course_id: number;
    rating: number;
    review: string | null;
    created_at: string;
    user: UserSummary;
}

interface Props {
    course: PublicCourse;
    enrollment: UserEnrollment | null;
    isUnderRevision: boolean;
    userRating: CourseRating | null;
    ratings: CourseRating[];
    averageRating: number | null;
    ratingsCount: number;
    can: {
        update: boolean;
        delete: boolean;
        publish: boolean;
        enroll: boolean;
        rate: boolean;
    };
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

const page = usePage();
const appName = computed(() => page.props.name || 'E-Learning');

// =============================================================================
// Computed
// =============================================================================

const isEnrolled = computed(() =>
    props.enrollment && props.enrollment.status === 'active'
);

const totalDuration = computed(() => {
    const minutes = props.course.manual_duration_minutes ?? props.course.estimated_duration_minutes ?? 0;
    return formatDuration(minutes, 'long');
});

const totalLessons = computed(() =>
    props.course.sections.reduce((total, section) => total + section.lessons.length, 0)
);

const previewLessonsCount = computed(() =>
    props.course.sections.reduce((total, section) =>
        total + section.lessons.filter(l => l.is_free_preview).length, 0)
);

const firstLessonId = computed(() => {
    for (const section of props.course.sections) {
        if (section.lessons.length > 0) {
            return section.lessons.sort((a, b) => a.order - b.order)[0].id;
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

                    <!-- Course Content Outline -->
                    <CourseContentOutline
                        :course-id="course.id"
                        :sections="course.sections"
                        :total-duration-minutes="course.manual_duration_minutes ?? course.estimated_duration_minutes"
                        :is-enrolled="isEnrolled"
                    />

                    <!-- Tags -->
                    <div v-if="course.tags.length > 0" class="mt-6">
                        <h3 class="text-sm font-medium mb-2">Tags</h3>
                        <div class="flex flex-wrap gap-2">
                            <Badge v-for="tag in course.tags" :key="tag.id" variant="secondary">
                                {{ tag.name }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Ratings & Reviews -->
                    <div class="mt-6">
                        <CourseRatingsSection
                            :course-id="course.id"
                            :is-enrolled="isEnrolled"
                            :user-rating="userRating"
                            :ratings="ratings"
                            :average-rating="averageRating"
                            :ratings-count="ratingsCount"
                        />
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1">
                    <div class="sticky top-4 space-y-4">
                        <!-- Enrollment Card -->
                        <CourseEnrollmentCard
                            :course-id="course.id"
                            :enrollment="enrollment"
                            :can-enroll="can.enroll"
                            :preview-lessons-count="previewLessonsCount"
                            :first-lesson-id="firstLessonId"
                        />

                        <!-- Course Info Card -->
                        <CourseMetaCard
                            :instructor="course.user"
                            :lessons-count="totalLessons"
                            :duration-minutes="course.manual_duration_minutes ?? course.estimated_duration_minutes"
                            :enrollments-count="course.enrollments_count"
                            :difficulty-level="course.difficulty_level"
                            :category="course.category"
                            :average-rating="averageRating"
                            :ratings-count="ratingsCount"
                        />
                    </div>
                </div>
            </div>
        </main>

        <Footer :app-name="appName" />
    </div>
</template>
