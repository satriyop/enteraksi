<script setup lang="ts">
import AssessmentController from '@/actions/App/Http/Controllers/AssessmentController';
import PageHeader from '@/components/crud/PageHeader.vue';
import FormSection from '@/components/crud/FormSection.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { Plus, X, Clock, Target, ListChecks, Settings, Eye, EyeOff, Shuffle, GripVertical, Pencil, Trash2, ArrowUp, ArrowDown } from 'lucide-vue-next';
import { ref } from 'vue';

interface Assessment {
    id: number;
    title: string;
    description: string;
    instructions: string;
    time_limit_minutes: number;
    passing_score: number;
    max_attempts: number;
    shuffle_questions: boolean;
    show_correct_answers: boolean;
    allow_review: boolean;
    status: string;
    visibility: string;
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
    can: {
        publish: boolean;
        delete: boolean;
    };
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
        href: `/courses/${props.course.id}/assessments/${props.assessment.id}`,
    },
    {
        title: 'Edit',
        href: AssessmentController.edit().url,
    },
];

const form = ref({
    title: props.assessment.title,
    description: props.assessment.description,
    instructions: props.assessment.instructions,
    time_limit_minutes: props.assessment.time_limit_minutes,
    passing_score: props.assessment.passing_score,
    max_attempts: props.assessment.max_attempts,
    shuffle_questions: props.assessment.shuffle_questions,
    show_correct_answers: props.assessment.show_correct_answers,
    allow_review: props.assessment.allow_review,
    status: props.assessment.status,
    visibility: props.assessment.visibility,
});

const questions = ref([...props.assessment.questions]);
const newQuestion = ref({
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
    questions.value.push({
        id: 0,
        question_text: newQuestion.value.question_text,
        question_type: newQuestion.value.question_type,
        points: newQuestion.value.points,
        feedback: newQuestion.value.feedback,
        order: questions.value.length,
        options: newQuestion.value.options.map((opt, index) => ({
            id: 0,
            option_text: opt.text,
            is_correct: opt.is_correct,
            feedback: opt.feedback,
            order: index,
        })),
    });

    // Reset new question form
    newQuestion.value = {
        question_text: '',
        question_type: 'multiple_choice',
        points: 1,
        feedback: '',
        options: [
            { text: '', is_correct: false, feedback: '' },
            { text: '', is_correct: false, feedback: '' },
        ],
    };
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
        <Head :title="`Edit ${assessment.title}`" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                :title="`Edit ${assessment.title}`"
                description="Edit informasi dan pertanyaan penilaian"
                :back-href="`/courses/${course.id}/assessments/${assessment.id}`"
                back-label="Kembali ke Penilaian"
            />

            <Form
                v-bind="AssessmentController.update.form()"
                class="grid gap-6 lg:grid-cols-3"
                v-slot="{ errors, processing }"
            >
                <div class="space-y-6 lg:col-span-2">
                    <FormSection title="Informasi Dasar" description="Informasi utama tentang penilaian">
                        <div class="space-y-5">
                            <div class="space-y-2">
                                <Label for="title" class="text-sm font-medium">
                                    Judul Penilaian <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="title"
                                    name="title"
                                    v-model="form.title"
                                    class="h-11"
                                    required
                                />
                                <InputError :message="errors.title" />
                            </div>

                            <div class="space-y-2">
                                <Label for="description" class="text-sm font-medium">
                                    Deskripsi
                                </Label>
                                <textarea
                                    id="description"
                                    name="description"
                                    v-model="form.description"
                                    rows="3"
                                    class="flex w-full rounded-lg border border-input bg-background px-4 py-3 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <InputError :message="errors.description" />
                            </div>

                            <div class="space-y-2">
                                <Label for="instructions" class="text-sm font-medium">
                                    Instruksi
                                </Label>
                                <textarea
                                    id="instructions"
                                    name="instructions"
                                    v-model="form.instructions"
                                    rows="6"
                                    class="flex w-full rounded-lg border border-input bg-background px-4 py-3 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <InputError :message="errors.instructions" />
                            </div>
                        </div>
                    </FormSection>

                    <FormSection title="Pengaturan Penilaian" description="Konfigurasi pengaturan penilaian">
                        <div class="space-y-5">
                            <div class="space-y-2">
                                <Label for="passing_score" class="text-sm font-medium">
                                    Nilai Kelulusan <span class="text-destructive">*</span>
                                </Label>
                                <div class="flex items-center gap-2">
                                    <Input
                                        id="passing_score"
                                        name="passing_score"
                                        v-model="form.passing_score"
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="h-11 w-24"
                                        required
                                    />
                                    <span class="text-sm text-muted-foreground">%</span>
                                </div>
                                <InputError :message="errors.passing_score" />
                            </div>

                            <div class="space-y-2">
                                <Label for="max_attempts" class="text-sm font-medium">
                                    Jumlah Percobaan Maksimal <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="max_attempts"
                                    name="max_attempts"
                                    v-model="form.max_attempts"
                                    type="number"
                                    min="1"
                                    max="10"
                                    class="h-11 w-24"
                                    required
                                />
                                <InputError :message="errors.max_attempts" />
                            </div>

                            <div class="space-y-2">
                                <Label for="time_limit_minutes" class="text-sm font-medium">
                                    Batas Waktu (menit)
                                </Label>
                                <div class="flex items-center gap-2">
                                    <Input
                                        id="time_limit_minutes"
                                        name="time_limit_minutes"
                                        v-model="form.time_limit_minutes"
                                        type="number"
                                        min="1"
                                        max="360"
                                        placeholder="Kosongkan untuk tidak ada batas"
                                        class="h-11 w-24"
                                    />
                                    <Clock class="h-5 w-5 text-muted-foreground" />
                                </div>
                                <InputError :message="errors.time_limit_minutes" />
                            </div>
                        </div>
                    </FormSection>

                    <FormSection title="Opsi Tambahan" description="Pengaturan tambahan untuk penilaian">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between rounded-lg border p-4">
                                <div class="flex items-center gap-3">
                                    <Shuffle class="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <h4 class="font-medium">Acak Pertanyaan</h4>
                                        <p class="text-sm text-muted-foreground">
                                            Acak urutan pertanyaan untuk setiap peserta
                                        </p>
                                    </div>
                                </div>
                                <Switch
                                    id="shuffle_questions"
                                    name="shuffle_questions"
                                    v-model:checked="form.shuffle_questions"
                                />
                            </div>

                            <div class="flex items-center justify-between rounded-lg border p-4">
                                <div class="flex items-center gap-3">
                                    <ListChecks class="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <h4 class="font-medium">Tampilkan Jawaban Benar</h4>
                                        <p class="text-sm text-muted-foreground">
                                            Tampilkan jawaban yang benar setelah penilaian selesai
                                        </p>
                                    </div>
                                </div>
                                <Switch
                                    id="show_correct_answers"
                                    name="show_correct_answers"
                                    v-model:checked="form.show_correct_answers"
                                />
                            </div>

                            <div class="flex items-center justify-between rounded-lg border p-4">
                                <div class="flex items-center gap-3">
                                    <Eye class="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <h4 class="font-medium">Izinkan Review</h4>
                                        <p class="text-sm text-muted-foreground">
                                            Izinkan peserta untuk meninjau jawaban mereka setelah penilaian
                                        </p>
                                    </div>
                                </div>
                                <Switch
                                    id="allow_review"
                                    name="allow_review"
                                    v-model:checked="form.allow_review"
                                />
                            </div>
                        </div>
                    </FormSection>

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

                            <div class="rounded-lg border p-4">
                                <h4 class="font-medium mb-4">Tambah Pertanyaan Baru</h4>
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
                    <FormSection title="Status & Visibilitas">
                        <div class="space-y-5">
                            <div class="space-y-2">
                                <Label for="status" class="text-sm font-medium">
                                    Status <span class="text-destructive">*</span>
                                </Label>
                                <select
                                    id="status"
                                    name="status"
                                    v-model="form.status"
                                    class="flex h-11 w-full rounded-lg border border-input bg-background px-4 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary"
                                    required
                                >
                                    <option value="draft">Draft</option>
                                    <option value="published">Dipublikasikan</option>
                                    <option value="archived">Diarsipkan</option>
                                </select>
                                <InputError :message="errors.status" />
                            </div>

                            <div class="space-y-2">
                                <Label for="visibility" class="text-sm font-medium">
                                    Visibilitas <span class="text-destructive">*</span>
                                </Label>
                                <select
                                    id="visibility"
                                    name="visibility"
                                    v-model="form.visibility"
                                    class="flex h-11 w-full rounded-lg border border-input bg-background px-4 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary"
                                    required
                                >
                                    <option value="public">Publik - Dapat dilihat semua peserta</option>
                                    <option value="restricted">Terbatas - Hanya peserta tertentu</option>
                                    <option value="hidden">Tersembunyi - Tidak tampil di daftar</option>
                                </select>
                                <InputError :message="errors.visibility" />
                            </div>
                        </div>
                    </FormSection>

                    <div class="sticky bottom-4 flex gap-3 rounded-xl border bg-card p-4 shadow-lg">
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
            </Form>
        </div>
    </AppLayout>
</template>