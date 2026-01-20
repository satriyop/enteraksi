<script setup lang="ts">
// =============================================================================
// Assessment Attempt Complete Page
// Displays completed attempt results with answers and certificate
// =============================================================================

import AssessmentController from '@/actions/App/Http/Controllers/AssessmentController';
import PageHeader from '@/components/crud/PageHeader.vue';
import AttemptResultsHero from '@/components/assessments/AttemptResultsHero.vue';
import AttemptAnswerReviewCard from '@/components/assessments/AttemptAnswerReviewCard.vue';
import AttemptSummaryCard from '@/components/assessments/AttemptSummaryCard.vue';
import AttemptStatsCard from '@/components/assessments/AttemptStatsCard.vue';
import AttemptCertificateCard from '@/components/assessments/AttemptCertificateCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type QuestionType, type AttemptStatus } from '@/types';
import { Head } from '@inertiajs/vue3';

// =============================================================================
// Page-Specific Types
// =============================================================================

interface CompletedQuestion {
    id: number;
    question_text: string;
    question_type: QuestionType;
    points: number;
}

interface CompletedAnswer {
    id: number;
    question_id: number;
    answer_text: string | null;
    is_correct: boolean | null;
    score: number | null;
    feedback: string | null;
    question: CompletedQuestion;
}

interface CompletedAttempt {
    id: number;
    attempt_number: number;
    status: AttemptStatus;
    score: number;
    max_score: number;
    percentage: number;
    passed: boolean;
    started_at: string;
    submitted_at: string;
    graded_at: string | null;
    feedback: string | null;
    answers: CompletedAnswer[];
}

interface CompletedAssessment {
    id: number;
    title: string;
    description: string | null;
    passing_score: number;
    max_attempts: number;
}

interface AssessmentCourse {
    id: number;
    title: string;
}

interface Props {
    course: AssessmentCourse;
    assessment: CompletedAssessment;
    attempt: CompletedAttempt;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Kursus', href: `/courses/${props.course.id}` },
    { title: 'Penilaian', href: `/courses/${props.course.id}/assessments` },
    { title: props.assessment.title, href: `/courses/${props.course.id}/assessments/${props.assessment.id}` },
    { title: `Percobaan ${props.attempt.attempt_number}`, href: AssessmentController.attempt().url },
    { title: 'Selesai', href: AssessmentController.attemptComplete().url },
];

// =============================================================================
// Computed
// =============================================================================

const correctAnswers = props.attempt.answers.filter(answer => answer.is_correct).length;
const totalQuestions = props.attempt.answers.length;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Percobaan Selesai - ${assessment.title}`" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                :title="`Percobaan Selesai - ${assessment.title}`"
                description="Hasil penilaian Anda"
                :back-href="`/courses/${course.id}/assessments/${assessment.id}`"
                back-label="Kembali ke Penilaian"
            />

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="space-y-6 lg:col-span-2">
                    <AttemptResultsHero
                        :passed="attempt.passed"
                        :score="attempt.score"
                        :max-score="attempt.max_score"
                        :percentage="attempt.percentage"
                        :correct-answers="correctAnswers"
                        :total-questions="totalQuestions"
                        :passing-score="assessment.passing_score"
                        :started-at="attempt.started_at"
                        :submitted-at="attempt.submitted_at"
                        :attempt-number="attempt.attempt_number"
                        :assessment-title="assessment.title"
                        :feedback="attempt.feedback"
                    />

                    <AttemptAnswerReviewCard :answers="attempt.answers" />
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <AttemptSummaryCard
                        :score="attempt.score"
                        :max-score="attempt.max_score"
                        :percentage="attempt.percentage"
                        :passed="attempt.passed"
                        :attempt-number="attempt.attempt_number"
                        :max-attempts="assessment.max_attempts"
                        :started-at="attempt.started_at"
                        :submitted-at="attempt.submitted_at"
                        :course-id="course.id"
                        :assessment-id="assessment.id"
                    />

                    <AttemptStatsCard :answers="attempt.answers" />

                    <AttemptCertificateCard v-if="attempt.passed" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
