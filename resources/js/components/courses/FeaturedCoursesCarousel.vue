<script setup lang="ts">
// =============================================================================
// FeaturedCoursesCarousel Component
// Displays featured courses in a carousel/slider
// =============================================================================

import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ChevronLeft, ChevronRight, GraduationCap, Clock, Users } from 'lucide-vue-next';
import { formatDuration } from '@/lib/utils';

// =============================================================================
// Types
// =============================================================================

interface FeaturedCourse {
    id: number;
    title: string;
    short_description: string;
    thumbnail_path: string | null;
    duration: number;
    instructor: string;
    category: string | null;
    enrollments_count?: number;
}

interface Props {
    /** Featured courses to display */
    courses: FeaturedCourse[];
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

// =============================================================================
// State
// =============================================================================

const carouselIndex = ref(0);

// =============================================================================
// Methods
// =============================================================================

const prevSlide = (total: number) => {
    carouselIndex.value = carouselIndex.value === 0 ? total - 1 : carouselIndex.value - 1;
};

const nextSlide = (total: number) => {
    carouselIndex.value = carouselIndex.value === total - 1 ? 0 : carouselIndex.value + 1;
};
</script>

<template>
    <section v-if="courses.length > 0" class="relative">
        <div class="overflow-hidden rounded-xl">
            <div
                class="flex transition-transform duration-500 ease-in-out"
                :style="{ transform: `translateX(-${carouselIndex * 100}%)` }"
            >
                <div
                    v-for="course in courses"
                    :key="course.id"
                    class="w-full shrink-0"
                >
                    <div class="relative h-64 md:h-80 lg:h-96 overflow-hidden rounded-xl bg-gradient-to-r from-primary/90 to-primary/70">
                        <img
                            v-if="course.thumbnail_path"
                            :src="course.thumbnail_path"
                            :alt="course.title"
                            class="absolute inset-0 h-full w-full object-cover mix-blend-overlay opacity-50"
                        />
                        <div class="absolute inset-0 flex flex-col justify-end p-6 md:p-8 text-white">
                            <Badge class="mb-2 w-fit" variant="secondary">
                                {{ course.category || 'Umum' }}
                            </Badge>
                            <h2 class="text-2xl md:text-3xl lg:text-4xl font-bold mb-2">
                                {{ course.title }}
                            </h2>
                            <p class="text-sm md:text-base opacity-90 line-clamp-2 mb-4 max-w-2xl">
                                {{ course.short_description }}
                            </p>
                            <div class="flex flex-wrap items-center gap-4 text-sm opacity-80 mb-4">
                                <span class="flex items-center gap-1">
                                    <GraduationCap class="h-4 w-4" />
                                    {{ course.instructor }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <Clock class="h-4 w-4" />
                                    {{ formatDuration(course.duration, 'long') }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <Users class="h-4 w-4" />
                                    {{ course.enrollments_count }} peserta
                                </span>
                            </div>
                            <Link :href="`/courses/${course.id}`">
                                <Button variant="secondary" size="lg">
                                    Lihat Kursus
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Carousel Controls -->
        <button
            v-if="courses.length > 1"
            class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-2 shadow-lg hover:bg-white dark:bg-gray-800/80 dark:hover:bg-gray-800"
            @click="prevSlide(courses.length)"
        >
            <ChevronLeft class="h-5 w-5" />
        </button>
        <button
            v-if="courses.length > 1"
            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-2 shadow-lg hover:bg-white dark:bg-gray-800/80 dark:hover:bg-gray-800"
            @click="nextSlide(courses.length)"
        >
            <ChevronRight class="h-5 w-5" />
        </button>

        <!-- Carousel Indicators -->
        <div v-if="courses.length > 1" class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
            <button
                v-for="(_, idx) in courses"
                :key="idx"
                class="h-2 w-2 rounded-full transition-colors"
                :class="idx === carouselIndex ? 'bg-white' : 'bg-white/50'"
                @click="carouselIndex = idx"
            />
        </div>
    </section>
</template>
