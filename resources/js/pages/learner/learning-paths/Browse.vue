<script setup lang="ts">
// =============================================================================
// Learning Path Browse Page
// Discover and explore published learning paths
// =============================================================================

import Navbar from '@/components/home/Navbar.vue';
import Footer from '@/components/home/Footer.vue';
import LearningPathBrowseCard from '@/components/learning_paths/LearningPathBrowseCard.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Route, Search, Filter, X } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import type { DifficultyLevel, PaginationLink } from '@/types';
import type { LearningPathItem } from '@/types/learning-path';

// =============================================================================
// Page-Specific Types
// =============================================================================

interface PaginatedLearningPaths {
    data: LearningPathItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

interface Filters {
    search?: string;
    difficulty?: DifficultyLevel | '';
}

interface Props {
    learningPaths: PaginatedLearningPaths;
    enrolledPathIds: number[];
    filters: Filters;
}

const props = defineProps<Props>();

const page = usePage();
const appName = computed(() => page.props.name || 'E-Learning');

const searchQuery = ref(props.filters.search || '');
const selectedDifficulty = ref(props.filters.difficulty || '');
const showFilters = ref(false);

const applyFilters = () => {
    const params: Record<string, string> = {};
    if (searchQuery.value) params.search = searchQuery.value;
    if (selectedDifficulty.value) params.difficulty = selectedDifficulty.value;

    router.get('/learner/learning-paths/browse', params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedDifficulty.value = '';
    router.get('/learner/learning-paths/browse', {}, { preserveState: true });
};

const hasActiveFilters = computed(() => {
    return searchQuery.value || selectedDifficulty.value;
});

const isEnrolled = (pathId: number) => {
    return props.enrolledPathIds.includes(pathId);
};

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout>;
watch(searchQuery, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 500);
});
</script>

<template>
    <Head title="Jelajahi Learning Path" />

    <div class="min-h-screen bg-background">
        <Navbar :app-name="appName" />

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold">Jelajahi Learning Path</h1>
                <p class="mt-2 text-muted-foreground">
                    Temukan jalur belajar terstruktur yang sesuai dengan tujuan pembelajaran Anda
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
                            placeholder="Cari learning path..."
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
                            {{ [selectedDifficulty].filter(Boolean).length }}
                        </Badge>
                    </Button>
                </div>

                <!-- Filter Panel -->
                <div v-if="showFilters" class="rounded-lg border bg-card p-4">
                    <div class="flex flex-wrap gap-4">
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
                Menampilkan {{ learningPaths.data.length }} dari {{ learningPaths.total }} learning path
            </div>

            <!-- Learning Path Grid -->
            <div v-if="learningPaths.data.length > 0" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <LearningPathBrowseCard
                    v-for="path in learningPaths.data"
                    :key="path.id"
                    :learning-path="path"
                    :is-enrolled="isEnrolled(path.id)"
                />
            </div>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                <Route class="h-16 w-16 text-muted-foreground mb-4" />
                <h2 class="text-xl font-semibold mb-2">Tidak Ada Learning Path</h2>
                <p class="text-muted-foreground mb-4">
                    {{ hasActiveFilters ? 'Tidak ada learning path yang sesuai dengan filter Anda.' : 'Belum ada learning path yang tersedia saat ini.' }}
                </p>
                <Button v-if="hasActiveFilters" variant="outline" @click="clearFilters">
                    Hapus Filter
                </Button>
            </div>

            <!-- Pagination -->
            <div v-if="learningPaths.last_page > 1" class="mt-8 flex justify-center gap-2">
                <template v-for="link in learningPaths.links" :key="link.label">
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
