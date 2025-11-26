<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Code,
    Palette,
    TrendingUp,
    Camera,
    Music,
    Heart,
    Globe,
    Briefcase,
    type LucideIcon,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    id: number;
    name: string;
    slug: string;
    description?: string;
    coursesCount?: number;
    icon?: string;
    href?: string;
}

const props = withDefaults(defineProps<Props>(), {
    coursesCount: 0,
});

const iconMap: Record<string, LucideIcon> = {
    code: Code,
    palette: Palette,
    trending: TrendingUp,
    camera: Camera,
    music: Music,
    heart: Heart,
    globe: Globe,
    briefcase: Briefcase,
};

const IconComponent = computed(() => {
    if (props.icon && iconMap[props.icon]) {
        return iconMap[props.icon];
    }
    return Briefcase;
});

const categoryHref = computed(() => {
    return props.href ?? `/categories/${props.slug}`;
});
</script>

<template>
    <Link
        :href="categoryHref"
        class="group flex flex-col items-center rounded-xl border bg-card p-6 text-center transition-all hover:border-primary hover:shadow-md dark:border-border"
    >
        <div
            class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground"
        >
            <component :is="IconComponent" class="h-7 w-7" />
        </div>
        <h3 class="mb-1 font-semibold text-foreground">
            {{ name }}
        </h3>
        <p v-if="coursesCount > 0" class="text-sm text-muted-foreground">
            {{ coursesCount }} kursus
        </p>
    </Link>
</template>
