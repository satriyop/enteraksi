<script setup lang="ts">
// =============================================================================
// Learning Path Index Page (My Learning Paths)
// View enrolled learning paths and progress
// =============================================================================

import Navbar from '@/components/home/Navbar.vue';
import Footer from '@/components/home/Footer.vue';
import LearningPathEnrollmentCard from '@/components/learning_paths/LearningPathEnrollmentCard.vue';
import { Button } from '@/components/ui/button';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Route, Search } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import type { PaginationLink } from '@/types';
import type { LearningPathEnrollmentItem, LearningPathEnrollmentState } from '@/types/learning-path';

// =============================================================================
// Page-Specific Types
// =============================================================================

interface PaginatedEnrollments {
    data: LearningPathEnrollmentItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

interface Filters {
    status?: LearningPathEnrollmentState | '';
}

interface Props {
    enrollments: PaginatedEnrollments;
    filters: Filters;
}

const props = defineProps<Props>();

const page = usePage();
const appName = computed(() => page.props.name || 'E-Learning');

const selectedStatus = ref(props.filters.status || '');

const statusTabs = [
    { value: '', label: 'Semua' },
    { value: 'active', label: 'Aktif' },
    { value: 'completed', label: 'Selesai' },
    { value: 'dropped', label: 'Dihentikan' },
] as const;

const applyFilter = (status: string) => {
    selectedStatus.value = status as LearningPathEnrollmentState | '';

    const params: Record<string, string> = {};
    if (status) params.status = status;

    router.get('/learner/learning-paths', params, {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Learning Path Saya" />

    <div class="min-h-screen bg-background">
        <Navbar :app-name="appName" />

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold flex items-center gap-2">
                        <Route class="h-8 w-8" />
                        Learning Path Saya
                    </h1>
                    <p class="mt-2 text-muted-foreground">
                        Kelola dan pantau progres belajar Anda
                    </p>
                </div>
                <Link href="/learner/learning-paths/browse">
                    <Button>
                        <Search class="mr-2 h-4 w-4" />
                        Jelajahi Learning Path
                    </Button>
                </Link>
            </div>

            <!-- Status Filter Tabs -->
            <div class="mb-6">
                <div class="flex flex-wrap gap-2 border-b pb-2">
                    <Button
                        v-for="tab in statusTabs"
                        :key="tab.value"
                        :variant="selectedStatus === tab.value ? 'default' : 'ghost'"
                        size="sm"
                        @click="applyFilter(tab.value)"
                    >
                        {{ tab.label }}
                    </Button>
                </div>
            </div>

            <!-- Results Info -->
            <div class="mb-4 text-sm text-muted-foreground">
                {{ enrollments.total }} learning path
                {{ selectedStatus ? `(${statusTabs.find(t => t.value === selectedStatus)?.label})` : '' }}
            </div>

            <!-- Enrollment Grid -->
            <div v-if="enrollments.data.length > 0" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <LearningPathEnrollmentCard
                    v-for="enrollment in enrollments.data"
                    :key="enrollment.id"
                    :enrollment="enrollment"
                />
            </div>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                <Route class="h-16 w-16 text-muted-foreground mb-4" />
                <h2 class="text-xl font-semibold mb-2">
                    {{ selectedStatus ? 'Tidak Ada Learning Path' : 'Belum Ada Learning Path' }}
                </h2>
                <p class="text-muted-foreground mb-4 max-w-md">
                    {{ selectedStatus
                        ? 'Tidak ada learning path dengan status tersebut.'
                        : 'Anda belum terdaftar di learning path manapun. Mulai jelajahi dan daftar ke learning path untuk memulai perjalanan belajar Anda.'
                    }}
                </p>
                <Link v-if="!selectedStatus" href="/learner/learning-paths/browse">
                    <Button>
                        <Search class="mr-2 h-4 w-4" />
                        Jelajahi Learning Path
                    </Button>
                </Link>
                <Button v-else variant="outline" @click="applyFilter('')">
                    Lihat Semua
                </Button>
            </div>

            <!-- Pagination -->
            <div v-if="enrollments.last_page > 1" class="mt-8 flex justify-center gap-2">
                <template v-for="link in enrollments.links" :key="link.label">
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
