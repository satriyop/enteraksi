<script setup lang="ts">
import CourseController from '@/actions/App/Http/Controllers/CourseController';
import { index } from '@/actions/App/Http/Controllers/CourseController';
import PageHeader from '@/components/crud/PageHeader.vue';
import FormSection from '@/components/crud/FormSection.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { Plus, X, GripVertical, Target, ListChecks, Settings, Tag, ImagePlus } from 'lucide-vue-next';
import { ref } from 'vue';

interface Category {
    id: number;
    name: string;
}

interface Tag {
    id: number;
    name: string;
}

interface Props {
    categories: Category[];
    tags: Tag[];
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Kursus',
        href: index().url,
    },
    {
        title: 'Buat Kursus',
        href: CourseController.create().url,
    },
];

const objectives = ref<string[]>(['']);
const prerequisites = ref<string[]>(['']);
const thumbnailPreview = ref<string | null>(null);

const addObjective = () => {
    objectives.value.push('');
};

const removeObjective = (idx: number) => {
    if (objectives.value.length > 1) {
        objectives.value.splice(idx, 1);
    }
};

const addPrerequisite = () => {
    prerequisites.value.push('');
};

const removePrerequisite = (idx: number) => {
    if (prerequisites.value.length > 1) {
        prerequisites.value.splice(idx, 1);
    }
};

const handleThumbnailChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        thumbnailPreview.value = URL.createObjectURL(file);
    }
};
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
                                    placeholder="Jelaskan secara detail apa yang akan dipelajari peserta, metodologi pengajaran, dan hasil yang diharapkan"
                                />
                                <InputError :message="errors.long_description" />
                            </div>
                        </div>
                    </FormSection>

                    <FormSection title="Tujuan Pembelajaran" description="Apa yang akan dicapai peserta setelah mengikuti kursus">
                        <div class="space-y-3">
                            <div
                                v-for="(_, idx) in objectives"
                                :key="idx"
                                class="flex items-center gap-3"
                            >
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-medium text-primary">
                                    {{ idx + 1 }}
                                </div>
                                <Input
                                    v-model="objectives[idx]"
                                    :name="`objectives[${idx}]`"
                                    :placeholder="`Contoh: Memahami konsep dasar ${idx === 0 ? 'pemrograman' : '...'}`"
                                    class="h-11 flex-1"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="h-10 w-10 shrink-0 text-muted-foreground hover:text-destructive"
                                    @click="removeObjective(idx)"
                                    :disabled="objectives.length === 1"
                                >
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>
                            <Button type="button" variant="outline" size="sm" class="gap-2" @click="addObjective">
                                <Plus class="h-4 w-4" />
                                Tambah Tujuan
                            </Button>
                            <InputError :message="errors.objectives" />
                        </div>
                    </FormSection>

                    <FormSection title="Prasyarat" description="Pengetahuan atau keterampilan yang diperlukan sebelum mengikuti kursus">
                        <div class="space-y-3">
                            <div
                                v-for="(_, idx) in prerequisites"
                                :key="idx"
                                class="flex items-center gap-3"
                            >
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-muted text-sm font-medium text-muted-foreground">
                                    {{ idx + 1 }}
                                </div>
                                <Input
                                    v-model="prerequisites[idx]"
                                    :name="`prerequisites[${idx}]`"
                                    :placeholder="`Contoh: ${idx === 0 ? 'Pemahaman dasar HTML dan CSS' : '...'}`"
                                    class="h-11 flex-1"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="h-10 w-10 shrink-0 text-muted-foreground hover:text-destructive"
                                    @click="removePrerequisite(idx)"
                                    :disabled="prerequisites.length === 1"
                                >
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>
                            <Button type="button" variant="outline" size="sm" class="gap-2" @click="addPrerequisite">
                                <Plus class="h-4 w-4" />
                                Tambah Prasyarat
                            </Button>
                            <InputError :message="errors.prerequisites" />
                        </div>
                    </FormSection>
                </div>

                <div class="space-y-6">
                    <FormSection title="Thumbnail">
                        <div class="space-y-4">
                            <div
                                class="relative aspect-video w-full overflow-hidden rounded-lg border-2 border-dashed bg-muted/30 transition-colors hover:border-primary/50"
                            >
                                <img
                                    v-if="thumbnailPreview"
                                    :src="thumbnailPreview"
                                    alt="Thumbnail preview"
                                    class="h-full w-full object-cover"
                                />
                                <label
                                    v-else
                                    class="flex h-full w-full cursor-pointer flex-col items-center justify-center gap-2 text-muted-foreground"
                                >
                                    <ImagePlus class="h-10 w-10" />
                                    <span class="text-sm font-medium">Klik untuk upload gambar</span>
                                    <span class="text-xs">PNG, JPG hingga 2MB</span>
                                    <input
                                        type="file"
                                        name="thumbnail"
                                        accept="image/*"
                                        class="hidden"
                                        @change="handleThumbnailChange"
                                    />
                                </label>
                            </div>
                            <InputError :message="errors.thumbnail" />
                        </div>
                    </FormSection>

                    <FormSection title="Pengaturan">
                        <div class="space-y-5">
                            <div class="space-y-2">
                                <Label for="category_id" class="text-sm font-medium">Kategori</Label>
                                <select
                                    id="category_id"
                                    name="category_id"
                                    class="flex h-11 w-full rounded-lg border border-input bg-background px-4 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary"
                                >
                                    <option value="">Pilih Kategori</option>
                                    <option v-for="category in categories" :key="category.id" :value="category.id">
                                        {{ category.name }}
                                    </option>
                                </select>
                                <InputError :message="errors.category_id" />
                            </div>

                            <div class="space-y-2">
                                <Label for="difficulty_level" class="text-sm font-medium">
                                    Tingkat Kesulitan <span class="text-destructive">*</span>
                                </Label>
                                <select
                                    id="difficulty_level"
                                    name="difficulty_level"
                                    class="flex h-11 w-full rounded-lg border border-input bg-background px-4 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary"
                                    required
                                >
                                    <option value="beginner">Pemula</option>
                                    <option value="intermediate">Menengah</option>
                                    <option value="advanced">Lanjutan</option>
                                </select>
                                <InputError :message="errors.difficulty_level" />
                            </div>

                            <div class="space-y-2">
                                <Label for="visibility" class="text-sm font-medium">
                                    Visibilitas <span class="text-destructive">*</span>
                                </Label>
                                <select
                                    id="visibility"
                                    name="visibility"
                                    class="flex h-11 w-full rounded-lg border border-input bg-background px-4 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary"
                                    required
                                >
                                    <option value="public">Publik - Dapat dilihat semua orang</option>
                                    <option value="restricted">Terbatas - Hanya peserta terdaftar</option>
                                    <option value="hidden">Tersembunyi - Tidak tampil di katalog</option>
                                </select>
                                <InputError :message="errors.visibility" />
                            </div>

                            <div class="space-y-2">
                                <Label for="manual_duration_minutes" class="text-sm font-medium">
                                    Durasi Manual (menit)
                                </Label>
                                <Input
                                    id="manual_duration_minutes"
                                    name="manual_duration_minutes"
                                    type="number"
                                    min="0"
                                    placeholder="Kosongkan untuk hitung otomatis"
                                    class="h-11"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Jika diisi, akan menimpa perhitungan durasi otomatis
                                </p>
                                <InputError :message="errors.manual_duration_minutes" />
                            </div>
                        </div>
                    </FormSection>

                    <FormSection title="Tag">
                        <div v-if="tags.length > 0" class="flex flex-wrap gap-2">
                            <label
                                v-for="tag in tags"
                                :key="tag.id"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-4 py-2 text-sm transition-all has-[:checked]:border-primary has-[:checked]:bg-primary/10 has-[:checked]:text-primary hover:border-primary/50"
                            >
                                <input
                                    type="checkbox"
                                    :name="`tag_ids[]`"
                                    :value="tag.id"
                                    class="sr-only"
                                />
                                {{ tag.name }}
                            </label>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            Belum ada tag tersedia
                        </p>
                        <InputError :message="errors.tag_ids" />
                    </FormSection>

                    <div class="sticky bottom-4 flex gap-3 rounded-xl border bg-card p-4 shadow-lg">
                        <Link :href="index().url" class="flex-1">
                            <Button type="button" variant="outline" class="w-full h-11">
                                Batal
                            </Button>
                        </Link>
                        <Button type="submit" class="flex-1 h-11" :disabled="processing">
                            {{ processing ? 'Menyimpan...' : 'Simpan Kursus' }}
                        </Button>
                    </div>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
