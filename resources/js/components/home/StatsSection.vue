<script setup lang="ts">
import { BookOpen, Users, Award, PlayCircle } from 'lucide-vue-next';

interface Stat {
    label: string;
    value: string;
    icon: 'courses' | 'students' | 'instructors' | 'hours';
}

interface Props {
    stats?: Stat[];
}

const props = withDefaults(defineProps<Props>(), {
    stats: () => [
        { label: 'Kursus Tersedia', value: '500+', icon: 'courses' },
        { label: 'Siswa Terdaftar', value: '10,000+', icon: 'students' },
        { label: 'Instruktur Ahli', value: '100+', icon: 'instructors' },
        { label: 'Jam Konten', value: '5,000+', icon: 'hours' },
    ],
});

const iconMap = {
    courses: BookOpen,
    students: Users,
    instructors: Award,
    hours: PlayCircle,
};
</script>

<template>
    <section class="border-b bg-muted/30 py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
                <div
                    v-for="stat in stats"
                    :key="stat.label"
                    class="text-center"
                >
                    <div
                        class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <component :is="iconMap[stat.icon]" class="h-6 w-6" />
                    </div>
                    <div class="text-2xl font-bold text-foreground md:text-3xl">
                        {{ stat.value }}
                    </div>
                    <div class="text-sm text-muted-foreground">
                        {{ stat.label }}
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
