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
import { ref, computed, watch } from 'vue';

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
        href: AssessmentController.edit({ course: props.course.id, assessment: props.assessment.id }).url,
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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Edit ${assessment.title}`" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Form submission feedback -->
            <div v-if="$page.props.flash.success" class="rounded-md bg-green-50 p-4">
                <div class="flex">
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">{{ $page.props.flash.success }}</p>
                    </div>
                </div>
            </div>
            <div v-if="$page.props.flash.error" class="rounded-md bg-red-50 p-4">
                <div class="flex">
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-800">{{ $page.props.flash.error }}</p>
                    </div>
                </div>
            </div>
            
            <PageHeader
                :title="`Edit ${assessment.title}`"
                description="Edit informasi dan pertanyaan penilaian"
                :back-href="`/courses/${course.id}/assessments/${assessment.id}`"
                back-label="Kembali ke Penilaian"
            />

            <Form
                v-bind="AssessmentController.update.form([props.course, props.assessment])"
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
                                    v-model="form.shuffle_questions"
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
                                    v-model="form.show_correct_answers"
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
                                    v-model="form.allow_review"
                                />
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