<script setup lang="ts">
import AssessmentController from '@/actions/App/Http/Controllers/AssessmentController';
import PageHeader from '@/components/crud/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Clock, CheckCircle, FileText, Users, Eye, Pencil, Trash2, Archive, PlayCircle, Check, X, AlertTriangle, Trophy, Award, BarChart2 } from 'lucide-vue-next';

interface Assessment {
    id: number;
    title: string;
    description: string;
    passing_score: number;
    max_attempts: number;
}

interface Attempt {
    id: number;
    attempt_number: number;
    status: string;
    score: number;
    max_score: number;
    percentage: number;
    passed: boolean;
    started_at: string;
    submitted_at: string;
    graded_at: string;
    feedback: string;
    answers: Answer[];
}

interface Answer {
    id: number;
    question_id: number;
    answer_text: string;
    is_correct: boolean;
    score: number;
    feedback: string;
    question: Question;
}

interface Question {
    id: number;
    question_text: string;
    question_type: string;
    points: number;
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
        href: AssessmentController.attempt().url,
    },
    {
        title: 'Selesai',
        href: AssessmentController.attemptComplete().url,
    },
];

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
                <div class="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Hasil Penilaian</CardTitle>
                            <CardDescription>
                                Percobaan {{ attempt.attempt_number }} - {{ assessment.title }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="flex flex-col items-center justify-center gap-4 py-8">
                                <div v-if="attempt.passed" class="flex h-20 w-20 items-center justify-center rounded-full bg-green-100">
                                    <Trophy class="h-12 w-12 text-green-600" />
                                </div>
                                <div v-else class="flex h-20 w-20 items-center justify-center rounded-full bg-red-100">
                                    <AlertTriangle class="h-12 w-12 text-red-600" />
                                </div>

                                <h2 v-if="attempt.passed" class="text-2xl font-bold text-green-600">
                                    Selamat! Anda Lulus!
                                </h2>
                                <h2 v-else class="text-2xl font-bold text-red-600">
                                    Anda Belum Lulus
                                </h2>

                                <p class="text-muted-foreground">
                                    {{ attempt.passed ? 'Anda telah berhasil menyelesaikan penilaian ini.' : 'Anda dapat mencoba lagi untuk meningkatkan nilai Anda.' }}
                                </p>
                            </div>

                            <div class="grid gap-6 md:grid-cols-2">
                                <div class="text-center">
                                    <p class="text-sm text-muted-foreground mb-1">Nilai Anda</p>
                                    <p class="text-3xl font-bold">
                                        {{ attempt.score }} / {{ attempt.max_score }}
                                    </p>
                                </div>
                                <div class="text-center">
                                    <p class="text-sm text-muted-foreground mb-1">Persentase</p>
                                    <p class="text-3xl font-bold">
                                        {{ attempt.percentage }}%
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-6 md:grid-cols-2">
                                <div class="text-center">
                                    <p class="text-sm text-muted-foreground mb-1">Jawaban Benar</p>
                                    <p class="text-2xl font-bold text-green-600">
                                        {{ correctAnswers }} / {{ totalQuestions }}
                                    </p>
                                </div>
                                <div class="text-center">
                                    <p class="text-sm text-muted-foreground mb-1">Nilai Kelulusan</p>
                                    <p class="text-2xl font-bold">
                                        {{ assessment.passing_score }}%
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <p class="text-sm text-muted-foreground">Waktu Penyelesaian</p>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="flex items-center gap-3">
                                        <Clock class="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <p class="text-sm text-muted-foreground">Dimulai</p>
                                            <p class="font-medium">
                                                {{ new Date(attempt.started_at).toLocaleString() }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <CheckCircle class="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <p class="text-sm text-muted-foreground">Diserahkan</p>
                                            <p class="font-medium">
                                                {{ new Date(attempt.submitted_at).toLocaleString() }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="attempt.feedback" class="space-y-2">
                                <p class="text-sm text-muted-foreground">Umpan Balik</p>
                                <div class="rounded-lg border p-3 bg-muted/50">
                                    <p class="whitespace-pre-wrap">{{ attempt.feedback }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Detail Jawaban</CardTitle>
                            <CardDescription>
                                Review jawaban Anda untuk setiap pertanyaan
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div v-for="(answer, index) in attempt.answers" :key="answer.id" class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-medium">Pertanyaan {{ index + 1 }}</h4>
                                    <span class="text-sm bg-primary/10 text-primary px-2 py-1 rounded-full">
                                        {{ getQuestionTypeLabel(answer.question.question_type) }}
                                    </span>
                                </div>

                                <p class="text-sm text-muted-foreground mb-2">Pertanyaan:</p>
                                <p class="mb-3">{{ answer.question.question_text }}</p>

                                <p class="text-sm text-muted-foreground mb-2">Jawaban Anda:</p>
                                <div v-if="answer.question.question_type === 'file_upload' && answer.answer_text" class="mb-3">
                                    <a :href="answer.answer_text" target="_blank" class="text-primary underline">
                                        Unduh Berkas Jawaban
                                    </a>
                                </div>
                                <div v-else class="mb-3">
                                    <p>{{ answer.answer_text || 'Tidak ada jawaban' }}</p>
                                </div>

                                <div class="flex items-center gap-3 mb-3">
                                    <div class="flex h-6 w-6 items-center justify-center rounded-full" :class="{
                                        'bg-green-100': answer.is_correct,
                                        'bg-red-100': !answer.is_correct && answer.is_correct !== null,
                                        'bg-gray-100': answer.is_correct === null
                                    }">
                                        <Check v-if="answer.is_correct" class="h-4 w-4 text-green-600" />
                                        <X v-else-if="answer.is_correct === false" class="h-4 w-4 text-red-600" />
                                        <AlertTriangle v-else class="h-4 w-4 text-gray-600" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium">Status</p>
                                        <p class="text-sm" :class="{
                                            'text-green-600': answer.is_correct,
                                            'text-red-600': answer.is_correct === false,
                                            'text-gray-600': answer.is_correct === null
                                        }">
                                            {{ answer.is_correct ? 'Benar' : answer.is_correct === false ? 'Salah' : 'Menunggu Penilaian' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <Award class="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <p class="text-sm text-muted-foreground">Poin</p>
                                        <p class="font-medium">
                                            {{ answer.score !== null ? `${answer.score} / ${answer.question.points}` : 'Menunggu Penilaian' }}
                                        </p>
                                    </div>
                                </div>

                                <div v-if="answer.feedback" class="mt-3 rounded-lg border p-3 bg-muted/50">
                                    <p class="text-sm text-muted-foreground mb-1">Umpan Balik:</p>
                                    <p class="whitespace-pre-wrap">{{ answer.feedback }}</p>
                                </div>

                                <hr v-if="index < attempt.answers.length - 1" class="my-4" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Ringkasan</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center gap-3">
                                <BarChart2 class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Nilai Akhir</p>
                                    <p class="text-xl font-bold">
                                        {{ attempt.score }} / {{ attempt.max_score }} ({{ attempt.percentage }}%)
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <CheckCircle class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Status</p>
                                    <p class="font-medium" :class="{
                                        'text-green-600': attempt.passed,
                                        'text-red-600': !attempt.passed
                                    }">
                                        {{ attempt.passed ? 'Lulus' : 'Tidak Lulus' }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <Users class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Percobaan Ke-</p>
                                    <p class="font-medium">{{ attempt.attempt_number }} / {{ assessment.max_attempts }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <Clock class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Waktu Penyelesaian</p>
                                    <p class="font-medium">
                                        {{ Math.floor((new Date(attempt.submitted_at).getTime() - new Date(attempt.started_at).getTime()) / 60000) }} menit
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                        <CardFooter class="flex flex-col gap-2">
                            <Link :href="`/courses/${course.id}/assessments/${assessment.id}`" class="w-full">
                                <Button class="w-full gap-2" variant="outline">
                                    <Eye class="h-4 w-4" />
                                    Kembali ke Penilaian
                                </Button>
                            </Link>

                            <Link v-if="!attempt.passed && attempt.attempt_number < assessment.max_attempts" 
                                  :href="`/courses/${course.id}/assessments/${assessment.id}/start`" class="w-full">
                                <Button class="w-full gap-2 bg-blue-600 hover:bg-blue-700">
                                    <PlayCircle class="h-4 w-4" />
                                    Coba Lagi
                                </Button>
                            </Link>
                        </CardFooter>
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
                                        {{ correctAnswers }} / {{ totalQuestions }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <X class="h-5 w-5 text-red-600" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Jawaban Salah</p>
                                    <p class="text-xl font-bold text-red-600">
                                        {{ totalQuestions - correctAnswers }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <AlertTriangle class="h-5 w-5 text-yellow-600" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Menunggu Penilaian</p>
                                    <p class="text-xl font-bold text-yellow-600">
                                        {{ attempt.answers.filter(a => a.is_correct === null).length }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="attempt.passed">
                        <CardHeader>
                            <CardTitle>Sertifikat</CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-col items-center justify-center gap-4 py-8">
                            <Award class="h-16 w-16 text-yellow-600" />
                            <p class="text-center text-muted-foreground">
                                Anda telah berhasil menyelesaikan penilaian ini dan memenuhi syarat untuk sertifikat.
                            </p>
                            <Button class="gap-2" variant="outline">
                                <Trophy class="h-4 w-4" />
                                Dapatkan Sertifikat
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>