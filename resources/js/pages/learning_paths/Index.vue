<script setup lang="ts">
import { index, create, show, edit, destroy, publish, unpublish } from '@/routes/learning-paths';
import PageHeader from '@/components/crud/PageHeader.vue';
import EmptyState from '@/components/crud/EmptyState.vue';
import DataCard from '@/components/crud/DataCard.vue';
import FilterTabs from '@/components/crud/FilterTabs.vue';
import SearchInput from '@/components/crud/SearchInput.vue';
import Pagination from '@/components/crud/Pagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type PaginatedResponse, DifficultyLevel } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, BookOpen, Layers, Eye, Pencil, Trash2, LayoutGrid, List, CheckCircle, Clock } from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';

// =============================================================================
// Page-Specific Types
// =============================================================================

/** Learning path list item with computed counts */
interface LearningPathListItem {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    is_published: boolean;
    estimated_duration: number;
    difficulty_level: DifficultyLevel | 'expert';
    thumbnail_url: string | null;
    courses_count: number;
    created_at: string;
    creator: {
        name: string;
    } | null;
}

interface Filters {
    search?: string;
    status?: string;
}

interface Props {
    learningPaths: PaginatedResponse<LearningPathListItem>;
    filters: Filters;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Jalur Pembelajaran',
        href: index().url,
    },
];

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const viewMode = ref<'grid' | 'list'>('grid');

const statusTabs = computed(() => [
    { value: '', label: 'Semua', count: undefined },
    { value: 'published', label: 'Terbit' },
    { value: 'draft', label: 'Draft' },
]);

const statusBadge = (learningPath: LearningPathListItem) => {
    return learningPath.is_published
        ? { label: 'Terbit', variant: 'default' as const }
        : { label: 'Draft', variant: 'secondary' as const };
};

const difficultyLabel = (level: string) => {
    switch (level) {
        case 'beginner':
            return 'Pemula';
        case 'intermediate':
            return 'Menengah';
        case 'advanced':
            return 'Lanjutan';
        case 'expert':
            return 'Ahli';
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

const getLearningPathActions = (learningPath: LearningPathListItem) => [
    { label: 'Lihat', href: show(learningPath.id).url, icon: Eye },
    { label: 'Edit', href: edit(learningPath.id).url, icon: Pencil },
    {
        label: learningPath.is_published ? 'Unpublish' : 'Publish',
        icon: learningPath.is_published ? Trash2 : CheckCircle,
        onClick: () => learningPath.is_published
            ? unpublishLearningPath(learningPath)
            : publishLearningPath(learningPath)
    },
    { label: 'Hapus', icon: Trash2, variant: 'destructive' as const, onClick: () => deleteLearningPath(learningPath) },
];

const getLearningPathMeta = (learningPath: LearningPathListItem) => [
    { icon: Layers, label: `${learningPath.courses_count ?? 0} kursus` },
    { icon: Clock, label: formatDuration(learningPath.estimated_duration) },
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

const publishLearningPath = (learningPath: LearningPathListItem) => {
    router.put(publish(learningPath.id).url, {}, { preserveScroll: true });
};

const unpublishLearningPath = (learningPath: LearningPathListItem) => {
    router.put(unpublish(learningPath.id).url, {}, { preserveScroll: true });
};

const deleteLearningPath = (learningPath: LearningPathListItem) => {
    if (confirm(`Apakah Anda yakin ingin menghapus jalur pembelajaran "${learningPath.title}"?`)) {
        router.delete(destroy(learningPath.id).url);
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Manajemen Jalur Pembelajaran" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Jalur Pembelajaran Saya"
                description="Kelola dan buat jalur pembelajaran baru"
            >
                <template #actions>
                    <Link :href="create().url">
                        <Button size="lg" class="gap-2">
                            <Plus class="h-5 w-5" />
                            Buat Jalur Pembelajaran
                        </Button>
                    </Link>
                </template>
            </PageHeader>

            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <FilterTabs v-model="status" :tabs="statusTabs" />

                <div class="flex items-center gap-3">
                    <div class="w-full lg:w-80">
                        <SearchInput v-model="search" placeholder="Cari jalur pembelajaran..." />
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
                v-if="learningPaths.data.length === 0"
                :icon="BookOpen"
                title="Belum ada jalur pembelajaran"
                description="Mulai perjalanan mengajar Anda dengan membuat jalur pembelajaran pertama."
                action-label="Buat Jalur Pembelajaran Baru"
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
                        v-for="learningPath in learningPaths.data"
                        :key="learningPath.id"
                        :title="learningPath.title"
                        :subtitle="difficultyLabel(learningPath.difficulty_level)"
                        :description="learningPath.description"
                        :thumbnail-url="learningPath.thumbnail_url ? `/storage/${learningPath.thumbnail_url}` : undefined"
                        :href="show(learningPath.id).url"
                        :badges="[statusBadge(learningPath), { label: difficultyLabel(learningPath.difficulty_level), variant: 'outline' }]"
                        :meta="getLearningPathMeta(learningPath)"
                        :actions="getLearningPathActions(learningPath)"
                    />
                </div>

                <Pagination
                    v-if="learningPaths.last_page > 1"
                    :links="learningPaths.links"
                    :current-page="learningPaths.current_page"
                    :last-page="learningPaths.last_page"
                    :from="learningPaths.from"
                    :to="learningPaths.to"
                    :total="learningPaths.total"
                />
            </template>
        </div>
    </AppLayout>
</template>