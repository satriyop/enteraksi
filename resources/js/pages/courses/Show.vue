<script setup lang="ts">
// =============================================================================
// Course Show Page (Instructor View)
// Displays course details with management actions
// =============================================================================

import { index } from '@/actions/App/Http/Controllers/CourseController';
import FilterTabs from '@/components/crud/FilterTabs.vue';
import FormSection from '@/components/crud/FormSection.vue';
import CourseShowHeader from '@/components/courses/CourseShowHeader.vue';
import CourseOutlineManager from '@/components/courses/CourseOutlineManager.vue';
import CourseInfoSidebar from '@/components/courses/CourseInfoSidebar.vue';
import CourseInvitationsTab from '@/components/courses/CourseInvitationsTab.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    type BreadcrumbItem,
    type Category,
    type Tag,
    type ContentType,
    type CourseStatus,
    type CourseVisibility,
    type DifficultyLevel,
    type UserSummary,
} from '@/types';
import { Head } from '@inertiajs/vue3';
import { CheckCircle, Target } from 'lucide-vue-next';
import { ref, computed } from 'vue';

// =============================================================================
// Page-Specific Types
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

interface CourseDetails {
    id: number;
    title: string;
    slug: string;
    short_description: string;
    long_description: string | null;
    objectives: string[];
    prerequisites: string[];
    status: CourseStatus;
    visibility: CourseVisibility;
    difficulty_level: DifficultyLevel;
    estimated_duration_minutes: number;
    thumbnail_path: string | null;
    category: Category | null;
    tags: Tag[];
    sections: OutlineSection[];
    user: UserSummary;
    published_at: string | null;
    created_at: string;
}

interface CourseInvitation {
    id: number;
    user: { id: number; name: string; email: string };
    status: 'pending' | 'accepted' | 'declined' | 'expired';
    message: string | null;
    invited_by: string;
    invited_at: string;
    expires_at: string | null;
    responded_at: string | null;
}

interface Props {
    course: CourseDetails;
    invitations?: CourseInvitation[];
    can: {
        update: boolean;
        delete: boolean;
        publish: boolean;
        invite?: boolean;
    };
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    invitations: () => [],
});

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Kursus', href: index().url },
    { title: props.course.title, href: '#' },
];

// =============================================================================
// State
// =============================================================================

const activeTab = ref('content');

// =============================================================================
// Computed
// =============================================================================

const totalLessons = computed(() =>
    props.course.sections.reduce((acc, section) => acc + section.lessons.length, 0)
);

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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="course.title" />

        <div class="flex h-full flex-1 flex-col">
            <!-- Hero Header -->
            <CourseShowHeader
                :course-id="course.id"
                :title="course.title"
                :short-description="course.short_description"
                :status="course.status"
                :difficulty-level="course.difficulty_level"
                :estimated-duration-minutes="course.estimated_duration_minutes"
                :category="course.category"
                :user="course.user"
                :sections-count="course.sections.length"
                :lessons-count="totalLessons"
                :can="can"
            />

            <!-- Main Content -->
            <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Left Column -->
                    <div class="space-y-6 lg:col-span-2">
                        <FilterTabs v-model="activeTab" :tabs="tabs" />

                        <!-- Content Tab -->
                        <div v-show="activeTab === 'content'">
                            <CourseOutlineManager
                                :course-id="course.id"
                                :sections="course.sections"
                                :total-lessons="totalLessons"
                                :total-duration-minutes="course.estimated_duration_minutes"
                                :can-update="can.update"
                            />

                            <!-- About Course -->
                            <FormSection v-if="course.long_description" title="Tentang Kursus" class="mt-6">
                                <p class="whitespace-pre-wrap leading-relaxed text-muted-foreground">
                                    {{ course.long_description }}
                                </p>
                            </FormSection>

                            <!-- Objectives -->
                            <FormSection v-if="course.objectives?.length > 0" title="Yang Akan Anda Pelajari" class="mt-6">
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

                            <!-- Prerequisites -->
                            <FormSection v-if="course.prerequisites?.length > 0" title="Prasyarat" class="mt-6">
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

                        <!-- Invitations Tab -->
                        <div v-show="activeTab === 'invitations'">
                            <CourseInvitationsTab
                                :course-id="course.id"
                                :invitations="invitations"
                                :can-invite="can.invite ?? false"
                            />
                        </div>
                    </div>

                    <!-- Right Column (Sidebar) -->
                    <div class="space-y-6">
                        <CourseInfoSidebar
                            :course-id="course.id"
                            :thumbnail-path="course.thumbnail_path"
                            :title="course.title"
                            :status="course.status"
                            :visibility="course.visibility"
                            :difficulty-level="course.difficulty_level"
                            :estimated-duration-minutes="course.estimated_duration_minutes"
                            :total-lessons="totalLessons"
                            :category="course.category"
                            :user="course.user"
                            :published-at="course.published_at"
                            :tags="course.tags"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
