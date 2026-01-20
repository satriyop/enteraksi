<script setup lang="ts">
// =============================================================================
// CourseShowHeader Component
// Hero header for instructor's course show page with status and actions
// =============================================================================

import { index, edit, destroy } from '@/actions/App/Http/Controllers/CourseController';
import { publish, unpublish, archive } from '@/actions/App/Http/Controllers/CoursePublishController';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Link, router } from '@inertiajs/vue3';
import {
    Pencil,
    Trash2,
    Clock,
    BookOpen,
    ChevronRight,
    Send,
    Archive,
    RotateCcw,
    Layers,
    User,
} from 'lucide-vue-next';
import { computed } from 'vue';
import type { CourseStatus, DifficultyLevel, Category, UserSummary } from '@/types';
import { formatDuration, difficultyLabel } from '@/lib/formatters';

// =============================================================================
// Types
// =============================================================================

interface Props {
    courseId: number;
    title: string;
    shortDescription: string;
    status: CourseStatus;
    difficultyLevel: DifficultyLevel;
    estimatedDurationMinutes: number;
    category: Category | null;
    user: UserSummary;
    sectionsCount: number;
    lessonsCount: number;
    can: {
        update: boolean;
        delete: boolean;
        publish: boolean;
    };
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

// =============================================================================
// Computed
// =============================================================================

const statusConfig = computed(() => {
    switch (props.status) {
        case 'published':
            return { label: 'Terbit', class: 'bg-emerald-500 hover:bg-emerald-500' };
        case 'draft':
            return { label: 'Draft', class: '' };
        case 'archived':
            return { label: 'Arsip', class: '' };
        default:
            return { label: props.status, class: '' };
    }
});

// =============================================================================
// Methods
// =============================================================================

const deleteCourse = () => {
    if (confirm(`Apakah Anda yakin ingin menghapus kursus "${props.title}"?`)) {
        router.delete(destroy(props.courseId).url);
    }
};

const publishCourse = () => {
    router.post(publish(props.courseId).url);
};

const unpublishCourse = () => {
    router.post(unpublish(props.courseId).url);
};

const archiveCourse = () => {
    router.post(archive(props.courseId).url);
};
</script>

<template>
    <div class="relative overflow-hidden bg-gradient-to-br from-primary via-primary/95 to-primary/90 dark:from-slate-900 dark:via-slate-800 dark:to-slate-900">
        <div class="absolute inset-0 opacity-10" />
        <div class="relative mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <Link
                :href="index().url"
                class="mb-4 inline-flex items-center gap-2 text-sm text-white/70 transition-colors hover:text-white"
            >
                <ChevronRight class="h-4 w-4 rotate-180" />
                Kembali ke Daftar Kursus
            </Link>

            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex-1">
                    <!-- Badges -->
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <Badge :class="statusConfig.class">
                            {{ statusConfig.label }}
                        </Badge>
                        <Badge variant="outline" class="border-white/30 text-white">
                            {{ difficultyLabel(difficultyLevel) }}
                        </Badge>
                        <Badge v-if="category" variant="outline" class="border-white/30 text-white">
                            {{ category.name }}
                        </Badge>
                    </div>

                    <!-- Title & Description -->
                    <h1 class="mb-3 text-3xl font-bold text-white sm:text-4xl">
                        {{ title }}
                    </h1>
                    <p class="mb-4 text-lg text-white/80">
                        {{ shortDescription }}
                    </p>

                    <!-- Meta Info -->
                    <div class="flex flex-wrap items-center gap-4 text-sm text-white/70">
                        <span class="flex items-center gap-1.5">
                            <User class="h-4 w-4" />
                            {{ user.name }}
                        </span>
                        <span class="flex items-center gap-1.5">
                            <Layers class="h-4 w-4" />
                            {{ sectionsCount }} seksi
                        </span>
                        <span class="flex items-center gap-1.5">
                            <BookOpen class="h-4 w-4" />
                            {{ lessonsCount }} materi
                        </span>
                        <span class="flex items-center gap-1.5">
                            <Clock class="h-4 w-4" />
                            {{ formatDuration(estimatedDurationMinutes, 'long') }}
                        </span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap gap-2 lg:flex-col">
                    <template v-if="can.publish">
                        <Button
                            v-if="status === 'draft'"
                            class="justify-start bg-white text-primary hover:bg-white/90 lg:w-full"
                            @click="publishCourse"
                        >
                            <Send class="size-4 shrink-0" />
                            <span>Terbitkan</span>
                        </Button>
                        <Button
                            v-if="status === 'published'"
                            variant="outline"
                            class="justify-start border-white/30 bg-transparent text-white hover:bg-white/10 lg:w-full"
                            @click="unpublishCourse"
                        >
                            <RotateCcw class="size-4 shrink-0" />
                            <span>Tarik Kembali</span>
                        </Button>
                        <Button
                            v-if="status !== 'archived'"
                            variant="outline"
                            class="justify-start border-white/30 bg-transparent text-white hover:bg-white/10 lg:w-full"
                            @click="archiveCourse"
                        >
                            <Archive class="size-4 shrink-0" />
                            <span>Arsipkan</span>
                        </Button>
                    </template>
                    <Link v-if="can.update" :href="edit(courseId).url" class="lg:w-full">
                        <Button variant="outline" class="w-full justify-start border-white/30 bg-transparent text-white hover:bg-white/10">
                            <Pencil class="size-4 shrink-0" />
                            <span>Edit Kursus</span>
                        </Button>
                    </Link>
                    <Button
                        v-if="can.delete"
                        variant="outline"
                        class="justify-start border-red-300/50 bg-transparent text-red-200 hover:bg-red-500/20 lg:w-full"
                        @click="deleteCourse"
                    >
                        <Trash2 class="size-4 shrink-0" />
                        <span>Hapus</span>
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
