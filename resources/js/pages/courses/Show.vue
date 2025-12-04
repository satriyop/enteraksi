<script setup lang="ts">
import { index, edit, destroy } from '@/actions/App/Http/Controllers/CourseController';
import { publish, unpublish, archive } from '@/actions/App/Http/Controllers/CoursePublishController';
import PageHeader from '@/components/crud/PageHeader.vue';
import FormSection from '@/components/crud/FormSection.vue';
import FilterTabs from '@/components/crud/FilterTabs.vue';
import CourseInvitationsTab from '@/components/courses/CourseInvitationsTab.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Pencil,
    Trash2,
    Clock,
    BookOpen,
    ChevronDown,
    ChevronRight,
    FileText,
    PlayCircle,
    Youtube,
    Headphones,
    FileDown,
    Video as VideoCall,
    Globe,
    Eye,
    EyeOff,
    Send,
    Archive,
    RotateCcw,
    Users,
    BarChart3,
    Calendar,
    User,
    Target,
    CheckCircle,
    Layers,
    Mail,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Category {
    id: number;
    name: string;
}

interface Tag {
    id: number;
    name: string;
}

interface Lesson {
    id: number;
    title: string;
    description: string | null;
    order: number;
    content_type: 'text' | 'video' | 'youtube' | 'audio' | 'document' | 'conference';
    estimated_duration_minutes: number;
    is_free_preview: boolean;
}

interface Section {
    id: number;
    title: string;
    description: string | null;
    order: number;
    lessons: Lesson[];
}

interface UserData {
    id: number;
    name: string;
}

interface Course {
    id: number;
    title: string;
    slug: string;
    short_description: string;
    long_description: string | null;
    objectives: string[];
    prerequisites: string[];
    status: 'draft' | 'published' | 'archived';
    visibility: 'public' | 'restricted' | 'hidden';
    difficulty_level: 'beginner' | 'intermediate' | 'advanced';
    estimated_duration_minutes: number;
    thumbnail_path: string | null;
    category: Category | null;
    tags: Tag[];
    sections: Section[];
    user: UserData;
    published_at: string | null;
    created_at: string;
}

interface Invitation {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
    status: 'pending' | 'accepted' | 'declined' | 'expired';
    message: string | null;
    invited_by: string;
    invited_at: string;
    expires_at: string | null;
    responded_at: string | null;
}

interface Props {
    course: Course;
    invitations?: Invitation[];
    can: {
        update: boolean;
        delete: boolean;
        publish: boolean;
        invite?: boolean;
    };
}

const props = withDefaults(defineProps<Props>(), {
    invitations: () => [],
});

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Kursus',
        href: index().url,
    },
    {
        title: props.course.title,
        href: '#',
    },
];

const expandedSections = ref<number[]>(props.course.sections.map((s) => s.id));
const activeTab = ref('content');

const tabs = computed(() => {
    const tabList = [
        { value: 'content', label: 'Konten Kursus', count: totalLessons.value },
    ];

    if (props.can.invite) {
        tabList.push({
            value: 'invitations',
            label: 'Undangan Peserta',
            count: props.invitations.length,
        });
    }

    return tabList;
});

const toggleSection = (sectionId: number) => {
    const idx = expandedSections.value.indexOf(sectionId);
    if (idx === -1) {
        expandedSections.value.push(sectionId);
    } else {
        expandedSections.value.splice(idx, 1);
    }
};

const statusConfig = computed(() => {
    switch (props.course.status) {
        case 'published':
            return { label: 'Terbit', variant: 'default' as const, class: 'bg-emerald-500 hover:bg-emerald-500' };
        case 'draft':
            return { label: 'Draft', variant: 'secondary' as const, class: '' };
        case 'archived':
            return { label: 'Arsip', variant: 'outline' as const, class: '' };
        default:
            return { label: props.course.status, variant: 'secondary' as const, class: '' };
    }
});

const difficultyLabel = (level: string) => {
    switch (level) {
        case 'beginner':
            return 'Pemula';
        case 'intermediate':
            return 'Menengah';
        case 'advanced':
            return 'Lanjutan';
        default:
            return level;
    }
};

const visibilityConfig = computed(() => {
    switch (props.course.visibility) {
        case 'public':
            return { label: 'Publik', icon: Globe };
        case 'restricted':
            return { label: 'Terbatas', icon: Eye };
        case 'hidden':
            return { label: 'Tersembunyi', icon: EyeOff };
        default:
            return { label: props.course.visibility, icon: Globe };
    }
});

const formatDuration = (minutes: number) => {
    if (!minutes) return '0 menit';
    if (minutes < 60) return `${minutes} menit`;
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    if (remainingMinutes === 0) return `${hours} jam`;
    return `${hours}j ${remainingMinutes}m`;
};

const contentTypeIcon = (type: string) => {
    switch (type) {
        case 'video':
            return PlayCircle;
        case 'youtube':
            return Youtube;
        case 'audio':
            return Headphones;
        case 'document':
            return FileDown;
        case 'conference':
            return VideoCall;
        case 'text':
        default:
            return FileText;
    }
};

const deleteCourse = () => {
    if (confirm(`Apakah Anda yakin ingin menghapus kursus "${props.course.title}"?`)) {
        router.delete(destroy(props.course.id).url);
    }
};

const publishCourse = () => {
    router.post(publish(props.course.id).url);
};

const unpublishCourse = () => {
    router.post(unpublish(props.course.id).url);
};

const archiveCourse = () => {
    router.post(archive(props.course.id).url);
};

const totalLessons = computed(() =>
    props.course.sections.reduce((acc, section) => acc + section.lessons.length, 0)
);

const totalSectionDuration = (section: Section) => {
    return section.lessons.reduce((acc, lesson) => acc + lesson.estimated_duration_minutes, 0);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="course.title" />

        <div class="flex h-full flex-1 flex-col">
            <div class="relative overflow-hidden bg-gradient-to-br from-primary via-primary/95 to-primary/90 dark:from-slate-900 dark:via-slate-800 dark:to-slate-900">
                <div class="absolute inset-0 opacity-10" />
                <div class="relative mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    <Link
                        :href="index().url"
                        class="mb-4 inline-flex items-center gap-2 text-sm text-white/70 transition-colors hover:text-white"
                    >
                        <ChevronRight class="h-4 w-4 rotate-180" />
                        Kembali ke Daftar Kursus
                    </Link>

                    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex-1">
                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                <Badge :class="statusConfig.class">
                                    {{ statusConfig.label }}
                                </Badge>
                                <Badge variant="outline" class="border-white/30 text-white">
                                    {{ difficultyLabel(course.difficulty_level) }}
                                </Badge>
                                <Badge v-if="course.category" variant="outline" class="border-white/30 text-white">
                                    {{ course.category.name }}
                                </Badge>
                            </div>
                            <h1 class="mb-3 text-3xl font-bold text-white sm:text-4xl">
                                {{ course.title }}
                            </h1>
                            <p class="mb-4 text-lg text-white/80">
                                {{ course.short_description }}
                            </p>
                            <div class="flex flex-wrap items-center gap-4 text-sm text-white/70">
                                <span class="flex items-center gap-1.5">
                                    <User class="h-4 w-4" />
                                    {{ course.user.name }}
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <Layers class="h-4 w-4" />
                                    {{ course.sections.length }} seksi
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <BookOpen class="h-4 w-4" />
                                    {{ totalLessons }} materi
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <Clock class="h-4 w-4" />
                                    {{ formatDuration(course.estimated_duration_minutes) }}
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 lg:flex-col">
                            <template v-if="can.publish">
                                <Button
                                    v-if="course.status === 'draft'"
                                    class="justify-start bg-white text-primary hover:bg-white/90 lg:w-full"
                                    @click="publishCourse"
                                >
                                    <Send class="size-4 shrink-0" />
                                    <span>Terbitkan</span>
                                </Button>
                                <Button
                                    v-if="course.status === 'published'"
                                    variant="outline"
                                    class="justify-start border-white/30 bg-transparent text-white hover:bg-white/10 lg:w-full"
                                    @click="unpublishCourse"
                                >
                                    <RotateCcw class="size-4 shrink-0" />
                                    <span>Tarik Kembali</span>
                                </Button>
                                <Button
                                    v-if="course.status !== 'archived'"
                                    variant="outline"
                                    class="justify-start border-white/30 bg-transparent text-white hover:bg-white/10 lg:w-full"
                                    @click="archiveCourse"
                                >
                                    <Archive class="size-4 shrink-0" />
                                    <span>Arsipkan</span>
                                </Button>
                            </template>
                            <Link v-if="can.update" :href="edit(course.id).url" class="lg:w-full">
                                <Button variant="outline" class="w-full justify-start border-white/30 bg-transparent text-white hover:bg-white/10">
                                    <Pencil class="size-4 shrink-0" />
                                    <span>Edit Kursus</span>
                                </Button>
                            </Link>
                            <Button
                                v-if="can.delete"
                                variant="outline"
                                class="justify-start border-red-300/50 bg-transparent text-red-200 hover:bg-red-500/20 lg:w-full"
                                @click="deleteCourse"
                            >
                                <Trash2 class="size-4 shrink-0" />
                                <span>Hapus</span>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="grid gap-8 lg:grid-cols-3">
                    <div class="space-y-6 lg:col-span-2">
                        <FilterTabs v-model="activeTab" :tabs="tabs" />

                        <div v-show="activeTab === 'content'">
                            <FormSection title="Konten Kursus" :description="`${course.sections.length} seksi • ${totalLessons} materi • ${formatDuration(course.estimated_duration_minutes)} total durasi`">
                            <div v-if="course.sections.length === 0" class="py-8 text-center">
                                <Layers class="mx-auto h-12 w-12 text-muted-foreground/50" />
                                <p class="mt-4 text-muted-foreground">
                                    Belum ada konten. Mulai dengan menambahkan seksi di halaman edit.
                                </p>
                                <Link v-if="can.update" :href="edit(course.id).url" class="mt-4 inline-block">
                                    <Button>Tambah Konten</Button>
                                </Link>
                            </div>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="(section, sectionIdx) in course.sections"
                                    :key="section.id"
                                    class="overflow-hidden rounded-lg border"
                                >
                                    <button
                                        class="flex w-full items-center justify-between bg-muted/30 p-4 text-left transition-colors hover:bg-muted/50"
                                        @click="toggleSection(section.id)"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-sm font-medium text-primary">
                                                {{ sectionIdx + 1 }}
                                            </div>
                                            <div>
                                                <div class="font-semibold text-foreground">{{ section.title }}</div>
                                                <div class="text-sm text-muted-foreground">
                                                    {{ section.lessons.length }} materi • {{ formatDuration(totalSectionDuration(section)) }}
                                                </div>
                                            </div>
                                        </div>
                                        <component
                                            :is="expandedSections.includes(section.id) ? ChevronDown : ChevronRight"
                                            class="h-5 w-5 text-muted-foreground transition-transform"
                                        />
                                    </button>
                                    <div
                                        v-if="expandedSections.includes(section.id)"
                                        class="divide-y border-t"
                                    >
                                        <div
                                            v-for="(lesson, lessonIdx) in section.lessons"
                                            :key="lesson.id"
                                            class="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-muted/20"
                                        >
                                            <component
                                                :is="contentTypeIcon(lesson.content_type)"
                                                class="h-5 w-5 shrink-0 text-muted-foreground"
                                            />
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm text-muted-foreground">{{ sectionIdx + 1 }}.{{ lessonIdx + 1 }}</span>
                                                    <span class="truncate font-medium">{{ lesson.title }}</span>
                                                    <Badge v-if="lesson.is_free_preview" variant="outline" class="shrink-0 text-xs">
                                                        Preview Gratis
                                                    </Badge>
                                                </div>
                                            </div>
                                            <span class="shrink-0 text-sm text-muted-foreground">
                                                {{ lesson.estimated_duration_minutes }} menit
                                            </span>
                                        </div>
                                        <div v-if="section.lessons.length === 0" class="px-4 py-6 text-center text-sm text-muted-foreground">
                                            Belum ada materi di seksi ini
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </FormSection>

                        <FormSection v-if="course.long_description" title="Tentang Kursus" class="mt-6">
                            <p class="whitespace-pre-wrap leading-relaxed text-muted-foreground">
                                {{ course.long_description }}
                            </p>
                        </FormSection>

                        <FormSection v-if="course.objectives && course.objectives.length > 0" title="Yang Akan Anda Pelajari">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div
                                    v-for="(objective, idx) in course.objectives"
                                    :key="idx"
                                    class="flex items-start gap-3"
                                >
                                    <CheckCircle class="mt-0.5 h-5 w-5 shrink-0 text-emerald-500" />
                                    <span class="text-muted-foreground">{{ objective }}</span>
                                </div>
                            </div>
                        </FormSection>

                        <FormSection v-if="course.prerequisites && course.prerequisites.length > 0" title="Prasyarat" class="mt-6">
                            <ul class="space-y-2">
                                <li
                                    v-for="(prereq, idx) in course.prerequisites"
                                    :key="idx"
                                    class="flex items-start gap-3"
                                >
                                    <Target class="mt-0.5 h-5 w-5 shrink-0 text-primary" />
                                    <span class="text-muted-foreground">{{ prereq }}</span>
                                </li>
                            </ul>
                        </FormSection>
                        </div>

                        <div v-show="activeTab === 'invitations'">
                            <CourseInvitationsTab
                                :course-id="course.id"
                                :invitations="invitations"
                                :can-invite="can.invite ?? false"
                            />
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="sticky top-4 space-y-6">
                            <div v-if="course.thumbnail_path" class="overflow-hidden rounded-xl border">
                                <img
                                    :src="`/storage/${course.thumbnail_path}`"
                                    :alt="course.title"
                                    class="aspect-video w-full object-cover"
                                />
                            </div>

                            <FormSection title="Informasi">
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-muted-foreground">Status</span>
                                        <Badge :class="statusConfig.class">
                                            {{ statusConfig.label }}
                                        </Badge>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-muted-foreground">Visibilitas</span>
                                        <span class="flex items-center gap-1.5 text-sm">
                                            <component :is="visibilityConfig.icon" class="h-4 w-4" />
                                            {{ visibilityConfig.label }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-muted-foreground">Tingkat</span>
                                        <Badge variant="outline">
                                            {{ difficultyLabel(course.difficulty_level) }}
                                        </Badge>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-muted-foreground">Durasi Total</span>
                                        <span class="flex items-center gap-1.5 text-sm font-medium">
                                            <Clock class="h-4 w-4 text-muted-foreground" />
                                            {{ formatDuration(course.estimated_duration_minutes) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-muted-foreground">Jumlah Materi</span>
                                        <span class="flex items-center gap-1.5 text-sm font-medium">
                                            <BookOpen class="h-4 w-4 text-muted-foreground" />
                                            {{ totalLessons }} materi
                                        </span>
                                    </div>
                                    <div v-if="course.category" class="flex items-center justify-between">
                                        <span class="text-sm text-muted-foreground">Kategori</span>
                                        <span class="text-sm font-medium">{{ course.category.name }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-muted-foreground">Instruktur</span>
                                        <span class="text-sm font-medium">{{ course.user.name }}</span>
                                    </div>
                                    <div v-if="course.published_at" class="flex items-center justify-between">
                                        <span class="text-sm text-muted-foreground">Diterbitkan</span>
                                        <span class="flex items-center gap-1.5 text-sm">
                                            <Calendar class="h-4 w-4 text-muted-foreground" />
                                            {{ new Date(course.published_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}
                                        </span>
                                    </div>
                                </div>
                            </FormSection>

                            <FormSection v-if="course.tags && course.tags.length > 0" title="Tag">
                                <div class="flex flex-wrap gap-2">
                                    <Badge v-for="tag in course.tags" :key="tag.id" variant="secondary" class="rounded-full">
                                        {{ tag.name }}
                                    </Badge>
                                </div>
                            </FormSection>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
