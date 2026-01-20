<script setup lang="ts">
// =============================================================================
// Assessment Attempt Page
// Uses QuestionInput component and useAssessmentTimer composable
// =============================================================================

import AssessmentController from '@/actions/App/Http/Controllers/AssessmentController';
import PageHeader from '@/components/crud/PageHeader.vue';
import QuestionInput from '@/components/assessments/QuestionInput.vue';
import AttemptInfoCard from '@/components/assessments/AttemptInfoCard.vue';
import AttemptNavigationCard from '@/components/assessments/AttemptNavigationCard.vue';
import AttemptTipsCard from '@/components/assessments/AttemptTipsCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, QuestionType, AttemptStatus } from '@/types';
import { Form, Head } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { ref } from 'vue';
import { useAssessmentTimer } from '@/composables/useAssessmentTimer';
import { questionTypeLabel } from '@/lib/utils';

// =============================================================================
// Page-Specific Types
// =============================================================================

interface AttemptQuestionOption {
    id: number;
    option_text: string;
    is_correct: boolean;
}

interface AttemptQuestion {
    id: number;
    question_text: string;
    question_type: QuestionType;
    points: number;
    options: AttemptQuestionOption[];
}

interface AttemptAssessment {
    id: number;
    title: string;
    description: string | null;
    instructions: string | null;
    time_limit_minutes: number | null;
    passing_score: number;
    max_attempts: number;
    questions: AttemptQuestion[];
}

interface CurrentAttempt {
    id: number;
    attempt_number: number;
    status: AttemptStatus;
    started_at: string;
}

interface AssessmentCourse {
    id: number;
    title: string;
}

interface Props {
    course: AssessmentCourse;
    assessment: AttemptAssessment;
    attempt: CurrentAttempt;
    can: { submit: boolean };
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
];

// =============================================================================
// State
// =============================================================================

const form = ref({
    answers: props.assessment.questions.map(question => ({
        question_id: question.id,
        answer_text: '',
        file: null as File | null,
    })),
});

// Timer
const { timeElapsed, timeLeft } = useAssessmentTimer({
    startedAt: props.attempt.started_at,
    timeLimitMinutes: props.assessment.time_limit_minutes,
});

// =============================================================================
// Methods
// =============================================================================

const scrollToQuestion = (index: number) => {
    const element = document.getElementById(`question-${index}`);
    if (element) element.scrollIntoView({ behavior: 'smooth' });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Percobaan ${attempt.attempt_number} - ${assessment.title}`" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                :title="`Percobaan ${attempt.attempt_number} - ${assessment.title}`"
                description="Jawab semua pertanyaan dengan sebaik-baiknya"
                :back-href="`/courses/${course.id}/assessments/${assessment.id}`"
                back-label="Kembali ke Penilaian"
            />

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Instructions -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Instruksi Penilaian</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="assessment.instructions" class="whitespace-pre-wrap">
                                {{ assessment.instructions }}
                            </div>
                            <div v-else class="text-muted-foreground">
                                Tidak ada instruksi khusus untuk penilaian ini.
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Questions Form -->
                    <Form
                        v-bind="AssessmentController.submitAttempt.form()"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                        enctype="multipart/form-data"
                    >
                        <Card
                            v-for="(question, qIndex) in assessment.questions"
                            :key="question.id"
                            :id="`question-${qIndex}`"
                        >
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <span>Pertanyaan {{ qIndex + 1 }}</span>
                                    <span class="text-sm bg-primary/10 text-primary px-2 py-1 rounded-full">
                                        {{ questionTypeLabel(question.question_type) }}
                                    </span>
                                    <span class="text-sm text-muted-foreground ml-auto">
                                        {{ question.points }} poin
                                    </span>
                                </CardTitle>
                                <CardDescription>
                                    {{ question.question_text }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <QuestionInput
                                    v-model:answer-text="form.answers[qIndex].answer_text"
                                    v-model:file="form.answers[qIndex].file"
                                    :question-type="question.question_type"
                                    :question-id="question.id"
                                    :question-index="qIndex"
                                    :options="question.options"
                                    :error="errors[`answers.${qIndex}.answer_text`]"
                                />
                            </CardContent>
                        </Card>

                        <!-- Submit Card -->
                        <Card>
                            <CardFooter class="flex justify-end gap-3">
                                <Button type="button" variant="outline" :disabled="processing">
                                    Simpan Draft
                                </Button>
                                <Button type="submit" class="gap-2" :disabled="processing">
                                    <Check class="h-4 w-4" />
                                    {{ processing ? 'Menyimpan...' : 'Serahkan Penilaian' }}
                                </Button>
                            </CardFooter>
                        </Card>
                    </Form>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <AttemptInfoCard
                        :attempt-number="attempt.attempt_number"
                        :time-elapsed="timeElapsed"
                        :time-left="timeLeft"
                        :has-time-limit="!!assessment.time_limit_minutes"
                        :passing-score="assessment.passing_score"
                        :total-questions="assessment.questions.length"
                    />

                    <AttemptNavigationCard
                        :total-questions="assessment.questions.length"
                        @navigate="scrollToQuestion"
                    />

                    <AttemptTipsCard />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
