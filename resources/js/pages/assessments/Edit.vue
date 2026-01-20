<script setup lang="ts">
// =============================================================================
// Assessment Edit Page
// Edit assessment information and settings
// =============================================================================

import AssessmentController from '@/actions/App/Http/Controllers/AssessmentController';
import AssessmentFormSidebar from '@/components/assessments/AssessmentFormSidebar.vue';
import AssessmentToggleOption from '@/components/assessments/AssessmentToggleOption.vue';
import PageHeader from '@/components/crud/PageHeader.vue';
import FormSection from '@/components/crud/FormSection.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type AssessmentStatus, type AssessmentVisibility } from '@/types';
import { Form, Head } from '@inertiajs/vue3';
import { Clock, Shuffle, ListChecks, Eye } from 'lucide-vue-next';
import { ref } from 'vue';

// =============================================================================
// Page-Specific Types
// =============================================================================

interface EditableAssessment {
    id: number;
    title: string;
    description: string | null;
    instructions: string | null;
    time_limit_minutes: number | null;
    passing_score: number;
    max_attempts: number;
    shuffle_questions: boolean;
    show_correct_answers: boolean;
    allow_review: boolean;
    status: AssessmentStatus;
    visibility: AssessmentVisibility;
}

interface AssessmentCourse {
    id: number;
    title: string;
}

interface Props {
    course: AssessmentCourse;
    assessment: EditableAssessment;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Kursus', href: `/courses/${props.course.id}` },
    { title: 'Penilaian', href: `/courses/${props.course.id}/assessments` },
    { title: props.assessment.title, href: `/courses/${props.course.id}/assessments/${props.assessment.id}` },
    { title: 'Edit', href: AssessmentController.edit({ course: props.course.id, assessment: props.assessment.id }).url },
];

// =============================================================================
// Form State
// =============================================================================

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
            <FlashMessages />

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
                                <Input id="title" name="title" v-model="form.title" class="h-11" required />
                                <InputError :message="errors.title" />
                            </div>

                            <div class="space-y-2">
                                <Label for="description" class="text-sm font-medium">Deskripsi</Label>
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
                                <Label for="instructions" class="text-sm font-medium">Instruksi</Label>
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
                            <AssessmentToggleOption
                                id="shuffle_questions"
                                name="shuffle_questions"
                                :icon="Shuffle"
                                title="Acak Pertanyaan"
                                description="Acak urutan pertanyaan untuk setiap peserta"
                                v-model="form.shuffle_questions"
                            />

                            <AssessmentToggleOption
                                id="show_correct_answers"
                                name="show_correct_answers"
                                :icon="ListChecks"
                                title="Tampilkan Jawaban Benar"
                                description="Tampilkan jawaban yang benar setelah penilaian selesai"
                                v-model="form.show_correct_answers"
                            />

                            <AssessmentToggleOption
                                id="allow_review"
                                name="allow_review"
                                :icon="Eye"
                                title="Izinkan Review"
                                description="Izinkan peserta untuk meninjau jawaban mereka setelah penilaian"
                                v-model="form.allow_review"
                            />
                        </div>
                    </FormSection>
                </div>

                <AssessmentFormSidebar
                    v-model:status="form.status"
                    v-model:visibility="form.visibility"
                    :cancel-href="`/courses/${course.id}/assessments/${assessment.id}`"
                    :processing="processing"
                    :errors="{ status: errors.status, visibility: errors.visibility }"
                    submit-label="Simpan Perubahan"
                    processing-label="Menyimpan..."
                />
            </Form>
        </div>
    </AppLayout>
</template>
