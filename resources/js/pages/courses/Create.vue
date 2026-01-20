<script setup lang="ts">
// =============================================================================
// Course Create Page
// Create a new course with basic information and settings
// =============================================================================

import CourseController from '@/actions/App/Http/Controllers/CourseController';
import { index } from '@/actions/App/Http/Controllers/CourseController';
import CourseFormActions from '@/components/courses/CourseFormActions.vue';
import CourseSettingsSidebar from '@/components/courses/CourseSettingsSidebar.vue';
import CourseTagsField from '@/components/courses/CourseTagsField.vue';
import CourseThumbnailUpload from '@/components/courses/CourseThumbnailUpload.vue';
import PageHeader from '@/components/crud/PageHeader.vue';
import FormSection from '@/components/crud/FormSection.vue';
import DynamicListField from '@/components/form/DynamicListField.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Category, type Tag } from '@/types';
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';

// =============================================================================
// Page-Specific Types
// =============================================================================

interface Props {
    categories: Category[];
    tags: Tag[];
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Kursus', href: index().url },
    { title: 'Buat Kursus', href: CourseController.create().url },
];

// =============================================================================
// Form State
// =============================================================================

const objectives = ref<string[]>(['']);
const prerequisites = ref<string[]>(['']);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Buat Kursus Baru" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Buat Kursus Baru"
                description="Isi informasi dasar untuk membuat kursus pembelajaran baru"
                :back-href="index().url"
                back-label="Kembali ke Daftar Kursus"
            />

            <Form
                v-bind="CourseController.store.form()"
                class="grid gap-6 lg:grid-cols-3"
                v-slot="{ errors, processing }"
                enctype="multipart/form-data"
            >
                <div class="space-y-6 lg:col-span-2">
                    <FormSection title="Informasi Dasar" description="Informasi utama tentang kursus Anda">
                        <div class="space-y-5">
                            <div class="space-y-2">
                                <Label for="title" class="text-sm font-medium">
                                    Judul Kursus <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="title"
                                    name="title"
                                    placeholder="Contoh: Belajar JavaScript dari Dasar"
                                    class="h-11"
                                    required
                                />
                                <InputError :message="errors.title" />
                            </div>

                            <div class="space-y-2">
                                <Label for="short_description" class="text-sm font-medium">
                                    Deskripsi Singkat <span class="text-destructive">*</span>
                                </Label>
                                <textarea
                                    id="short_description"
                                    name="short_description"
                                    rows="3"
                                    class="flex w-full rounded-lg border border-input bg-background px-4 py-3 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary disabled:cursor-not-allowed disabled:opacity-50"
                                    placeholder="Ringkasan singkat yang menarik tentang kursus ini (maksimal 200 karakter)"
                                    required
                                />
                                <InputError :message="errors.short_description" />
                            </div>

                            <div class="space-y-2">
                                <Label for="long_description" class="text-sm font-medium">
                                    Deskripsi Lengkap
                                </Label>
                                <textarea
                                    id="long_description"
                                    name="long_description"
                                    rows="6"
                                    class="flex w-full rounded-lg border border-input bg-background px-4 py-3 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary disabled:cursor-not-allowed disabled:opacity-50"
                                    placeholder="Jelaskan secara detail apa yang akan dipelajari peserta"
                                />
                                <InputError :message="errors.long_description" />
                            </div>
                        </div>
                    </FormSection>

                    <FormSection title="Tujuan Pembelajaran" description="Apa yang akan dicapai peserta setelah mengikuti kursus">
                        <DynamicListField
                            v-model="objectives"
                            label=""
                            input-name="objectives"
                            placeholder="Contoh: Memahami konsep dasar pemrograman"
                            add-button-text="Tambah Tujuan"
                            indicator-variant="primary"
                            :error="errors.objectives"
                        />
                    </FormSection>

                    <FormSection title="Prasyarat" description="Pengetahuan atau keterampilan yang diperlukan sebelum mengikuti kursus">
                        <DynamicListField
                            v-model="prerequisites"
                            label=""
                            input-name="prerequisites"
                            placeholder="Contoh: Pemahaman dasar HTML dan CSS"
                            add-button-text="Tambah Prasyarat"
                            indicator-variant="muted"
                            :error="errors.prerequisites"
                        />
                    </FormSection>
                </div>

                <div class="space-y-6">
                    <CourseThumbnailUpload :error="errors.thumbnail" />

                    <CourseSettingsSidebar :categories="categories" :errors="errors" />

                    <CourseTagsField :tags="tags" :error="errors.tag_ids" />

                    <CourseFormActions
                        :cancel-href="index().url"
                        submit-text="Simpan Kursus"
                        processing-text="Menyimpan..."
                        :processing="processing"
                    />
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
