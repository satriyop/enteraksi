<script setup lang="ts">
import AssessmentController from '@/actions/App/Http/Controllers/AssessmentController';
import PageHeader from '@/components/crud/PageHeader.vue';
import EmptyState from '@/components/crud/EmptyState.vue';
import DataCard from '@/components/crud/DataCard.vue';
import FilterTabs from '@/components/crud/FilterTabs.vue';
import SearchInput from '@/components/crud/SearchInput.vue';
import Pagination from '@/components/crud/Pagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    type BreadcrumbItem,
    type PaginatedResponse,
    type PaginationLink,
    AssessmentStatus,
    AssessmentVisibility,
} from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, FileText, Clock, CheckCircle, Users, Eye, Pencil, Trash2, LayoutGrid, List, PlayCircle } from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';

// =============================================================================
// Page-Specific Types
// =============================================================================

/** Assessment list item with computed counts */
interface AssessmentListItem {
    id: number;
    title: string;
    description: string;
    status: AssessmentStatus;
    visibility: AssessmentVisibility;
    time_limit_minutes: number | null;
    passing_score: number;
    max_attempts: number;
    questions_count: number;
    attempts_count: number;
    created_at: string;
    updated_at: string;
}

/** Minimal course info for breadcrumbs */
interface AssessmentCourse {
    id: number;
    title: string;
}

interface Filters {
    search?: string;
    status?: string;
}

interface Props {
    course: AssessmentCourse;
    assessments: PaginatedResponse<AssessmentListItem>;
    filters: Filters;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Kursus',
        href: `/courses/${props.course.id}`,
    },
    {
        title: 'Penilaian',
        href: AssessmentController.index(props.course).url,
    },
];

const searchQuery = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const viewMode = ref<'grid' | 'list'>('grid');

const statusTabs = computed(() => [
    { value: '', label: 'Semua', count: undefined },
    { value: 'published', label: 'Dipublikasikan' },
    { value: 'draft', label: 'Draft' },
    { value: 'archived', label: 'Diarsipkan' },
]);

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'published':
            return { text: 'Dipublikasikan', class: 'bg-green-100 text-green-800' };
        case 'draft':
            return { text: 'Draft', class: 'bg-yellow-100 text-yellow-800' };
        case 'archived':
            return { text: 'Diarsipkan', class: 'bg-gray-100 text-gray-800' };
        default:
            return { text: status, class: 'bg-gray-100 text-gray-800' };
    }
};

const getVisibilityBadge = (visibility: string) => {
    switch (visibility) {
        case 'public':
            return { text: 'Publik', class: 'bg-blue-100 text-blue-800' };
        case 'restricted':
            return { text: 'Terbatas', class: 'bg-purple-100 text-purple-800' };
        case 'hidden':
            return { text: 'Tersembunyi', class: 'bg-gray-100 text-gray-800' };
        default:
            return { text: visibility, class: 'bg-gray-100 text-gray-800' };
    }
};

const getAssessmentBadge = (status: string) => {
    switch (status) {
        case 'published':
            return { label: 'Dipublikasikan', variant: 'default' as const };
        case 'draft':
            return { label: 'Draft', variant: 'secondary' as const };
        case 'archived':
            return { label: 'Diarsipkan', variant: 'outline' as const };
        default:
            return { label: status, variant: 'secondary' as const };
    }
};

const getAssessmentMeta = (assessment: Assessment) => [
    { icon: FileText, label: `${assessment.questions_count} pertanyaan` },
    { icon: Users, label: `${assessment.attempts_count} percobaan` },
    { icon: Clock, label: assessment.time_limit_minutes ? `${assessment.time_limit_minutes} menit` : 'Tidak ada batas waktu' },
    { icon: CheckCircle, label: `Nilai kelulusan: ${assessment.passing_score}%` },
];

const getAssessmentActions = (assessment: Assessment) => {
    const actions = [
        { label: 'Lihat', href: `/courses/${props.course.id}/assessments/${assessment.id}`, icon: Eye },
    ];
    
    if (assessment.status !== 'published') {
        actions.push({ label: 'Edit', href: `/courses/${props.course.id}/assessments/${assessment.id}/edit`, icon: Pencil });
    } else {
        actions.push({ label: 'Mulai', href: `/courses/${props.course.id}/assessments/${assessment.id}/start`, icon: PlayCircle });
    }
    
    return actions;
};

let searchTimeout: ReturnType<typeof setTimeout>;

watch(searchQuery, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(AssessmentController.index(props.course).url, { search: value, status: statusFilter.value }, { preserveState: true, replace: true });
    }, 300);
});

watch(statusFilter, (value) => {
    router.get(AssessmentController.index(props.course).url, { search: searchQuery.value, status: value }, { preserveState: true, replace: true });
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Penilaian - ${course.title}`" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                :title="`Penilaian - ${course.title}`"
                description="Kelola semua penilaian untuk kursus ini"
                :back-href="`/courses/${course.id}`"
                back-label="Kembali ke Kursus"
            />

            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <FilterTabs v-model="statusFilter" :tabs="statusTabs" />

                <div class="flex items-center gap-3">
                    <div class="w-full lg:w-80">
                        <SearchInput v-model="searchQuery" placeholder="Cari penilaian..." />
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
                v-if="assessments.data.length === 0"
                :icon="FileText"
                title="Belum ada penilaian"
                description="Mulai membuat penilaian untuk kursus ini."
                action-label="Buat Penilaian Baru"
                :action-href="`/courses/${course.id}/assessments/create`"
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
                        v-for="assessment in assessments.data"
                        :key="assessment.id"
                        :title="assessment.title"
                        :description="assessment.description || 'Tidak ada deskripsi'"
                        :href="`/courses/${course.id}/assessments/${assessment.id}`"
                        :badges="[getAssessmentBadge(assessment.status)]"
                        :meta="getAssessmentMeta(assessment)"
                        :actions="getAssessmentActions(assessment)"
                    />
                </div>

                <Pagination
                    v-if="assessments.last_page > 1"
                    :links="assessments.links"
                    :current-page="assessments.current_page"
                    :last-page="assessments.last_page"
                    :from="assessments.from"
                    :to="assessments.to"
                    :total="assessments.total"
                />
            </template>
        </div>
    </AppLayout>
</template>