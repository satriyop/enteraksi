<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Card, CardContent } from '@/components/ui/card';
import { Star, Quote } from 'lucide-vue-next';

interface Testimonial {
    id: number;
    name: string;
    role: string;
    avatar?: string;
    content: string;
    rating: number;
    course?: string;
}

interface Props {
    title?: string;
    subtitle?: string;
    testimonials?: Testimonial[];
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Apa Kata Mereka',
    subtitle: 'Dengarkan pengalaman belajar dari siswa kami',
    testimonials: () => [
        {
            id: 1,
            name: 'Ahmad Fauzi',
            role: 'Software Developer',
            content:
                'Kursus di platform ini sangat membantu saya dalam meningkatkan skill programming. Materinya lengkap dan instrukturnya sangat berpengalaman.',
            rating: 5,
            course: 'Web Development Bootcamp',
        },
        {
            id: 2,
            name: 'Siti Nurhaliza',
            role: 'UI/UX Designer',
            content:
                'Saya sangat puas dengan kualitas kursus design yang tersedia. Sekarang saya bisa menerapkan ilmu yang didapat di pekerjaan sehari-hari.',
            rating: 5,
            course: 'UI/UX Design Masterclass',
        },
        {
            id: 3,
            name: 'Budi Santoso',
            role: 'Data Analyst',
            content:
                'Platform yang sangat user-friendly dengan konten berkualitas tinggi. Saya berhasil career switch berkat kursus data science di sini.',
            rating: 5,
            course: 'Data Science Fundamentals',
        },
    ],
});

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};
</script>

<template>
    <section class="py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-12 text-center">
                <h2 class="text-2xl font-bold text-foreground sm:text-3xl">
                    {{ title }}
                </h2>
                <p class="mt-2 text-muted-foreground">
                    {{ subtitle }}
                </p>
            </div>

            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="testimonial in testimonials"
                    :key="testimonial.id"
                    class="relative overflow-hidden"
                >
                    <CardContent class="p-6">
                        <Quote
                            class="absolute -right-2 -top-2 h-16 w-16 text-muted/20"
                        />

                        <div class="mb-4 flex items-center gap-1">
                            <Star
                                v-for="i in 5"
                                :key="i"
                                class="h-4 w-4"
                                :class="
                                    i <= testimonial.rating
                                        ? 'fill-amber-400 text-amber-400'
                                        : 'fill-muted text-muted'
                                "
                            />
                        </div>

                        <p class="mb-6 text-sm leading-relaxed text-muted-foreground">
                            "{{ testimonial.content }}"
                        </p>

                        <div class="flex items-center gap-3">
                            <Avatar class="h-10 w-10">
                                <AvatarImage
                                    v-if="testimonial.avatar"
                                    :src="testimonial.avatar"
                                    :alt="testimonial.name"
                                />
                                <AvatarFallback class="bg-primary/10 text-primary text-sm">
                                    {{ getInitials(testimonial.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <div>
                                <p class="font-medium text-foreground">
                                    {{ testimonial.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ testimonial.role }}
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="testimonial.course"
                            class="mt-4 border-t pt-4 text-xs text-muted-foreground"
                        >
                            Mengikuti: {{ testimonial.course }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </section>
</template>
