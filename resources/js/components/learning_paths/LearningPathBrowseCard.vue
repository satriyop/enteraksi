<script setup lang="ts">
// =============================================================================
// LearningPathBrowseCard Component
// Displays a learning path card for browsing/discovery with full details
// =============================================================================

import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Clock, BookOpen, CheckCircle, Route } from 'lucide-vue-next';
import { formatDuration, difficultyLabel, DIFFICULTY_COLORS } from '@/lib/utils';
import type { DifficultyLevel } from '@/types';
import type { LearningPathItem } from '@/types/learning-path';

// =============================================================================
// Types
// =============================================================================

interface Props {
    learningPath: LearningPathItem;
    isEnrolled?: boolean;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    isEnrolled: false,
});

const getDifficultyColor = (level: DifficultyLevel) => {
    const colors = DIFFICULTY_COLORS[level];
    return colors ? `${colors.bg} ${colors.text}` : '';
};

const getEstimatedDuration = () => {
    return formatDuration(props.learningPath.estimated_duration, 'long');
};
</script>

<template>
    <Card class="group overflow-hidden">
        <Link :href="`/learner/learning-paths/${learningPath.id}`">
            <div class="relative aspect-video bg-muted">
                <img
                    v-if="learningPath.thumbnail_url"
                    :src="learningPath.thumbnail_url"
                    :alt="learningPath.title"
                    class="h-full w-full object-cover transition-transform group-hover:scale-105"
                />
                <div v-else class="flex h-full items-center justify-center">
                    <Route class="h-12 w-12 text-muted-foreground" />
                </div>
                <Badge
                    class="absolute left-2 top-2"
                    :class="getDifficultyColor(learningPath.difficulty_level)"
                >
                    {{ difficultyLabel(learningPath.difficulty_level) }}
                </Badge>
                <Badge
                    v-if="isEnrolled"
                    class="absolute right-2 top-2 bg-green-600 text-white hover:bg-green-600"
                >
                    <CheckCircle class="mr-1 h-3 w-3" />
                    Sudah Terdaftar
                </Badge>
            </div>
        </Link>
        <CardContent class="p-4">
            <Link :href="`/learner/learning-paths/${learningPath.id}`">
                <h3 class="font-semibold line-clamp-2 hover:text-primary">
                    {{ learningPath.title }}
                </h3>
            </Link>
            <p v-if="learningPath.description" class="mt-1 text-sm text-muted-foreground line-clamp-2">
                {{ learningPath.description }}
            </p>
            <p v-if="learningPath.creator" class="mt-2 text-sm text-muted-foreground">
                {{ learningPath.creator.name }}
            </p>
            <div class="mt-3 flex items-center gap-3 text-xs text-muted-foreground">
                <span class="flex items-center gap-1">
                    <BookOpen class="h-3 w-3" />
                    {{ learningPath.courses_count }} kursus
                </span>
                <span class="flex items-center gap-1">
                    <Clock class="h-3 w-3" />
                    {{ getEstimatedDuration() }}
                </span>
            </div>
            <Link :href="`/learner/learning-paths/${learningPath.id}`" class="mt-4 block">
                <Button class="w-full" variant="outline" size="sm">
                    Lihat Detail
                </Button>
            </Link>
        </CardContent>
    </Card>
</template>
