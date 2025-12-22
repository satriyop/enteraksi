<script setup lang="ts">
import AssessmentController from '@/actions/App/Http/Controllers/AssessmentController';
import PageHeader from '@/components/crud/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Clock, CheckCircle, FileText, Users, Eye, Pencil, Trash2, Archive, PlayCircle, Check, X, AlertTriangle } from 'lucide-vue-next';

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
    questions_count: number;
    attempts_count: number;
    created_at: string;
    updated_at: string;
}

interface Course {
    id: number;
    title: string;
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
}

interface Props {
    course: Course;
    assessment: Assessment;
    canAttempt: boolean;
    latestAttempt: Attempt | null;
    can: {
        update: boolean;
        delete: boolean;
        publish: boolean;
        attempt: boolean;
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
        href: AssessmentController.show().url,
    },
];

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'published':
            return { text: 'Dipublikasikan', class: 'bg-green-100 text-green-800' };
        case 'draft':
            return { text: 'Draft', class: 'bg-yellow-100 text-yellow-800' };
        case 'archived':
            return { text: 'Diarsipkan', class: 'bg-gray-100 text-gray-800' };
        default:
            return { text: status, class: 'bg-gray-100 text-gray-800' };
    }
};

const getVisibilityBadge = (visibility: string) => {
    switch (visibility) {
        case 'public':
            return { text: 'Publik', class: 'bg-blue-100 text-blue-800' };
        case 'restricted':
            return { text: 'Terbatas', class: 'bg-purple-100 text-purple-800' };
        case 'hidden':
            return { text: 'Tersembunyi', class: 'bg-gray-100 text-gray-800' };
        default:
            return { text: visibility, class: 'bg-gray-100 text-gray-800' };
    }
};

const getAttemptStatusBadge = (status: string) => {
    switch (status) {
        case 'in_progress':
            return { text: 'Sedang Berlangsung', class: 'bg-blue-100 text-blue-800' };
        case 'submitted':
            return { text: 'Diserahkan', class: 'bg-yellow-100 text-yellow-800' };
        case 'graded':
            return { text: 'Dinilai', class: 'bg-green-100 text-green-800' };
        case 'completed':
            return { text: 'Selesai', class: 'bg-purple-100 text-purple-800' };
        default:
            return { text: status, class: 'bg-gray-100 text-gray-800' };
    }
};
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
                <div class="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Informasi Penilaian</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p class="text-sm text-muted-foreground mb-1">Status</p>
                                    <span :class="`text-sm font-medium px-3 py-1 rounded-full ${getStatusBadge(assessment.status).class}`">
                                        {{ getStatusBadge(assessment.status).text }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground mb-1">Visibilitas</p>
                                    <span :class="`text-sm font-medium px-3 py-1 rounded-full ${getVisibilityBadge(assessment.visibility).class}`">
                                        {{ getVisibilityBadge(assessment.visibility).text }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="assessment.description" class="space-y-2">
                                <p class="text-sm text-muted-foreground">Deskripsi</p>
                                <p>{{ assessment.description }}</p>
                            </div>

                            <div v-if="assessment.instructions" class="space-y-2">
                                <p class="text-sm text-muted-foreground">Instruksi</p>
                                <div class="rounded-lg border p-3 bg-muted/50">
                                    <p class="whitespace-pre-wrap">{{ assessment.instructions }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Pengaturan Penilaian</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="flex items-center gap-3">
                                    <CheckCircle class="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <p class="text-sm text-muted-foreground">Nilai Kelulusan</p>
                                        <p class="font-medium">{{ assessment.passing_score }}%</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <Users class="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <p class="text-sm text-muted-foreground">Jumlah Percobaan</p>
                                        <p class="font-medium">{{ assessment.max_attempts }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <Clock class="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <p class="text-sm text-muted-foreground">Batas Waktu</p>
                                        <p class="font-medium">
                                            {{ assessment.time_limit_minutes ? `${assessment.time_limit_minutes} menit` : 'Tidak ada batas waktu' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <FileText class="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <p class="text-sm text-muted-foreground">Jumlah Pertanyaan</p>
                                        <p class="font-medium">{{ assessment.questions_count }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-6 w-6 items-center justify-center rounded-full border">
                                        <Check v-if="assessment.shuffle_questions" class="h-4 w-4 text-green-600" />
                                        <X v-else class="h-4 w-4 text-red-600" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium">Acak Pertanyaan</p>
                                        <p class="text-sm text-muted-foreground">
                                            {{ assessment.shuffle_questions ? 'Aktif' : 'Nonaktif' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="flex h-6 w-6 items-center justify-center rounded-full border">
                                        <Check v-if="assessment.show_correct_answers" class="h-4 w-4 text-green-600" />
                                        <X v-else class="h-4 w-4 text-red-600" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium">Tampilkan Jawaban Benar</p>
                                        <p class="text-sm text-muted-foreground">
                                            {{ assessment.show_correct_answers ? 'Aktif' : 'Nonaktif' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="flex h-6 w-6 items-center justify-center rounded-full border">
                                        <Check v-if="assessment.allow_review" class="h-4 w-4 text-green-600" />
                                        <X v-else class="h-4 w-4 text-red-600" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium">Izinkan Review</p>
                                        <p class="text-sm text-muted-foreground">
                                            {{ assessment.allow_review ? 'Aktif' : 'Nonaktif' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="latestAttempt">
                        <CardHeader>
                            <CardTitle>Percobaan Terakhir</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p class="text-sm text-muted-foreground mb-1">Status</p>
                                    <span :class="`text-sm font-medium px-3 py-1 rounded-full ${getAttemptStatusBadge(latestAttempt.status).class}`">
                                        {{ getAttemptStatusBadge(latestAttempt.status).text }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground mb-1">Percobaan Ke-</p>
                                    <p class="font-medium">{{ latestAttempt.attempt_number }}</p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p class="text-sm text-muted-foreground mb-1">Nilai</p>
                                    <p class="text-2xl font-bold">
                                        {{ latestAttempt.score !== null ? latestAttempt.score : '-' }} / {{ latestAttempt.max_score }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground mb-1">Persentase</p>
                                    <p class="text-2xl font-bold">
                                        {{ latestAttempt.percentage !== null ? `${latestAttempt.percentage}%` : '-' }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full" :class="{
                                    'bg-green-100': latestAttempt.passed,
                                    'bg-red-100': !latestAttempt.passed
                                }">
                                    <Check v-if="latestAttempt.passed" class="h-5 w-5 text-green-600" />
                                    <X v-else class="h-5 w-5 text-red-600" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium">Status Kelulusan</p>
                                    <p class="font-medium" :class="{
                                        'text-green-600': latestAttempt.passed,
                                        'text-red-600': !latestAttempt.passed
                                    }">
                                        {{ latestAttempt.passed ? 'Lulus' : 'Tidak Lulus' }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p class="text-sm text-muted-foreground mb-1">Dimulai</p>
                                    <p class="font-medium">
                                        {{ latestAttempt.started_at ? new Date(latestAttempt.started_at).toLocaleString() : '-' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground mb-1">Diserahkan</p>
                                    <p class="font-medium">
                                        {{ latestAttempt.submitted_at ? new Date(latestAttempt.submitted_at).toLocaleString() : '-' }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="latestAttempt.graded_at">
                                <p class="text-sm text-muted-foreground mb-1">Dinilai</p>
                                <p class="font-medium">
                                    {{ new Date(latestAttempt.graded_at).toLocaleString() }}
                                </p>
                            </div>
                        </CardContent>
                        <CardFooter>
                            <Link :href="`/courses/${course.id}/assessments/${assessment.id}/attempts/${latestAttempt.id}`" class="w-full">
                                <Button class="w-full gap-2">
                                    <Eye class="h-4 w-4" />
                                    Lihat Detail Percobaan
                                </Button>
                            </Link>
                        </CardFooter>
                    </Card>
                </div>

                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Aksi Cepat</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <Link v-if="can.attempt" :href="`/courses/${course.id}/assessments/${assessment.id}/start`" class="w-full block">
                                <Button class="w-full gap-2 bg-green-600 hover:bg-green-700">
                                    <PlayCircle class="h-4 w-4" />
                                    Mulai Penilaian
                                </Button>
                            </Link>

                            <Link v-if="can.update" :href="`/courses/${course.id}/assessments/${assessment.id}/edit`" class="w-full block">
                                <Button class="w-full gap-2">
                                    <Pencil class="h-4 w-4" />
                                    Edit Penilaian
                                </Button>
                            </Link>

                            <Button v-if="can.publish && assessment.status === 'draft'" 
                                    type="button" 
                                    class="w-full gap-2 bg-blue-600 hover:bg-blue-700"
                                    @click="() => {
                                        if (confirm('Apakah Anda yakin ingin mempublikasikan penilaian ini?')) {
                                            $inertia.post(`/courses/${course.id}/assessments/${assessment.id}/publish`);
                                        }
                                    }">
                                <Archive class="h-4 w-4" />
                                Publikasikan
                            </Button>

                            <Button v-if="can.publish && assessment.status === 'published'" 
                                    type="button" 
                                    class="w-full gap-2 bg-yellow-600 hover:bg-yellow-700"
                                    @click="() => {
                                        if (confirm('Apakah Anda yakin ingin membatalkan publikasi penilaian ini?')) {
                                            $inertia.post(`/courses/${course.id}/assessments/${assessment.id}/unpublish`);
                                        }
                                    }">
                                <Archive class="h-4 w-4" />
                                Batalkan Publikasi
                            </Button>

                            <Button v-if="can.publish && assessment.status !== 'archived'" 
                                    type="button" 
                                    variant="destructive" 
                                    class="w-full gap-2"
                                    @click="() => {
                                        if (confirm('Apakah Anda yakin ingin mengarsipkan penilaian ini?')) {
                                            $inertia.post(`/courses/${course.id}/assessments/${assessment.id}/archive`);
                                        }
                                    }">
                                <Archive class="h-4 w-4" />
                                Arsipkan
                            </Button>

                            <Button v-if="can.delete" 
                                    type="button" 
                                    variant="destructive" 
                                    class="w-full gap-2"
                                    @click="() => {
                                        if (confirm('Apakah Anda yakin ingin menghapus penilaian ini? Tindakan ini tidak dapat dibatalkan.')) {
                                            $inertia.delete(`/courses/${course.id}/assessments/${assessment.id}`);
                                        }
                                    }">
                                <Trash2 class="h-4 w-4" />
                                Hapus Penilaian
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Statistik</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center gap-3">
                                <Users class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Total Percobaan</p>
                                    <p class="text-xl font-bold">{{ assessment.attempts_count }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <FileText class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Total Pertanyaan</p>
                                    <p class="text-xl font-bold">{{ assessment.questions_count }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <AlertTriangle class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Tingkat Kesulitan</p>
                                    <p class="text-xl font-bold">Menengah</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>