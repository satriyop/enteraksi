<script setup lang="ts">
// =============================================================================
// AssessmentFormSidebar Component
// Sidebar for assessment create/edit with status, visibility, and action buttons
// =============================================================================

import FormSection from '@/components/crud/FormSection.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Link } from '@inertiajs/vue3';
import { type AssessmentStatus, type AssessmentVisibility } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface Props {
    cancelHref: string;
    processing: boolean;
    submitLabel?: string;
    processingLabel?: string;
    errors: {
        status?: string;
        visibility?: string;
    };
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

const status = defineModel<AssessmentStatus>('status', { required: true });
const visibility = defineModel<AssessmentVisibility>('visibility', { required: true });
</script>

<template>
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
                        v-model="status"
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
                        v-model="visibility"
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
            <Link :href="cancelHref" class="flex-1">
                <Button type="button" variant="outline" class="w-full h-11">
                    Batal
                </Button>
            </Link>
            <Button type="submit" class="flex-1 h-11" :disabled="processing">
                {{ processing ? (processingLabel || 'Menyimpan...') : (submitLabel || 'Simpan') }}
            </Button>
        </div>
    </div>
</template>
