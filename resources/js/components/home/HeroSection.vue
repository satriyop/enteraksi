<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    title?: string;
    subtitle?: string;
    backgroundImage?: string;
}

withDefaults(defineProps<Props>(), {
    title: 'Belajar Tanpa Batas',
    subtitle: 'Tingkatkan keterampilan Anda dengan ribuan kursus online dari instruktur terbaik.',
});

const searchQuery = ref('');

const handleSearch = () => {
    if (searchQuery.value.trim()) {
        router.get('/courses', { search: searchQuery.value });
    }
};
</script>

<template>
    <section
        class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900"
    >
        <div
            class="absolute inset-0 bg-[url('/images/hero-pattern.svg')] opacity-10"
        />
        <div
            class="absolute -right-20 -top-20 h-96 w-96 rounded-full bg-primary/20 blur-3xl"
        />
        <div
            class="absolute -bottom-20 -left-20 h-96 w-96 rounded-full bg-primary/10 blur-3xl"
        />

        <div class="relative mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
            <div class="mx-auto max-w-3xl text-center">
                <h1
                    class="mb-6 text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl"
                >
                    {{ title }}
                </h1>
                <p class="mb-10 text-lg text-white/80 sm:text-xl">
                    {{ subtitle }}
                </p>

                <form
                    @submit.prevent="handleSearch"
                    class="mx-auto flex max-w-xl gap-2"
                >
                    <div class="relative flex-1">
                        <Search
                            class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Cari kursus yang Anda inginkan..."
                            class="h-12 bg-white pl-12 text-base shadow-lg dark:bg-slate-800"
                        />
                    </div>
                    <Button
                        type="submit"
                        size="lg"
                        class="h-12 px-6"
                    >
                        Cari
                    </Button>
                </form>

                <div class="mt-8 flex flex-wrap items-center justify-center gap-4 text-sm text-white/70">
                    <span>Populer:</span>
                    <button
                        type="button"
                        class="rounded-full border border-white/30 px-3 py-1 transition-colors hover:border-white hover:text-white"
                        @click="searchQuery = 'Web Development'; handleSearch()"
                    >
                        Web Development
                    </button>
                    <button
                        type="button"
                        class="rounded-full border border-white/30 px-3 py-1 transition-colors hover:border-white hover:text-white"
                        @click="searchQuery = 'Data Science'; handleSearch()"
                    >
                        Data Science
                    </button>
                    <button
                        type="button"
                        class="rounded-full border border-white/30 px-3 py-1 transition-colors hover:border-white hover:text-white"
                        @click="searchQuery = 'Design'; handleSearch()"
                    >
                        Design
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>
