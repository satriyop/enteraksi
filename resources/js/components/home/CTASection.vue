<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle } from 'lucide-vue-next';

interface Props {
    title?: string;
    subtitle?: string;
    features?: string[];
    primaryButtonText?: string;
    primaryButtonHref?: string;
    secondaryButtonText?: string;
    secondaryButtonHref?: string;
}

withDefaults(defineProps<Props>(), {
    title: 'Mulai Belajar Hari Ini',
    subtitle: 'Bergabung dengan ribuan siswa lainnya dan mulai perjalanan belajar Anda.',
    features: () => [
        'Akses seumur hidup ke semua materi',
        'Sertifikat penyelesaian',
        'Komunitas belajar yang aktif',
        'Dukungan dari instruktur',
    ],
    primaryButtonText: 'Daftar Gratis',
    primaryButtonHref: '/register',
    secondaryButtonText: 'Lihat Kursus',
    secondaryButtonHref: '/courses',
});
</script>

<template>
    <section
        class="relative overflow-hidden bg-gradient-to-br from-primary via-primary/95 to-primary/90 py-20 dark:from-slate-900 dark:via-slate-800 dark:to-slate-900"
    >
        <div
            class="absolute inset-0 bg-[url('/images/cta-pattern.svg')] opacity-10"
        />
        <div
            class="absolute -left-20 -top-20 h-80 w-80 rounded-full bg-white/10 blur-3xl"
        />
        <div
            class="absolute -bottom-20 -right-20 h-80 w-80 rounded-full bg-white/5 blur-3xl"
        />

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div>
                    <h2 class="text-3xl font-bold text-white sm:text-4xl">
                        {{ title }}
                    </h2>
                    <p class="mt-4 text-lg text-white/80">
                        {{ subtitle }}
                    </p>

                    <ul class="mt-8 space-y-4">
                        <li
                            v-for="(feature, index) in features"
                            :key="index"
                            class="flex items-center gap-3 text-white/90"
                        >
                            <CheckCircle class="h-5 w-5 flex-shrink-0 text-emerald-400" />
                            <span>{{ feature }}</span>
                        </li>
                    </ul>
                </div>

                <div class="flex flex-col items-center gap-4 lg:items-end">
                    <Link :href="primaryButtonHref" class="w-full sm:w-auto">
                        <Button
                            size="lg"
                            class="h-14 w-full bg-white px-8 text-base font-semibold text-primary hover:bg-white/90 sm:w-auto dark:bg-white dark:text-slate-900 dark:hover:bg-white/90"
                        >
                            {{ primaryButtonText }}
                            <ArrowRight class="ml-2 h-5 w-5" />
                        </Button>
                    </Link>
                    <Link :href="secondaryButtonHref" class="w-full sm:w-auto">
                        <Button
                            variant="outline"
                            size="lg"
                            class="h-14 w-full border-white/30 bg-transparent px-8 text-base text-white hover:bg-white/10 hover:text-white sm:w-auto"
                        >
                            {{ secondaryButtonText }}
                        </Button>
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
