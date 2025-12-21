<script setup lang="ts">
import Navbar from '@/components/home/Navbar.vue';
import Footer from '@/components/home/Footer.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Clock,
    Users,
    BookOpen,
    ChevronLeft,
    ChevronRight,
    Play,
    GraduationCap,
    Mail,
    Check,
    X,
    Eye,
    AlertCircle,
    Loader2,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { useTimeAgo } from '@vueuse/core';
import MyLearningCard from '@/components/courses/MyLearningCard.vue';

interface CourseItem {
    id: number;
    course_id?: number;
    title: string;
    slug: string;
    short_description: string;
    thumbnail_path: string | null;
    difficulty_level: 'beginner' | 'intermediate' | 'advanced';
    duration: number;
    instructor: string;
    category: string | null;
    enrollments_count?: number;
    progress_percentage?: number;
    enrolled_at?: string;
    last_lesson_id?: number | null;
    lessons_count?: number;
}

interface InvitedCourse {
    id: number; // invitation_id for accept/decline
    course_id: number;
    title: string;
    slug: string;
    short_description: string;
    thumbnail_path: string | null;
    difficulty_level: 'beginner' | 'intermediate' | 'advanced';
    duration: number;
    instructor: string;
    category: string | null;
    lessons_count: number;
    invited_by: string;
    message: string | null;
    invited_at: string;
    expires_at: string | null;
}

interface Props {
    featuredCourses: CourseItem[];
    myLearning: CourseItem[];
    invitedCourses: InvitedCourse[];
    browseCourses: CourseItem[];
}

defineProps<Props>();

const page = usePage();
const appName = computed(() => page.props.name || 'E-Learning');

const carouselIndex = ref(0);

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

const formatDuration = (minutes: number) => {
    if (!minutes) return '-';
    if (minutes < 60) return `${minutes} menit`;
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    if (remainingMinutes === 0) return `${hours} jam`;
    return `${hours}j ${remainingMinutes}m`;
};

const prevSlide = (total: number) => {
    carouselIndex.value = carouselIndex.value === 0 ? total - 1 : carouselIndex.value - 1;
};

const nextSlide = (total: number) => {
    carouselIndex.value = carouselIndex.value === total - 1 ? 0 : carouselIndex.value + 1;
};

// Invitation handling
const processingInvitations = ref<Set<number>>(new Set());

const formatRelativeTime = (dateString: string) => {
    return useTimeAgo(new Date(dateString)).value;
};

const getDaysUntilExpiry = (expiresAt: string | null): number | null => {
    if (!expiresAt) return null;
    const expiry = new Date(expiresAt);
    const now = new Date();
    const diffTime = expiry.getTime() - now.getTime();
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
};

const isExpiringSoon = (expiresAt: string | null): boolean => {
    const days = getDaysUntilExpiry(expiresAt);
    return days !== null && days <= 7 && days >= 0;
};

const acceptInvitation = (item: InvitedCourse) => {
    processingInvitations.value.add(item.id);
    router.post(
        `/invitations/${item.id}/accept`,
        {},
        {
            preserveScroll: true,
            onFinish: () => processingInvitations.value.delete(item.id),
        },
    );
};

const declineInvitation = (item: InvitedCourse) => {
    if (!confirm('Yakin ingin menolak undangan ini?')) return;

    processingInvitations.value.add(item.id);
    router.post(
        `/invitations/${item.id}/decline`,
        {},
        {
            preserveScroll: true,
            onFinish: () => processingInvitations.value.delete(item.id),
        },
    );
};
</script>

<template>
    <Head title="Dashboard Pembelajaran" />

    <div class="min-h-screen bg-background">
        <Navbar :app-name="appName" />

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-8">
                <!-- Featured Courses Carousel -->
                <section v-if="featuredCourses.length > 0" class="relative">
                    <div class="overflow-hidden rounded-xl">
                        <div
                            class="flex transition-transform duration-500 ease-in-out"
                            :style="{ transform: `translateX(-${carouselIndex * 100}%)` }"
                        >
                            <div
                                v-for="course in featuredCourses"
                                :key="course.id"
                                class="w-full shrink-0"
                            >
                                <div class="relative h-64 md:h-80 lg:h-96 overflow-hidden rounded-xl bg-gradient-to-r from-primary/90 to-primary/70">
                                    <img
                                        v-if="course.thumbnail_path"
                                        :src="course.thumbnail_path"
                                        :alt="course.title"
                                        class="absolute inset-0 h-full w-full object-cover mix-blend-overlay opacity-50"
                                    />
                                    <div class="absolute inset-0 flex flex-col justify-end p-6 md:p-8 text-white">
                                        <Badge class="mb-2 w-fit" variant="secondary">
                                            {{ course.category || 'Umum' }}
                                        </Badge>
                                        <h2 class="text-2xl md:text-3xl lg:text-4xl font-bold mb-2">
                                            {{ course.title }}
                                        </h2>
                                        <p class="text-sm md:text-base opacity-90 line-clamp-2 mb-4 max-w-2xl">
                                            {{ course.short_description }}
                                        </p>
                                        <div class="flex flex-wrap items-center gap-4 text-sm opacity-80 mb-4">
                                            <span class="flex items-center gap-1">
                                                <GraduationCap class="h-4 w-4" />
                                                {{ course.instructor }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <Clock class="h-4 w-4" />
                                                {{ formatDuration(course.duration) }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <Users class="h-4 w-4" />
                                                {{ course.enrollments_count }} peserta
                                            </span>
                                        </div>
                                        <Link :href="`/courses/${course.id}`">
                                            <Button variant="secondary" size="lg">
                                                Lihat Kursus
                                            </Button>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Carousel Controls -->
                    <button
                        v-if="featuredCourses.length > 1"
                        class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-2 shadow-lg hover:bg-white dark:bg-gray-800/80 dark:hover:bg-gray-800"
                        @click="prevSlide(featuredCourses.length)"
                    >
                        <ChevronLeft class="h-5 w-5" />
                    </button>
                    <button
                        v-if="featuredCourses.length > 1"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-2 shadow-lg hover:bg-white dark:bg-gray-800/80 dark:hover:bg-gray-800"
                        @click="nextSlide(featuredCourses.length)"
                    >
                        <ChevronRight class="h-5 w-5" />
                    </button>

                    <!-- Carousel Indicators -->
                    <div v-if="featuredCourses.length > 1" class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
                        <button
                            v-for="(_, idx) in featuredCourses"
                            :key="idx"
                            class="h-2 w-2 rounded-full transition-colors"
                            :class="idx === carouselIndex ? 'bg-white' : 'bg-white/50'"
                            @click="carouselIndex = idx"
                        />
                    </div>
                </section>

                <!-- My Learning Section -->
                <section v-if="myLearning.length > 0">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold flex items-center gap-2">
                            <BookOpen class="h-5 w-5" />
                            Pembelajaran Saya
                        </h2>
                        <Link href="/my-learning" class="text-sm text-primary hover:underline">
                            Lihat Semua
                        </Link>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <MyLearningCard
                            v-for="item in myLearning"
                            :key="item.id"
                            :course="{
                                id: item.id,
                                title: item.title,
                                slug: item.slug,
                                thumbnail_path: item.thumbnail_path,
                                instructor: item.instructor,
                                progress_percentage: item.progress_percentage,
                                last_lesson_id: item.last_lesson_id,
                                duration: item.duration,
                                difficulty_level: item.difficulty_level,
                                lessons_count: item.lessons_count,
                            }"
                        />
                    </div>
                </section>

                <!-- Invited Courses Section -->
                <section v-if="invitedCourses.length > 0">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="flex items-center gap-2 text-xl font-bold">
                            <Mail class="h-5 w-5 text-primary" />
                            Undangan Kursus
                            <Badge variant="secondary" class="ml-2">
                                {{ invitedCourses.length }}
                            </Badge>
                        </h2>
                    </div>

                    <div class="space-y-4">
                        <Card
                            v-for="item in invitedCourses"
                            :key="item.id"
                            class="overflow-hidden border-2 border-primary/30 bg-gradient-to-r from-primary/5 to-transparent"
                        >
                            <!-- Header -->
                            <div class="flex items-center gap-2 bg-primary/10 px-4 py-2">
                                <Mail class="h-4 w-4 text-primary" />
                                <span class="text-sm">
                                    Diundang oleh <strong>{{ item.invited_by }}</strong>
                                </span>
                                <span class="ml-auto text-xs text-muted-foreground">
                                    {{ formatRelativeTime(item.invited_at) }}
                                </span>
                            </div>

                            <!-- Body -->
                            <CardContent class="p-4">
                                <div class="flex gap-4">
                                    <!-- Thumbnail -->
                                    <Link :href="`/courses/${item.course_id}`" class="shrink-0">
                                        <div class="h-20 w-32 overflow-hidden rounded-lg bg-muted">
                                            <img
                                                v-if="item.thumbnail_path"
                                                :src="item.thumbnail_path"
                                                :alt="item.title"
                                                class="h-full w-full object-cover"
                                            />
                                            <div v-else class="flex h-full w-full items-center justify-center">
                                                <BookOpen class="h-8 w-8 text-muted-foreground" />
                                            </div>
                                        </div>
                                    </Link>

                                    <!-- Details -->
                                    <div class="min-w-0 flex-1">
                                        <Link :href="`/courses/${item.course_id}`">
                                            <h3 class="line-clamp-1 font-semibold hover:text-primary">
                                                {{ item.title }}
                                            </h3>
                                        </Link>
                                        <p class="text-sm text-muted-foreground">{{ item.instructor }}</p>
                                        <div class="mt-2 flex flex-wrap gap-3 text-xs text-muted-foreground">
                                            <span class="flex items-center gap-1">
                                                <Clock class="h-3 w-3" />
                                                {{ formatDuration(item.duration) }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <BookOpen class="h-3 w-3" />
                                                {{ item.lessons_count }} materi
                                            </span>
                                            <Badge :class="difficultyColor(item.difficulty_level)" class="text-xs">
                                                {{ difficultyLabel(item.difficulty_level) }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>

                                <!-- Message -->
                                <div
                                    v-if="item.message"
                                    class="mt-3 rounded-lg border-l-4 border-primary/50 bg-muted/50 p-3"
                                >
                                    <p class="text-sm italic text-muted-foreground">"{{ item.message }}"</p>
                                </div>

                                <!-- Expiration Warning -->
                                <div
                                    v-if="item.expires_at && isExpiringSoon(item.expires_at)"
                                    class="mt-3 flex items-center gap-2 text-amber-600 dark:text-amber-500"
                                >
                                    <AlertCircle class="h-4 w-4" />
                                    <span class="text-sm font-medium">
                                        Berakhir dalam {{ getDaysUntilExpiry(item.expires_at) }} hari
                                    </span>
                                </div>
                            </CardContent>

                            <!-- Actions -->
                            <div class="flex gap-2 border-t bg-muted/30 px-4 py-3">
                                <Button
                                    @click="acceptInvitation(item)"
                                    :disabled="processingInvitations.has(item.id)"
                                    class="flex-1"
                                >
                                    <Loader2
                                        v-if="processingInvitations.has(item.id)"
                                        class="mr-1 h-4 w-4 animate-spin"
                                    />
                                    <Check v-else class="mr-1 h-4 w-4" />
                                    Terima Undangan
                                </Button>
                                <Button
                                    @click="declineInvitation(item)"
                                    :disabled="processingInvitations.has(item.id)"
                                    variant="outline"
                                >
                                    <X class="mr-1 h-4 w-4" />
                                    Tolak
                                </Button>
                                <Link :href="`/courses/${item.course_id}`">
                                    <Button variant="ghost" size="icon">
                                        <Eye class="h-4 w-4" />
                                    </Button>
                                </Link>
                            </div>
                        </Card>
                    </div>
                </section>

                <!-- Browse Courses Section -->
                <section v-if="browseCourses.length > 0">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold">Jelajahi Kursus</h2>
                        <Link href="/courses" class="text-sm text-primary hover:underline">
                            Lihat Semua
                        </Link>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <Card v-for="course in browseCourses" :key="course.id" class="group overflow-hidden">
                            <div class="relative aspect-video bg-muted">
                                <img
                                    v-if="course.thumbnail_path"
                                    :src="course.thumbnail_path"
                                    :alt="course.title"
                                    class="h-full w-full object-cover transition-transform group-hover:scale-105"
                                />
                                <div v-else class="flex h-full items-center justify-center">
                                    <BookOpen class="h-12 w-12 text-muted-foreground" />
                                </div>
                                <Badge
                                    class="absolute top-2 left-2"
                                    :class="difficultyColor(course.difficulty_level)"
                                >
                                    {{ difficultyLabel(course.difficulty_level) }}
                                </Badge>
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
                                <div class="mt-2 flex items-center gap-3 text-xs text-muted-foreground">
                                    <span class="flex items-center gap-1">
                                        <Clock class="h-3 w-3" />
                                        {{ formatDuration(course.duration) }}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <Users class="h-3 w-3" />
                                        {{ course.enrollments_count }}
                                    </span>
                                </div>
                                <Button class="mt-3 w-full" variant="outline" size="sm">
                                    Daftar Sekarang
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <!-- Empty State -->
                <div
                    v-if="featuredCourses.length === 0 && myLearning.length === 0 && invitedCourses.length === 0 && browseCourses.length === 0"
                    class="flex flex-1 flex-col items-center justify-center py-12 text-center"
                >
                    <BookOpen class="h-16 w-16 text-muted-foreground mb-4" />
                    <h2 class="text-xl font-semibold mb-2">Belum Ada Kursus</h2>
                    <p class="text-muted-foreground mb-4">
                        Belum ada kursus yang tersedia saat ini.
                    </p>
                </div>
            </div>
        </main>

        <Footer :app-name="appName" />
    </div>
</template>
