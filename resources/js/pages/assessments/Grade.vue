<script setup lang="ts">
import AssessmentController from '@/actions/App/Http/Controllers/AssessmentController';
import PageHeader from '@/components/crud/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { Check, X, User, Clock, FileText, Award, AlertTriangle, CheckCircle } from 'lucide-vue-next';
import { ref } from 'vue';

interface Assessment {
    id: number;
    title: string;
    description: string;
    passing_score: number;
    questions: Question[];
}

interface Question {
    id: number;
    question_text: string;
    question_type: string;
    points: number;
    options: QuestionOption[];
}

interface QuestionOption {
    id: number;
    option_text: string;
    is_correct: boolean;
}

interface Attempt {
    id: number;
    attempt_number: number;
    status: string;
    score: number;
    max_score: number;
    percentage: number;
    passed: boolean;
    feedback: string;
    started_at: string;
    submitted_at: string;
    user: User;
    answers: Answer[];
}

interface Answer {
    id: number;
    question_id: number;
    answer_text: string;
    file_path: string;
    is_correct: boolean;
    score: number;
    feedback: string;
    question: Question;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface Course {
    id: number;
    title: string;
}

interface Props {
    course: Course;
    assessment: Assessment;
    attempt: Attempt;
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
        title: `Percobaan ${props.attempt.attempt_number}`,
        href: `/courses/${props.course.id}/assessments/${props.assessment.id}/attempts/${props.attempt.id}`,
    },
    {
        title: 'Penilaian',
        href: AssessmentController.grade().url,
    },
];

const form = ref({
    feedback: props.attempt.feedback || '',
    answers: props.attempt.answers.map(answer => ({
        id: answer.id,
        score: answer.score || 0,
        is_correct: answer.is_correct || false,
        feedback: answer.feedback || '',
    })),
});

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

const calculateTotalScore = () => {
    return form.value.answers.reduce((total, answer) => total + (answer.score || 0), 0);
};

const totalScore = ref(calculateTotalScore());
const maxScore = ref(props.assessment.questions.reduce((total, question) => total + question.points, 0));
const percentage = ref(maxScore.value > 0 ? Math.round((totalScore.value / maxScore.value) * 100) : 0);
const passed = ref(percentage.value >= props.assessment.passing_score);
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
                <div class="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Informasi Peserta</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center gap-3">
                                <User class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Nama</p>
                                    <p class="font-medium">{{ attempt.user.name }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <FileText class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Email</p>
                                    <p class="font-medium">{{ attempt.user.email }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <Clock class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Percobaan Ke-</p>
                                    <p class="font-medium">{{ attempt.attempt_number }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <Clock class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Diserahkan</p>
                                    <p class="font-medium">
                                        {{ new Date(attempt.submitted_at).toLocaleString() }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Form
                        v-bind="AssessmentController.submitGrade.form()"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <Card v-for="(answer, index) in attempt.answers" :key="answer.id">
                            <CardHeader>
                                <CardTitle class="flex items-center justify-between">
                                    <span>Pertanyaan {{ index + 1 }}</span>
                                    <span class="text-sm bg-primary/10 text-primary px-2 py-1 rounded-full">
                                        {{ getQuestionTypeLabel(answer.question.question_type) }}
                                    </span>
                                </CardTitle>
                                <CardDescription>
                                    {{ answer.question.question_text }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium">Jawaban Peserta</Label>
                                    <div v-if="answer.question.question_type === 'file_upload' && answer.file_path" class="mb-3">
                                        <a :href="answer.file_path" target="_blank" class="text-primary underline">
                                            Unduh Berkas Jawaban
                                        </a>
                                    </div>
                                    <div v-else class="rounded-lg border p-3 bg-muted/50">
                                        <p>{{ answer.answer_text || 'Tidak ada jawaban' }}</p>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label :for="`answers[${index}][score]`" class="text-sm font-medium">
                                            Poin (maks: {{ answer.question.points }})
                                        </Label>
                                        <Input
                                            :id="`answers[${index}][score]`"
                                            v-model="form.answers[index].score"
                                            :name="`answers[${index}][score]`"
                                            type="number"
                                            min="0"
                                            :max="answer.question.points"
                                            class="h-11"
                                            @input="() => {
                                                totalScore = calculateTotalScore();
                                                percentage = maxScore > 0 ? Math.round((totalScore / maxScore) * 100) : 0;
                                                passed = percentage >= assessment.passing_score;
                                            }"
                                        />
                                        <InputError :message="errors[`answers.${index}.score`]" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">Status</Label>
                                        <div class="flex items-center gap-4">
                                            <Label class="flex items-center gap-2 cursor-pointer">
                                                <input 
                                                    type="radio"
                                                    v-model="form.answers[index].is_correct"
                                                    :value="true"
                                                    :name="`answers[${index}][is_correct]`"
                                                    class="h-4 w-4"
                                                />
                                                <Check class="h-4 w-4 text-green-600" />
                                                Benar
                                            </Label>
                                            <Label class="flex items-center gap-2 cursor-pointer">
                                                <input 
                                                    type="radio"
                                                    v-model="form.answers[index].is_correct"
                                                    :value="false"
                                                    :name="`answers[${index}][is_correct]`"
                                                    class="h-4 w-4"
                                                />
                                                <X class="h-4 w-4 text-red-600" />
                                                Salah
                                            </Label>
                                        </div>
                                        <InputError :message="errors[`answers.${index}.is_correct`]" />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`answers[${index}][feedback]`" class="text-sm font-medium">
                                        Umpan Balik
                                    </Label>
                                    <Textarea
                                        :id="`answers[${index}][feedback]`"
                                        v-model="form.answers[index].feedback"
                                        :name="`answers[${index}][feedback]`"
                                        rows="3"
                                        placeholder="Berikan umpan balik untuk jawaban ini"
                                    />
                                    <InputError :message="errors[`answers.${index}.feedback`]" />
                                </div>
                            </CardContent>
                        </Card>

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

                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Ringkasan Penilaian</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center gap-3">
                                <Award class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Nilai Saat Ini</p>
                                    <p class="text-xl font-bold">
                                        {{ totalScore }} / {{ maxScore }} ({{ percentage }}%)
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <CheckCircle class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Status</p>
                                    <p class="font-medium" :class="{
                                        'text-green-600': passed,
                                        'text-red-600': !passed
                                    }">
                                        {{ passed ? 'Lulus' : 'Tidak Lulus' }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <AlertTriangle class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Nilai Kelulusan</p>
                                    <p class="font-medium">{{ assessment.passing_score }}%</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <FileText class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Total Pertanyaan</p>
                                    <p class="font-medium">{{ attempt.answers.length }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Petunjuk Penilaian</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-start gap-3">
                                <Check class="h-5 w-5 text-green-600 mt-0.5" />
                                <div>
                                    <p class="text-sm font-medium">Berikan umpan balik yang konstruktif</p>
                                    <p class="text-sm text-muted-foreground">
                                        Berikan umpan balik yang jelas dan bermanfaat untuk membantu peserta belajar.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <Award class="h-5 w-5 text-yellow-600 mt-0.5" />
                                <div>
                                    <p class="text-sm font-medium">Nilai dengan adil</p>
                                    <p class="text-sm text-muted-foreground">
                                        Gunakan kriteria penilaian yang konsisten untuk semua peserta.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <AlertTriangle class="h-5 w-5 text-blue-600 mt-0.5" />
                                <div>
                                    <p class="text-sm font-medium">Periksa dengan teliti</p>
                                    <p class="text-sm text-muted-foreground">
                                        Pastikan Anda telah menilai semua pertanyaan sebelum menyimpan.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Statistik</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center gap-3">
                                <Check class="h-5 w-5 text-green-600" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Jawaban Benar</p>
                                    <p class="text-xl font-bold text-green-600">
                                        {{ form.answers.filter(a => a.is_correct).length }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <X class="h-5 w-5 text-red-600" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Jawaban Salah</p>
                                    <p class="text-xl font-bold text-red-600">
                                        {{ form.answers.filter(a => a.is_correct === false).length }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <AlertTriangle class="h-5 w-5 text-yellow-600" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Belum Dinilai</p>
                                    <p class="text-xl font-bold text-yellow-600">
                                        {{ form.answers.filter(a => a.is_correct === null || a.is_correct === undefined).length }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>