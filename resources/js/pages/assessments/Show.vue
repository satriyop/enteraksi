<script setup lang="ts">
// =============================================================================
// Assessment Show Page
// Displays assessment details, settings, and latest attempt
// =============================================================================

import AssessmentController from '@/actions/App/Http/Controllers/AssessmentController';
import PageHeader from '@/components/crud/PageHeader.vue';
import AssessmentInfoCard from '@/components/assessments/AssessmentInfoCard.vue';
import AssessmentSettingsCard from '@/components/assessments/AssessmentSettingsCard.vue';
import AssessmentAttemptCard from '@/components/assessments/AssessmentAttemptCard.vue';
import AssessmentActionsCard from '@/components/assessments/AssessmentActionsCard.vue';
import AssessmentStatsCard from '@/components/assessments/AssessmentStatsCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    type BreadcrumbItem,
    type AssessmentStatus,
    type AssessmentVisibility,
    type AttemptStatus,
} from '@/types';
import { Head } from '@inertiajs/vue3';

// =============================================================================
// Page-Specific Types
// =============================================================================

/** Full assessment details with computed counts */
interface AssessmentDetails {
    id: number;
    title: string;
    description: string | null;
    instructions: string | null;
    time_limit_minutes: number | null;
    passing_score: number;
    max_attempts: number;
    shuffle_questions: boolean;
    show_correct_answers: boolean;
    allow_review: boolean;
    status: AssessmentStatus;
    visibility: AssessmentVisibility;
    questions_count: number;
    attempts_count: number;
    created_at: string;
    updated_at: string;
}

/** Minimal course info for breadcrumbs */
interface AssessmentCourse {
    id: number;
    title: string;
}

/** User's attempt summary */
interface AttemptSummary {
    id: number;
    attempt_number: number;
    status: AttemptStatus;
    score: number | null;
    max_score: number;
    percentage: number | null;
    passed: boolean;
    started_at: string;
    submitted_at: string | null;
    graded_at: string | null;
}

/** Permission flags for the current user */
interface AssessmentPermissions {
    update: boolean;
    delete: boolean;
    publish: boolean;
    attempt: boolean;
}

interface Props {
    course: AssessmentCourse;
    assessment: AssessmentDetails;
    canAttempt: boolean;
    latestAttempt: AttemptSummary | null;
    can: AssessmentPermissions;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Kursus', href: `/courses/${props.course.id}` },
    { title: 'Penilaian', href: `/courses/${props.course.id}/assessments` },
    {
        title: props.assessment.title,
        href: AssessmentController.show({ course: props.course.id, assessment: props.assessment.id }).url,
    },
];

// =============================================================================
// Computed
// =============================================================================

const attemptDetailHref = props.latestAttempt
    ? `/courses/${props.course.id}/assessments/${props.assessment.id}/attempts/${props.latestAttempt.id}`
    : '';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="assessment.title" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                :title="assessment.title"
                description="Detail penilaian"
                :back-href="`/courses/${course.id}/assessments`"
                back-label="Kembali ke Daftar Penilaian"
            />

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="space-y-6 lg:col-span-2">
                    <AssessmentInfoCard
                        :status="assessment.status"
                        :visibility="assessment.visibility"
                        :description="assessment.description"
                        :instructions="assessment.instructions"
                    />

                    <AssessmentSettingsCard
                        :passing-score="assessment.passing_score"
                        :max-attempts="assessment.max_attempts"
                        :time-limit-minutes="assessment.time_limit_minutes"
                        :questions-count="assessment.questions_count"
                        :shuffle-questions="assessment.shuffle_questions"
                        :show-correct-answers="assessment.show_correct_answers"
                        :allow-review="assessment.allow_review"
                    />

                    <AssessmentAttemptCard
                        v-if="latestAttempt"
                        :attempt="latestAttempt"
                        :detail-href="attemptDetailHref"
                    />
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <AssessmentActionsCard
                        :course-id="course.id"
                        :assessment-id="assessment.id"
                        :status="assessment.status"
                        :can="can"
                    />

                    <AssessmentStatsCard
                        :attempts-count="assessment.attempts_count"
                        :questions-count="assessment.questions_count"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
