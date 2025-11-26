<script setup lang="ts">
import { index, create, show, edit, destroy } from '@/actions/App/Http/Controllers/CourseController';
import PageHeader from '@/components/crud/PageHeader.vue';
import EmptyState from '@/components/crud/EmptyState.vue';
import DataCard from '@/components/crud/DataCard.vue';
import FilterTabs from '@/components/crud/FilterTabs.vue';
import SearchInput from '@/components/crud/SearchInput.vue';
import Pagination from '@/components/crud/Pagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, BookOpen, Clock, Layers, Eye, Pencil, Trash2, LayoutGrid, List } from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';

interface Category {
    id: number;
    name: string;
    slug: string;
}

interface Course {
    id: number;
    title: string;
    slug: string;
    short_description: string;
    status: 'draft' | 'published' | 'archived';
    visibility: 'public' | 'restricted' | 'hidden';
    difficulty_level: 'beginner' | 'intermediate' | 'advanced';
    estimated_duration_minutes: number;
    thumbnail_path: string | null;
    category: Category | null;
    sections_count: number;
    lessons_count: number;
    created_at: string;
}

interface Props {
    courses: {
        data: Course[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        from: number;
        to: number;
        total: number;
    };
    filters: {
        search?: string;
        status?: string;
        category_id?: string;
    };
    categories: Category[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Kursus',
        href: index().url,
    },
];

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const viewMode = ref<'grid' | 'list'>('grid');

const statusTabs = computed(() => [
    { value: '', label: 'Semua', count: undefined },
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Terbit' },
    { value: 'archived', label: 'Arsip' },
]);

const statusBadge = (courseStatus: string) => {
    switch (courseStatus) {
        case 'published':
            return { label: 'Terbit', variant: 'default' as const };
        case 'draft':
            return { label: 'Draft', variant: 'secondary' as const };
        case 'archived':
            return { label: 'Arsip', variant: 'outline' as const };
        default:
            return { label: courseStatus, variant: 'secondary' as const };
    }
};

const difficultyLabel = (level: string) => {
    switch (level) {
        case 'beginner':
            return 'Pemula';
        case 'intermediate':
            return 'Menengah';
        case 'advanced':
            return 'Lanjutan';
        default:
            return level;
    }
};

const formatDuration = (minutes: number) => {
    if (!minutes) return '0 menit';
    if (minutes < 60) return `${minutes} menit`;
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    if (remainingMinutes === 0) return `${hours} jam`;
    return `${hours}j ${remainingMinutes}m`;
};

const getCourseActions = (course: Course) => [
    { label: 'Lihat', href: show(course.id).url, icon: Eye },
    { label: 'Edit', href: edit(course.id).url, icon: Pencil },
    { label: 'Hapus', icon: Trash2, variant: 'destructive' as const, onClick: () => deleteCourse(course) },
];

const getCourseMeta = (course: Course) => [
    { icon: Layers, label: `${course.sections_count ?? 0} seksi` },
    { icon: BookOpen, label: `${course.lessons_count ?? 0} materi` },
    { icon: Clock, label: formatDuration(course.estimated_duration_minutes) },
];

let searchTimeout: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(index().url, { search: value, status: status.value }, { preserveState: true, replace: true });
    }, 300);
});

watch(status, (value) => {
    router.get(index().url, { search: search.value, status: value }, { preserveState: true, replace: true });
});

const deleteCourse = (course: Course) => {
    if (confirm(`Apakah Anda yakin ingin menghapus kursus "${course.title}"?`)) {
        router.delete(destroy(course.id).url);
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Manajemen Kursus" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Kursus Saya"
                description="Kelola dan buat kursus pembelajaran baru"
            >
                <template #actions>
                    <Link :href="create().url">
                        <Button size="lg" class="gap-2">
                            <Plus class="h-5 w-5" />
                            Buat Kursus
                        </Button>
                    </Link>
                </template>
            </PageHeader>

            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <FilterTabs v-model="status" :tabs="statusTabs" />

                <div class="flex items-center gap-3">
                    <div class="w-full lg:w-80">
                        <SearchInput v-model="search" placeholder="Cari kursus..." />
                    </div>
                    <div class="hidden items-center gap-1 rounded-lg border p-1 sm:flex">
                        <button
                            type="button"
                            class="rounded-md p-2 transition-colors"
                            :class="viewMode === 'grid' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="viewMode = 'grid'"
                        >
                            <LayoutGrid class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="rounded-md p-2 transition-colors"
                            :class="viewMode === 'list' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="viewMode = 'list'"
                        >
                            <List class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <EmptyState
                v-if="courses.data.length === 0"
                :icon="BookOpen"
                title="Belum ada kursus"
                description="Mulai perjalanan mengajar Anda dengan membuat kursus pertama."
                action-label="Buat Kursus Baru"
                :action-href="create().url"
            />

            <template v-else>
                <div
                    :class="
                        viewMode === 'grid'
                            ? 'grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'
                            : 'flex flex-col gap-4'
                    "
                >
                    <DataCard
                        v-for="course in courses.data"
                        :key="course.id"
                        :title="course.title"
                        :subtitle="course.category?.name"
                        :description="course.short_description"
                        :thumbnail-url="course.thumbnail_path ? `/storage/${course.thumbnail_path}` : undefined"
                        :href="show(course.id).url"
                        :badges="[statusBadge(course.status), { label: difficultyLabel(course.difficulty_level), variant: 'outline' }]"
                        :meta="getCourseMeta(course)"
                        :actions="getCourseActions(course)"
                    />
                </div>

                <Pagination
                    v-if="courses.last_page > 1"
                    :links="courses.links"
                    :current-page="courses.current_page"
                    :last-page="courses.last_page"
                    :from="courses.from"
                    :to="courses.to"
                    :total="courses.total"
                />
            </template>
        </div>
    </AppLayout>
</template>
