<script setup lang="ts">
// =============================================================================
// CourseMetaCard Component
// Displays course metadata in sidebar (instructor, duration, etc.)
// =============================================================================

import StarRating from '@/components/StarRating.vue';
import { Card, CardContent } from '@/components/ui/card';
import {
    User,
    BookOpen,
    Clock,
    Users,
    BarChart,
    FolderOpen,
    Star,
} from 'lucide-vue-next';
import { formatDuration, difficultyLabel } from '@/lib/utils';
import type { Category, UserSummary, DifficultyLevel } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface Props {
    /** Course instructor */
    instructor: UserSummary;
    /** Total lessons count */
    lessonsCount: number;
    /** Total duration in minutes */
    durationMinutes: number;
    /** Number of enrolled students */
    enrollmentsCount: number;
    /** Difficulty level */
    difficultyLevel: DifficultyLevel;
    /** Category (optional) */
    category?: Category | null;
    /** Average rating (optional) */
    averageRating?: number | null;
    /** Total ratings count */
    ratingsCount?: number;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    category: null,
    averageRating: null,
    ratingsCount: 0,
});
</script>

<template>
    <Card>
        <CardContent class="p-6 space-y-4">
            <!-- Rating summary in sidebar -->
            <div v-if="averageRating" class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900">
                    <Star class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">Rating</div>
                    <div class="flex items-center gap-1">
                        <span class="font-bold text-amber-600 dark:text-amber-400">{{ averageRating.toFixed(1) }}</span>
                        <StarRating :rating="averageRating" readonly size="sm" />
                        <span class="text-sm text-muted-foreground">({{ ratingsCount }})</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                    <User class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">Instruktur</div>
                    <div class="font-medium">{{ instructor.name }}</div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                    <BookOpen class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">Jumlah Materi</div>
                    <div class="font-medium">{{ lessonsCount }} materi</div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                    <Clock class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">Total Durasi</div>
                    <div class="font-medium">{{ formatDuration(durationMinutes, 'long') }}</div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                    <Users class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">Peserta</div>
                    <div class="font-medium">{{ enrollmentsCount }} peserta</div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                    <BarChart class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">Tingkat Kesulitan</div>
                    <div class="font-medium">{{ difficultyLabel(difficultyLevel) }}</div>
                </div>
            </div>

            <div v-if="category" class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                    <FolderOpen class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">Kategori</div>
                    <div class="font-medium">{{ category.name }}</div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
