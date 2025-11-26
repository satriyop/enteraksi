<script setup lang="ts">
import CourseCard from './CourseCard.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, ArrowRight } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Course {
    id: number;
    title: string;
    slug: string;
    short_description?: string;
    thumbnail_url?: string;
    instructor?: {
        id: number;
        name: string;
        avatar?: string;
    };
    rating?: number;
    reviews_count?: number;
    students_count?: number;
    estimated_duration_minutes?: number;
    lessons_count?: number;
    difficulty_level?: 'beginner' | 'intermediate' | 'advanced';
    is_bestseller?: boolean;
    is_new?: boolean;
}

interface Props {
    title?: string;
    subtitle?: string;
    courses?: Course[];
    loading?: boolean;
    viewAllHref?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Kursus Populer',
    subtitle: 'Pelajari dari kursus terbaik yang dipilih oleh siswa kami',
    courses: () => [],
    loading: false,
    viewAllHref: '/courses',
});

const scrollContainer = ref<HTMLElement | null>(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(true);

const updateScrollButtons = () => {
    if (!scrollContainer.value) return;
    canScrollLeft.value = scrollContainer.value.scrollLeft > 0;
    canScrollRight.value =
        scrollContainer.value.scrollLeft <
        scrollContainer.value.scrollWidth - scrollContainer.value.clientWidth - 10;
};

const scroll = (direction: 'left' | 'right') => {
    if (!scrollContainer.value) return;
    const scrollAmount = 320;
    scrollContainer.value.scrollBy({
        left: direction === 'left' ? -scrollAmount : scrollAmount,
        behavior: 'smooth',
    });
    setTimeout(updateScrollButtons, 300);
};

const showNavigation = computed(() => props.courses.length > 4);
</script>

<template>
    <section class="py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8 flex items-end justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-foreground sm:text-3xl">
                        {{ title }}
                    </h2>
                    <p class="mt-2 text-muted-foreground">
                        {{ subtitle }}
                    </p>
                </div>
                <div class="hidden items-center gap-2 sm:flex">
                    <template v-if="showNavigation">
                        <Button
                            variant="outline"
                            size="icon"
                            :disabled="!canScrollLeft"
                            @click="scroll('left')"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            :disabled="!canScrollRight"
                            @click="scroll('right')"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </template>
                    <Link :href="viewAllHref">
                        <Button variant="ghost" class="gap-1">
                            Lihat Semua
                            <ArrowRight class="h-4 w-4" />
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-if="loading" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="i in 4" :key="i" class="space-y-3">
                    <Skeleton class="aspect-video w-full rounded-lg" />
                    <Skeleton class="h-5 w-3/4" />
                    <Skeleton class="h-4 w-1/2" />
                    <Skeleton class="h-4 w-2/3" />
                </div>
            </div>

            <div
                v-else-if="courses.length > 0"
                ref="scrollContainer"
                class="scrollbar-hide -mx-4 flex gap-6 overflow-x-auto px-4 pb-4 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 lg:grid-cols-4"
                @scroll="updateScrollButtons"
            >
                <div
                    v-for="course in courses"
                    :key="course.id"
                    class="w-72 flex-shrink-0 sm:w-auto"
                >
                    <CourseCard
                        :id="course.id"
                        :title="course.title"
                        :slug="course.slug"
                        :short-description="course.short_description"
                        :thumbnail-url="course.thumbnail_url"
                        :instructor="course.instructor"
                        :rating="course.rating"
                        :reviews-count="course.reviews_count"
                        :students-count="course.students_count"
                        :duration-minutes="course.estimated_duration_minutes"
                        :lessons-count="course.lessons_count"
                        :difficulty="course.difficulty_level"
                        :is-bestseller="course.is_bestseller"
                        :is-new="course.is_new"
                    />
                </div>
            </div>

            <div
                v-else
                class="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center"
            >
                <p class="text-muted-foreground">
                    Belum ada kursus tersedia.
                </p>
            </div>

            <div class="mt-6 text-center sm:hidden">
                <Link :href="viewAllHref">
                    <Button variant="outline" class="w-full gap-1">
                        Lihat Semua Kursus
                        <ArrowRight class="h-4 w-4" />
                    </Button>
                </Link>
            </div>
        </div>
    </section>
</template>

<style scoped>
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
</style>
