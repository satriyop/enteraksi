<script setup lang="ts">
import AssessmentController from '@/actions/App/Http/Controllers/AssessmentController';
import PageHeader from '@/components/crud/PageHeader.vue';
import FormSection from '@/components/crud/FormSection.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { Plus, X, GripVertical, Pencil, Trash2, ArrowUp, ArrowDown } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Assessment {
    id: number;
    title: string;
    questions: Question[];
}

interface Question {
    id: number;
    question_text: string;
    question_type: string;
    points: number;
    feedback: string;
    order: number;
    options: QuestionOption[];
}

interface QuestionOption {
    id: number;
    option_text: string;
    is_correct: boolean;
    feedback: string;
    order: number;
}

interface Course {
    id: number;
    title: string;
}

interface Props {
    course: Course;
    assessment: Assessment;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Kursus',
        href: `/courses/${props.course.id}`,
    },
    {
        title: 'Penilaian',
        href: `/courses/${props.course.id}/assessments`,
    },
    {
        title: props.assessment.title,
        href: AssessmentController.show({ course: props.course.id, assessment: props.assessment.id }).url,
    },
    {
        title: 'Edit Pertanyaan',
        href: `/courses/${props.course.id}/assessments/${props.assessment.id}/questions`,
    },
];

const questions = ref([...props.assessment.questions]);
const showNewQuestionForm = ref(questions.value.length === 0);

const toggleNewQuestionForm = () => {
    showNewQuestionForm.value = !showNewQuestionForm.value;
};

const newQuestion = ref({
    id: 0,
    question_text: '',
    question_type: 'multiple_choice',
    points: 1,
    feedback: '',
    options: [
        { text: '', is_correct: false, feedback: '' },
        { text: '', is_correct: false, feedback: '' },
    ],
});

const addOption = (question: any) => {
    question.options.push({ text: '', is_correct: false, feedback: '' });
};

const removeOption = (question: any, index: number) => {
    if (question.options.length > 2) {
        question.options.splice(index, 1);
    }
};

const addNewQuestion = () => {
    const newQuestionData = {
        id: 0,
        question_text: newQuestion.value.question_text || 'Pertanyaan Baru',
        question_type: newQuestion.value.question_type || 'multiple_choice',
        points: newQuestion.value.points || 1,
        feedback: newQuestion.value.feedback || '',
        order: questions.value.length,
        options: newQuestion.value.options.map((opt, index) => ({
            id: 0,
            option_text: opt.text || `Opsi ${String.fromCharCode(65 + index)}`,
            is_correct: opt.is_correct || false,
            feedback: opt.feedback || '',
            order: index,
        })),
    };

    questions.value = [...questions.value, newQuestionData];

    // Reset new question form
    newQuestion.value = {
        id: 0,
        question_text: '',
        question_type: 'multiple_choice',
        points: 1,
        feedback: '',
        options: [
            { text: '', is_correct: false, feedback: '' },
            { text: '', is_correct: false, feedback: '' },
        ],
    };
    
    // Hide the form after adding - user can click the button again to add another
    showNewQuestionForm.value = false;
};

// New function to add a quick question with default values
const addQuickQuestion = () => {
    const quickQuestion = {
        id: 0,
        question_text: 'Pertanyaan Baru',
        question_type: 'multiple_choice',
        points: 1,
        feedback: '',
        order: questions.value.length,
        options: [
            { id: 0, option_text: 'Opsi A', is_correct: false, feedback: '', order: 0 },
            { id: 0, option_text: 'Opsi B', is_correct: false, feedback: '', order: 1 },
        ],
    };

    questions.value = [...questions.value, quickQuestion];
    
    // Don't show the new question form - the question is already added and visible in the list
    // Users can edit it directly in the list or click the button again to add another
    showNewQuestionForm.value = false;
};

const removeQuestion = (index: number) => {
    questions.value.splice(index, 1);
};

const moveQuestionUp = (index: number) => {
    if (index > 0) {
        [questions.value[index], questions.value[index - 1]] = [questions.value[index - 1], questions.value[index]];
    }
};

const moveQuestionDown = (index: number) => {
    if (index < questions.value.length - 1) {
        [questions.value[index], questions.value[index + 1]] = [questions.value[index + 1], questions.value[index]];
    }
};

const getQuestionTypeLabel = (type: string) => {
    const types: Record<string, string> = {
        multiple_choice: 'Pilihan Ganda',
        true_false: 'Benar/Salah',
        matching: 'Pencocokan',
        short_answer: 'Jawaban Singkat',
        essay: 'Esai',
        file_upload: 'Unggah Berkas',
    };
    return types[type] || type;
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
                @submit.prevent="() => {}"
            >
                <div class="space-y-6 lg:col-span-2">
                    <FormSection title="Pertanyaan" description="Kelola pertanyaan penilaian">
                        <div class="space-y-4">
                            <div v-for="(question, qIndex) in questions" :key="question.id" class="rounded-lg border p-4">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-2">
                                        <GripVertical class="h-4 w-4 cursor-move text-muted-foreground" />
                                        <h4 class="font-medium">Pertanyaan {{ qIndex + 1 }}</h4>
                                        <span class="text-xs bg-primary/10 text-primary px-2 py-1 rounded-full">
                                            {{ getQuestionTypeLabel(question.question_type) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Button type="button" variant="ghost" size="icon" @click="moveQuestionUp(qIndex)">
                                            <ArrowUp class="h-4 w-4" />
                                        </Button>
                                        <Button type="button" variant="ghost" size="icon" @click="moveQuestionDown(qIndex)">
                                            <ArrowDown class="h-4 w-4" />
                                        </Button>
                                        <Button type="button" variant="ghost" size="icon" class="text-destructive hover:text-destructive" @click="removeQuestion(qIndex)">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">Teks Pertanyaan</Label>
                                        <textarea
                                            v-model="question.question_text"
                                            rows="3"
                                            class="flex w-full rounded-lg border border-input bg-background px-4 py-3 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">Poin</Label>
                                        <Input v-model="question.points" type="number" min="1" class="h-11 w-24" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">Umpan Balik</Label>
                                        <textarea
                                            v-model="question.feedback"
                                            rows="2"
                                            class="flex w-full rounded-lg border border-input bg-background px-4 py-3 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary disabled:cursor-not-allowed disabled:opacity-50"
                                            placeholder="Umpan balik untuk pertanyaan ini"
                                        />
                                    </div>

                                    <div v-if="question.question_type === 'multiple_choice' || question.question_type === 'true_false'" class="space-y-3">
                                        <h5 class="font-medium">Opsi Jawaban</h5>
                                        <div v-for="(option, oIndex) in question.options" :key="option.id" class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-medium text-primary">
                                                {{ String.fromCharCode(65 + oIndex) }}
                                            </div>
                                            <Input
                                                v-model="option.option_text"
                                                placeholder="Teks opsi"
                                                class="h-11 flex-1"
                                            />
                                            <div class="flex items-center gap-2">
                                                <Label class="flex items-center gap-1 text-sm">
                                                    <input type="checkbox" v-model="option.is_correct" class="h-4 w-4" />
                                                    Benar
                                                </Label>
                                                <Button type="button" variant="ghost" size="icon" @click="removeOption(question, oIndex)" :disabled="question.options.length <= 2">
                                                    <X class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                        <Button type="button" variant="outline" size="sm" class="gap-2" @click="addOption(question)">
                                            <Plus class="h-4 w-4" />
                                            Tambah Opsi
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <Button
                                type="button"
                                class="w-full gap-2 mb-4"
                                @click="addQuickQuestion"
                                v-if="!showNewQuestionForm && questions.length > 0"
                            >
                                <Plus class="h-4 w-4" />
                                Tambah Pertanyaan
                            </Button>

                            <div class="rounded-lg border p-4" v-if="showNewQuestionForm">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="font-medium">Tambah Pertanyaan Baru</h4>
                                    <Button type="button" variant="ghost" size="icon" @click="toggleNewQuestionForm">
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>
                                <div class="space-y-3">
                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">Teks Pertanyaan</Label>
                                        <textarea
                                            v-model="newQuestion.question_text"
                                            rows="3"
                                            class="flex w-full rounded-lg border border-input bg-background px-4 py-3 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary disabled:cursor-not-allowed disabled:opacity-50"
                                            placeholder="Masukkan teks pertanyaan"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">Tipe Pertanyaan</Label>
                                        <select
                                            v-model="newQuestion.question_type"
                                            class="flex h-11 w-full rounded-lg border border-input bg-background px-4 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary"
                                        >
                                            <option value="multiple_choice">Pilihan Ganda</option>
                                            <option value="true_false">Benar/Salah</option>
                                            <option value="matching">Pencocokan</option>
                                            <option value="short_answer">Jawaban Singkat</option>
                                            <option value="essay">Esai</option>
                                            <option value="file_upload">Unggah Berkas</option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">Poin</Label>
                                        <Input v-model="newQuestion.points" type="number" min="1" class="h-11 w-24" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">Umpan Balik</Label>
                                        <textarea
                                            v-model="newQuestion.feedback"
                                            rows="2"
                                            class="flex w-full rounded-lg border border-input bg-background px-4 py-3 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary disabled:cursor-not-allowed disabled:opacity-50"
                                            placeholder="Umpan balik untuk pertanyaan ini"
                                        />
                                    </div>

                                    <div v-if="newQuestion.question_type === 'multiple_choice' || newQuestion.question_type === 'true_false'" class="space-y-3">
                                        <h5 class="font-medium">Opsi Jawaban</h5>
                                        <div v-for="(option, oIndex) in newQuestion.options" :key="oIndex" class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-medium text-primary">
                                                {{ String.fromCharCode(65 + oIndex) }}
                                            </div>
                                            <Input
                                                v-model="option.text"
                                                placeholder="Teks opsi"
                                                class="h-11 flex-1"
                                            />
                                            <div class="flex items-center gap-2">
                                                <Label class="flex items-center gap-1 text-sm">
                                                    <input type="checkbox" v-model="option.is_correct" class="h-4 w-4" />
                                                    Benar
                                                </Label>
                                                <Button type="button" variant="ghost" size="icon" @click="removeOption(newQuestion, oIndex)" :disabled="newQuestion.options.length <= 2">
                                                    <X class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                        <Button type="button" variant="outline" size="sm" class="gap-2" @click="addOption(newQuestion)">
                                            <Plus class="h-4 w-4" />
                                            Tambah Opsi
                                        </Button>
                                    </div>

                                    <Button type="button" class="gap-2" @click="addNewQuestion">
                                        <Plus class="h-4 w-4" />
                                        Tambah Pertanyaan
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </FormSection>
                </div>

                <div class="space-y-6">
                    <div class="space-y-4">
                        <!-- Display validation errors -->
                        <div v-if="errors.questions" class="rounded-lg border border-destructive bg-destructive/10 p-3 text-sm text-destructive">
                            {{ errors.questions }}
                        </div>
                        
                        <div class="sticky bottom-4 flex gap-3 rounded-xl border bg-card p-4 shadow-lg">
                            <!-- Hidden input to include questions data in form submission -->
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