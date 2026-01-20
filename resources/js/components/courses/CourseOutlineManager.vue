<script setup lang="ts">
// =============================================================================
// CourseOutlineManager Component
// Expandable course outline for instructor view (sections and lessons)
// =============================================================================

import { edit } from '@/actions/App/Http/Controllers/CourseController';
import FormSection from '@/components/crud/FormSection.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Link } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronRight,
    FileText,
    PlayCircle,
    Youtube,
    Headphones,
    FileDown,
    Video as VideoCall,
    Layers,
} from 'lucide-vue-next';
import { ref } from 'vue';
import type { ContentType } from '@/types';
import { formatDuration } from '@/lib/formatters';

// =============================================================================
// Types
// =============================================================================

interface OutlineLesson {
    id: number;
    title: string;
    description: string | null;
    order: number;
    content_type: ContentType;
    estimated_duration_minutes: number;
    is_free_preview: boolean;
}

interface OutlineSection {
    id: number;
    title: string;
    description: string | null;
    order: number;
    lessons: OutlineLesson[];
}

interface Props {
    courseId: number;
    sections: OutlineSection[];
    totalLessons: number;
    totalDurationMinutes: number;
    canUpdate: boolean;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

const expandedSections = ref<number[]>(props.sections.map((s) => s.id));

// =============================================================================
// Methods
// =============================================================================

const toggleSection = (sectionId: number) => {
    const idx = expandedSections.value.indexOf(sectionId);
    if (idx === -1) {
        expandedSections.value.push(sectionId);
    } else {
        expandedSections.value.splice(idx, 1);
    }
};

const contentTypeIcon = (type: string) => {
    const icons: Record<string, typeof PlayCircle> = {
        video: PlayCircle,
        youtube: Youtube,
        audio: Headphones,
        document: FileDown,
        conference: VideoCall,
        text: FileText,
    };
    return icons[type] || FileText;
};

const totalSectionDuration = (section: OutlineSection) => {
    return section.lessons.reduce((acc, lesson) => acc + lesson.estimated_duration_minutes, 0);
};
</script>

<template>
    <FormSection
        title="Konten Kursus"
        :description="`${sections.length} seksi • ${totalLessons} materi • ${formatDuration(totalDurationMinutes, 'long')} total durasi`"
    >
        <!-- Empty State -->
        <div v-if="sections.length === 0" class="py-8 text-center">
            <Layers class="mx-auto h-12 w-12 text-muted-foreground/50" />
            <p class="mt-4 text-muted-foreground">
                Belum ada konten. Mulai dengan menambahkan seksi di halaman edit.
            </p>
            <Link v-if="canUpdate" :href="edit(courseId).url" class="mt-4 inline-block">
                <Button>Tambah Konten</Button>
            </Link>
        </div>

        <!-- Sections List -->
        <div v-else class="space-y-3">
            <div
                v-for="(section, sectionIdx) in sections"
                :key="section.id"
                class="overflow-hidden rounded-lg border"
            >
                <!-- Section Header -->
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
                                {{ section.lessons.length }} materi • {{ formatDuration(totalSectionDuration(section), 'long') }}
                            </div>
                        </div>
                    </div>
                    <component
                        :is="expandedSections.includes(section.id) ? ChevronDown : ChevronRight"
                        class="h-5 w-5 text-muted-foreground transition-transform"
                    />
                </button>

                <!-- Lessons List -->
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
</template>
