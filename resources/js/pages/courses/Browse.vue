<script setup lang="ts">
import Navbar from '@/components/home/Navbar.vue';
import Footer from '@/components/home/Footer.vue';
import BrowseCourseCard from '@/components/courses/BrowseCourseCard.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { BookOpen, Search, Filter, X } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import type { Category, DifficultyLevel, PaginationLink, UserSummary } from '@/types';

// =============================================================================
// Page-Specific Types
// =============================================================================

/** Course card for browse page */
interface BrowseCourse {
    id: number;
    title: string;
    slug: string;
    short_description: string;
    thumbnail_path: string | null;
    difficulty_level: DifficultyLevel;
    estimated_duration_minutes: number;
    manual_duration_minutes: number | null;
    user: UserSummary;
    category: Category | null;
    lessons_count: number;
    enrollments_count: number;
}

interface PaginatedCourses {
    data: BrowseCourse[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

interface Filters {
    search?: string;
    category_id?: string;
    difficulty_level?: string;
}

/** Enrollment status for a course */
interface EnrollmentStatus {
    status: 'active' | 'completed' | 'dropped';
    progress_percentage: number;
}

/** Map of course ID to enrollment status */
type EnrollmentMap = Record<number, EnrollmentStatus>;

interface Props {
    courses: PaginatedCourses;
    categories: Category[];
    filters: Filters;
    enrollmentMap?: EnrollmentMap;
}

const props = defineProps<Props>();

const page = usePage();
const appName = computed(() => page.props.name || 'E-Learning');

const searchQuery = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category_id || '');
const selectedDifficulty = ref(props.filters.difficulty_level || '');
const showFilters = ref(false);

const applyFilters = () => {
    const params: Record<string, string> = {};
    if (searchQuery.value) params.search = searchQuery.value;
    if (selectedCategory.value) params.category_id = selectedCategory.value;
    if (selectedDifficulty.value) params.difficulty_level = selectedDifficulty.value;

    router.get('/courses', params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedCategory.value = '';
    selectedDifficulty.value = '';
    router.get('/courses', {}, { preserveState: true });
};

const hasActiveFilters = computed(() => {
    return searchQuery.value || selectedCategory.value || selectedDifficulty.value;
});

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout>;
watch(searchQuery, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 500);
});
</script>

<template>
    <Head title="Jelajahi Kursus" />

    <div class="min-h-screen bg-background">
        <Navbar :app-name="appName" />

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold">Jelajahi Kursus</h1>
                <p class="mt-2 text-muted-foreground">
                    Temukan kursus yang sesuai dengan minat dan kebutuhan Anda
                </p>
            </div>

            <!-- Search and Filters -->
            <div class="mb-6 space-y-4">
                <div class="flex flex-col gap-4 sm:flex-row">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Cari kursus..."
                            class="pl-10"
                        />
                    </div>
                    <Button
                        variant="outline"
                        @click="showFilters = !showFilters"
                        class="sm:w-auto"
                    >
                        <Filter class="mr-2 h-4 w-4" />
                        Filter
                        <Badge v-if="hasActiveFilters" class="ml-2" variant="secondary">
                            {{ [selectedCategory, selectedDifficulty].filter(Boolean).length }}
                        </Badge>
                    </Button>
                </div>

                <!-- Filter Panel -->
                <div v-if="showFilters" class="rounded-lg border bg-card p-4">
                    <div class="flex flex-wrap gap-4">
                        <div class="min-w-48">
                            <label class="mb-2 block text-sm font-medium">Kategori</label>
                            <select
                                v-model="selectedCategory"
                                @change="applyFilters"
                                class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            >
                                <option value="">Semua Kategori</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>
                        <div class="min-w-48">
                            <label class="mb-2 block text-sm font-medium">Tingkat Kesulitan</label>
                            <select
                                v-model="selectedDifficulty"
                                @change="applyFilters"
                                class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            >
                                <option value="">Semua Tingkat</option>
                                <option value="beginner">Pemula</option>
                                <option value="intermediate">Menengah</option>
                                <option value="advanced">Lanjutan</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
                                <X class="mr-1 h-4 w-4" />
                                Hapus Filter
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Info -->
            <div class="mb-4 text-sm text-muted-foreground">
                Menampilkan {{ courses.data.length }} dari {{ courses.total }} kursus
            </div>

            <!-- Course Grid -->
            <div v-if="courses.data.length > 0" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <BrowseCourseCard
                    v-for="course in courses.data"
                    :key="course.id"
                    :course="course"
                    :enrollment="enrollmentMap?.[course.id]"
                />
            </div>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                <BookOpen class="h-16 w-16 text-muted-foreground mb-4" />
                <h2 class="text-xl font-semibold mb-2">Tidak Ada Kursus</h2>
                <p class="text-muted-foreground mb-4">
                    {{ hasActiveFilters ? 'Tidak ada kursus yang sesuai dengan filter Anda.' : 'Belum ada kursus yang tersedia saat ini.' }}
                </p>
                <Button v-if="hasActiveFilters" variant="outline" @click="clearFilters">
                    Hapus Filter
                </Button>
            </div>

            <!-- Pagination -->
            <div v-if="courses.last_page > 1" class="mt-8 flex justify-center gap-2">
                <template v-for="link in courses.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md border px-3 py-2 text-sm transition-colors hover:bg-muted"
                        :class="{ 'bg-primary text-primary-foreground hover:bg-primary/90': link.active }"
                        v-html="link.label"
                        preserve-scroll
                    />
                    <span
                        v-else
                        class="rounded-md border px-3 py-2 text-sm text-muted-foreground opacity-50"
                        v-html="link.label"
                    />
                </template>
            </div>
        </main>

        <Footer :app-name="appName" />
    </div>
</template>
