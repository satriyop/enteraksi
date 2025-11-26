<script setup lang="ts">
import Navbar from '@/components/home/Navbar.vue';
import HeroSection from '@/components/home/HeroSection.vue';
import StatsSection from '@/components/home/StatsSection.vue';
import FeaturedCourses from '@/components/home/FeaturedCourses.vue';
import CategoriesSection from '@/components/home/CategoriesSection.vue';
import TestimonialsSection from '@/components/home/TestimonialsSection.vue';
import CTASection from '@/components/home/CTASection.vue';
import Footer from '@/components/home/Footer.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Instructor {
    id: number;
    name: string;
    avatar?: string;
}

interface Category {
    id: number;
    name: string;
    slug: string;
}

interface Course {
    id: number;
    title: string;
    slug: string;
    short_description?: string;
    thumbnail_url?: string;
    instructor?: Instructor;
    category?: Category;
    rating?: number;
    reviews_count?: number;
    students_count?: number;
    estimated_duration_minutes?: number;
    lessons_count?: number;
    difficulty_level?: 'beginner' | 'intermediate' | 'advanced';
    is_bestseller?: boolean;
    is_new?: boolean;
}

interface CategoryWithCount {
    id: number;
    name: string;
    slug: string;
    description?: string;
    courses_count?: number;
    icon?: string;
}

interface Stat {
    label: string;
    value: string | number;
    icon: 'courses' | 'students' | 'instructors' | 'hours';
}

interface Props {
    canRegister: boolean;
    featuredCourses?: Course[];
    popularCourses?: Course[];
    categories?: CategoryWithCount[];
    stats?: Stat[];
}

const props = withDefaults(defineProps<Props>(), {
    canRegister: true,
    featuredCourses: () => [],
    popularCourses: () => [],
    categories: () => [],
    stats: () => [],
});

const page = usePage();
const appName = computed(() => page.props.name || 'E-Learning');

const formattedStats = computed(() => {
    return props.stats.map((stat) => ({
        ...stat,
        value: typeof stat.value === 'number' ? stat.value.toLocaleString() : stat.value,
    }));
});
</script>

<template>
    <Head :title="`Selamat Datang - ${appName}`">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="min-h-screen bg-background">
        <Navbar :app-name="appName" :can-register="canRegister" />

        <main>
            <HeroSection
                title="Belajar Tanpa Batas"
                subtitle="Tingkatkan keterampilan Anda dengan ribuan kursus online dari instruktur terbaik. Mulai perjalanan belajar Anda hari ini."
            />

            <StatsSection v-if="formattedStats.length > 0" :stats="formattedStats" />

            <FeaturedCourses
                v-if="featuredCourses.length > 0"
                title="Kursus Terbaru"
                subtitle="Kursus terbaru yang baru saja ditambahkan ke platform kami"
                :courses="featuredCourses"
                view-all-href="/courses"
            />

            <CategoriesSection
                v-if="categories.length > 0"
                title="Jelajahi Kategori"
                subtitle="Temukan kursus berdasarkan kategori yang Anda minati"
                :categories="categories"
                view-all-href="/categories"
            />

            <FeaturedCourses
                v-if="popularCourses.length > 0"
                title="Kursus Populer"
                subtitle="Pelajari dari kursus terbaik yang dipilih oleh siswa kami"
                :courses="popularCourses"
                view-all-href="/courses?sort=popular"
                class="bg-muted/30"
            />

            <TestimonialsSection
                title="Apa Kata Mereka"
                subtitle="Dengarkan pengalaman belajar dari siswa kami"
            />

            <CTASection
                title="Mulai Belajar Hari Ini"
                subtitle="Bergabung dengan ribuan siswa lainnya dan mulai perjalanan belajar Anda."
                :features="[
                    'Akses seumur hidup ke semua materi',
                    'Sertifikat penyelesaian',
                    'Komunitas belajar yang aktif',
                    'Dukungan dari instruktur',
                ]"
                primary-button-text="Daftar Gratis"
                primary-button-href="/register"
                secondary-button-text="Lihat Kursus"
                secondary-button-href="/courses"
            />
        </main>

        <Footer :app-name="appName" />
    </div>
</template>
