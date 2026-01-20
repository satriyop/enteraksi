<script setup lang="ts">
// =============================================================================
// CourseSettingsSidebar Component
// Course settings: category, difficulty, visibility, duration
// =============================================================================

import FormSection from '@/components/crud/FormSection.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type Category } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface Props {
    categories: Category[];
    errors: {
        category_id?: string;
        difficulty_level?: string;
        visibility?: string;
        manual_duration_minutes?: string;
    };
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();
</script>

<template>
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
</template>
