<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Link } from '@inertiajs/vue3';
import { Star, Users, Clock, Play } from 'lucide-vue-next';
import { computed } from 'vue';

interface Instructor {
    id: number;
    name: string;
    avatar?: string;
}

interface Props {
    id: number;
    title: string;
    slug: string;
    shortDescription?: string;
    thumbnailUrl?: string;
    instructor?: Instructor;
    rating?: number;
    reviewsCount?: number;
    studentsCount?: number;
    durationMinutes?: number;
    lessonsCount?: number;
    difficulty?: 'beginner' | 'intermediate' | 'advanced';
    isBestseller?: boolean;
    isNew?: boolean;
    href?: string;
}

const props = withDefaults(defineProps<Props>(), {
    rating: 0,
    reviewsCount: 0,
    studentsCount: 0,
    durationMinutes: 0,
    lessonsCount: 0,
    isBestseller: false,
    isNew: false,
});

const formattedDuration = computed(() => {
    if (props.durationMinutes < 60) {
        return `${props.durationMinutes} menit`;
    }
    const hours = Math.floor(props.durationMinutes / 60);
    const minutes = props.durationMinutes % 60;
    if (minutes === 0) {
        return `${hours} jam`;
    }
    return `${hours}j ${minutes}m`;
});

const formattedStudents = computed(() => {
    if (props.studentsCount >= 1000) {
        return `${(props.studentsCount / 1000).toFixed(1)}rb`;
    }
    return props.studentsCount.toString();
});

const difficultyLabel = computed(() => {
    switch (props.difficulty) {
        case 'beginner':
            return 'Pemula';
        case 'intermediate':
            return 'Menengah';
        case 'advanced':
            return 'Lanjutan';
        default:
            return '';
    }
});

const courseHref = computed(() => {
    return props.href ?? `/courses/${props.id}`;
});
</script>

<template>
    <Link
        :href="courseHref"
        class="group flex flex-col overflow-hidden rounded-lg border bg-card transition-all hover:shadow-lg dark:border-border"
    >
        <div class="relative aspect-video w-full overflow-hidden bg-muted">
            <img
                v-if="thumbnailUrl"
                :src="thumbnailUrl"
                :alt="title"
                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
            />
            <div
                v-else
                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary/10 to-primary/5"
            >
                <Play class="h-12 w-12 text-primary/40" />
            </div>
            <div class="absolute left-2 top-2 flex gap-1.5">
                <Badge
                    v-if="isBestseller"
                    class="bg-primary text-primary-foreground hover:bg-primary/90"
                >
                    Bestseller
                </Badge>
                <Badge
                    v-if="isNew"
                    class="bg-emerald-500 text-white hover:bg-emerald-500"
                >
                    Baru
                </Badge>
            </div>
        </div>

        <div class="flex flex-1 flex-col p-4">
            <h3
                class="mb-1 line-clamp-2 text-base font-semibold leading-tight text-foreground group-hover:text-primary"
            >
                {{ title }}
            </h3>

            <p
                v-if="shortDescription"
                class="mb-2 line-clamp-2 text-sm text-muted-foreground"
            >
                {{ shortDescription }}
            </p>

            <p v-if="instructor" class="mb-2 text-xs text-muted-foreground">
                {{ instructor.name }}
            </p>

            <div v-if="rating > 0" class="mb-2 flex items-center gap-1">
                <span class="text-sm font-bold text-amber-600 dark:text-amber-500">
                    {{ rating.toFixed(1) }}
                </span>
                <div class="flex items-center">
                    <Star
                        v-for="i in 5"
                        :key="i"
                        class="h-3.5 w-3.5"
                        :class="
                            i <= Math.round(rating)
                                ? 'fill-amber-400 text-amber-400'
                                : 'fill-muted text-muted'
                        "
                    />
                </div>
                <span class="text-xs text-muted-foreground">
                    ({{ reviewsCount.toLocaleString() }})
                </span>
            </div>

            <div class="mt-auto flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                <span v-if="studentsCount > 0" class="flex items-center gap-1">
                    <Users class="h-3.5 w-3.5" />
                    {{ formattedStudents }} siswa
                </span>
                <span v-if="lessonsCount > 0" class="flex items-center gap-1">
                    <Play class="h-3.5 w-3.5" />
                    {{ lessonsCount }} materi
                </span>
                <span v-if="durationMinutes > 0" class="flex items-center gap-1">
                    <Clock class="h-3.5 w-3.5" />
                    {{ formattedDuration }}
                </span>
            </div>

            <div v-if="difficulty" class="mt-3">
                <Badge variant="secondary" class="text-xs">
                    {{ difficultyLabel }}
                </Badge>
            </div>
        </div>
    </Link>
</template>
