<script setup lang="ts">
// =============================================================================
// Assessment Questions Edit Page
// Uses QuestionEditor and NewQuestionForm components
// =============================================================================

import AssessmentController from '@/actions/App/Http/Controllers/AssessmentController';
import PageHeader from '@/components/crud/PageHeader.vue';
import FormSection from '@/components/crud/FormSection.vue';
import QuestionEditor from '@/components/assessments/QuestionEditor.vue';
import NewQuestionForm from '@/components/assessments/NewQuestionForm.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, QuestionType } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';

// =============================================================================
// Page-Specific Types
// =============================================================================

interface QuestionOption {
    id: number;
    option_text: string;
    is_correct: boolean;
    feedback: string | null;
    order: number;
}

interface EditableQuestion {
    id: number;
    question_text: string;
    question_type: QuestionType;
    points: number;
    feedback: string | null;
    order: number;
    options: QuestionOption[];
}

interface AssessmentWithQuestions {
    id: number;
    title: string;
    questions: EditableQuestion[];
}

interface AssessmentCourse {
    id: number;
    title: string;
}

interface Props {
    course: AssessmentCourse;
    assessment: AssessmentWithQuestions;
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
    { title: 'Edit Pertanyaan', href: AssessmentController.questions({ course: props.course.id, assessment: props.assessment.id }).url },
];

// =============================================================================
// State
// =============================================================================

const questions = ref<EditableQuestion[]>([...props.assessment.questions]);
const showNewQuestionForm = ref(questions.value.length === 0);

// =============================================================================
// Methods
// =============================================================================

const addQuickQuestion = () => {
    const newQuestion: EditableQuestion = {
        id: 0,
        question_text: 'Pertanyaan Baru',
        question_type: 'multiple_choice',
        points: 1,
        feedback: null,
        order: questions.value.length,
        options: [
            { id: 0, option_text: 'Opsi A', is_correct: false, feedback: null, order: 0 },
            { id: 0, option_text: 'Opsi B', is_correct: false, feedback: null, order: 1 },
        ],
    };
    questions.value = [...questions.value, newQuestion];
    showNewQuestionForm.value = false;
};

const handleAddQuestion = (data: {
    question_text: string;
    question_type: QuestionType;
    points: number;
    feedback: string;
    options: { text: string; is_correct: boolean; feedback: string }[];
}) => {
    const newQuestion: EditableQuestion = {
        id: 0,
        question_text: data.question_text || 'Pertanyaan Baru',
        question_type: data.question_type,
        points: data.points || 1,
        feedback: data.feedback || null,
        order: questions.value.length,
        options: data.options.map((opt, index) => ({
            id: 0,
            option_text: opt.text || `Opsi ${String.fromCharCode(65 + index)}`,
            is_correct: opt.is_correct,
            feedback: opt.feedback || null,
            order: index,
        })),
    };
    questions.value = [...questions.value, newQuestion];
    showNewQuestionForm.value = false;
};

const updateQuestion = (index: number, question: EditableQuestion) => {
    questions.value[index] = question;
};

const removeQuestion = (index: number) => {
    questions.value.splice(index, 1);
};

const moveQuestionUp = (index: number) => {
    if (index > 0) {
        [questions.value[index], questions.value[index - 1]] = [
            questions.value[index - 1],
            questions.value[index],
        ];
    }
};

const moveQuestionDown = (index: number) => {
    if (index < questions.value.length - 1) {
        [questions.value[index], questions.value[index + 1]] = [
            questions.value[index + 1],
            questions.value[index],
        ];
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Edit Pertanyaan - ${assessment.title}`" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                :title="`Edit Pertanyaan - ${assessment.title}`"
                description="Kelola pertanyaan penilaian"
                :back-href="`/courses/${course.id}/assessments/${assessment.id}`"
                back-label="Kembali ke Penilaian"
            />

            <Form
                :action="`/courses/${course.id}/assessments/${assessment.id}/questions`"
                method="put"
                class="grid gap-6 lg:grid-cols-3"
                v-slot="{ errors, processing }"
            >
                <div class="space-y-6 lg:col-span-2">
                    <FormSection title="Pertanyaan" description="Kelola pertanyaan penilaian">
                        <div class="space-y-4">
                            <!-- Question List -->
                            <QuestionEditor
                                v-for="(question, qIndex) in questions"
                                :key="question.id || qIndex"
                                :question="question"
                                :index="qIndex"
                                :is-first="qIndex === 0"
                                :is-last="qIndex === questions.length - 1"
                                @move-up="moveQuestionUp(qIndex)"
                                @move-down="moveQuestionDown(qIndex)"
                                @remove="removeQuestion(qIndex)"
                                @update:question="updateQuestion(qIndex, $event)"
                            />

                            <!-- Quick Add Button -->
                            <Button
                                v-if="!showNewQuestionForm && questions.length > 0"
                                type="button"
                                class="w-full gap-2"
                                @click="addQuickQuestion"
                            >
                                <Plus class="h-4 w-4" />
                                Tambah Pertanyaan
                            </Button>

                            <!-- New Question Form -->
                            <NewQuestionForm
                                v-if="showNewQuestionForm"
                                @add="handleAddQuestion"
                                @cancel="showNewQuestionForm = false"
                            />
                        </div>
                    </FormSection>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <div class="space-y-4">
                        <!-- Validation Errors -->
                        <div
                            v-if="errors.questions"
                            class="rounded-lg border border-destructive bg-destructive/10 p-3 text-sm text-destructive"
                        >
                            {{ errors.questions }}
                        </div>

                        <!-- Action Buttons -->
                        <div class="sticky bottom-4 flex gap-3 rounded-xl border bg-card p-4 shadow-lg">
                            <input type="hidden" name="questions" :value="JSON.stringify(questions)" />
                            <Link :href="`/courses/${course.id}/assessments/${assessment.id}`" class="flex-1">
                                <Button type="button" variant="outline" class="w-full h-11">
                                    Batal
                                </Button>
                            </Link>
                            <Button type="submit" class="flex-1 h-11" :disabled="processing">
                                {{ processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
