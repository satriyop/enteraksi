<script setup lang="ts">
import CategoryCard from './CategoryCard.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';

interface Category {
    id: number;
    name: string;
    slug: string;
    description?: string;
    courses_count?: number;
    icon?: string;
}

interface Props {
    title?: string;
    subtitle?: string;
    categories?: Category[];
    loading?: boolean;
    viewAllHref?: string;
}

withDefaults(defineProps<Props>(), {
    title: 'Jelajahi Kategori',
    subtitle: 'Temukan kursus berdasarkan kategori yang Anda minati',
    categories: () => [],
    loading: false,
    viewAllHref: '/categories',
});
</script>

<template>
    <section class="bg-muted/30 py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8 text-center">
                <h2 class="text-2xl font-bold text-foreground sm:text-3xl">
                    {{ title }}
                </h2>
                <p class="mt-2 text-muted-foreground">
                    {{ subtitle }}
                </p>
            </div>

            <div v-if="loading" class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                <div v-for="i in 6" :key="i" class="space-y-3 rounded-xl border bg-card p-6">
                    <Skeleton class="mx-auto h-14 w-14 rounded-full" />
                    <Skeleton class="mx-auto h-5 w-24" />
                    <Skeleton class="mx-auto h-4 w-16" />
                </div>
            </div>

            <div
                v-else-if="categories.length > 0"
                class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
            >
                <CategoryCard
                    v-for="category in categories"
                    :key="category.id"
                    :id="category.id"
                    :name="category.name"
                    :slug="category.slug"
                    :description="category.description"
                    :courses-count="category.courses_count"
                    :icon="category.icon"
                />
            </div>

            <div
                v-else
                class="flex flex-col items-center justify-center rounded-xl border border-dashed py-12 text-center"
            >
                <p class="text-muted-foreground">
                    Belum ada kategori tersedia.
                </p>
            </div>

            <div v-if="categories.length > 0" class="mt-8 text-center">
                <Link :href="viewAllHref">
                    <Button variant="outline" class="gap-1">
                        Lihat Semua Kategori
                        <ArrowRight class="h-4 w-4" />
                    </Button>
                </Link>
            </div>
        </div>
    </section>
</template>
