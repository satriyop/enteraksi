<script setup lang="ts">
// =============================================================================
// Assessment Grade Page
// Grade participant answers with score, correctness, and feedback
// =============================================================================

import AssessmentController from '@/actions/App/Http/Controllers/AssessmentController';
import PageHeader from '@/components/crud/PageHeader.vue';
import GradeParticipantCard from '@/components/assessments/GradeParticipantCard.vue';
import GradeAnswerCard from '@/components/assessments/GradeAnswerCard.vue';
import GradeSummaryCard from '@/components/assessments/GradeSummaryCard.vue';
import GradeTipsCard from '@/components/assessments/GradeTipsCard.vue';
import GradeStatsCard from '@/components/assessments/GradeStatsCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type QuestionType, type AttemptStatus } from '@/types';
import { Form, Head } from '@inertiajs/vue3';
import { CheckCircle } from 'lucide-vue-next';
import { ref, computed } from 'vue';

// =============================================================================
// Page-Specific Types
// =============================================================================

interface QuestionOption {
    id: number;
    option_text: string;
    is_correct: boolean;
}

interface GradeQuestion {
    id: number;
    question_text: string;
    question_type: QuestionType;
    points: number;
    options: QuestionOption[];
}

interface GradeAnswer {
    id: number;
    question_id: number;
    answer_text: string | null;
    file_path: string | null;
    is_correct: boolean | null;
    score: number | null;
    feedback: string | null;
    question: GradeQuestion;
}

interface AttemptUser {
    id: number;
    name: string;
    email: string;
}

interface GradeAttempt {
    id: number;
    attempt_number: number;
    status: AttemptStatus;
    score: number | null;
    max_score: number;
    percentage: number | null;
    passed: boolean;
    feedback: string | null;
    started_at: string;
    submitted_at: string;
    user: AttemptUser;
    answers: GradeAnswer[];
}

interface GradeAssessment {
    id: number;
    title: string;
    description: string | null;
    passing_score: number;
    questions: GradeQuestion[];
}

interface AssessmentCourse {
    id: number;
    title: string;
}

interface Props {
    course: AssessmentCourse;
    assessment: GradeAssessment;
    attempt: GradeAttempt;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Kursus', href: `/courses/${props.course.id}` },
    { title: 'Penilaian', href: `/courses/${props.course.id}/assessments` },
    { title: props.assessment.title, href: `/courses/${props.course.id}/assessments/${props.assessment.id}` },
    { title: `Percobaan ${props.attempt.attempt_number}`, href: `/courses/${props.course.id}/assessments/${props.assessment.id}/attempts/${props.attempt.id}` },
    { title: 'Penilaian', href: AssessmentController.grade({ course: props.course.id, assessment: props.assessment.id, attempt: props.attempt.id }).url },
];

// =============================================================================
// Form State
// =============================================================================

const form = ref({
    feedback: props.attempt.feedback || '',
    answers: props.attempt.answers.map(answer => ({
        id: answer.id,
        score: answer.score || 0,
        is_correct: answer.is_correct ?? false,
        feedback: answer.feedback || '',
    })),
});

// =============================================================================
// Computed
// =============================================================================

const maxScore = computed(() =>
    props.assessment.questions.reduce((total, question) => total + question.points, 0)
);

const totalScore = ref(
    form.value.answers.reduce((total, answer) => total + (answer.score || 0), 0)
);

const percentage = ref(
    maxScore.value > 0 ? Math.round((totalScore.value / maxScore.value) * 100) : 0
);

const passed = ref(percentage.value >= props.assessment.passing_score);

// =============================================================================
// Methods
// =============================================================================

const recalculateScores = () => {
    totalScore.value = form.value.answers.reduce((total, answer) => total + (answer.score || 0), 0);
    percentage.value = maxScore.value > 0 ? Math.round((totalScore.value / maxScore.value) * 100) : 0;
    passed.value = percentage.value >= props.assessment.passing_score;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Penilaian Percobaan - ${assessment.title}`" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                :title="`Penilaian Percobaan - ${assessment.title}`"
                description="Nilai jawaban peserta untuk penilaian ini"
                :back-href="`/courses/${course.id}/assessments/${assessment.id}/attempts/${attempt.id}`"
                back-label="Kembali ke Percobaan"
            />

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="space-y-6 lg:col-span-2">
                    <GradeParticipantCard
                        :user="attempt.user"
                        :attempt-number="attempt.attempt_number"
                        :submitted-at="attempt.submitted_at"
                    />

                    <Form
                        v-bind="AssessmentController.submitGrade.form({ course: course.id, assessment: assessment.id, attempt: attempt.id })"
                        class="space-y-6"
                        #default="{ errors, processing }"
                    >
                        <GradeAnswerCard
                            v-for="(answer, index) in attempt.answers"
                            :key="answer.id"
                            :answer="answer"
                            :index="index"
                            :errors="errors"
                            v-model:score="form.answers[index].score"
                            v-model:is-correct="form.answers[index].is_correct"
                            v-model:feedback="form.answers[index].feedback"
                            @score-updated="recalculateScores"
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle>Umpan Balik Umum</CardTitle>
                                <CardDescription>
                                    Berikan umpan balik umum untuk seluruh penilaian
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Textarea
                                    id="feedback"
                                    v-model="form.feedback"
                                    name="feedback"
                                    rows="6"
                                    placeholder="Berikan umpan balik umum untuk peserta tentang kinerja mereka secara keseluruhan"
                                />
                                <InputError :message="errors.feedback" />
                            </CardContent>
                            <CardFooter>
                                <Button type="submit" class="gap-2" :disabled="processing">
                                    <CheckCircle class="h-4 w-4" />
                                    {{ processing ? 'Menyimpan...' : 'Simpan Penilaian' }}
                                </Button>
                            </CardFooter>
                        </Card>
                    </Form>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <GradeSummaryCard
                        :total-score="totalScore"
                        :max-score="maxScore"
                        :percentage="percentage"
                        :passed="passed"
                        :passing-score="assessment.passing_score"
                        :total-questions="attempt.answers.length"
                    />

                    <GradeTipsCard />

                    <GradeStatsCard :answers="form.answers" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
