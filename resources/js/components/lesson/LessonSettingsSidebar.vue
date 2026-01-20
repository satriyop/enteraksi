<script setup lang="ts">
// =============================================================================
// LessonSettingsSidebar Component
// Settings sidebar for lesson edit (duration, free preview, actions)
// =============================================================================

import FormSection from '@/components/crud/FormSection.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link } from '@inertiajs/vue3';
import { Clock, Eye, Save } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface Props {
    cancelHref: string;
    estimatedDurationMinutes: number | null;
    isFreePreview: boolean;
    isProcessing: boolean;
    errors: Record<string, string>;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

const emit = defineEmits<{
    'update:estimatedDurationMinutes': [value: number | null];
    'update:isFreePreview': [value: boolean];
}>();
</script>

<template>
    <div class="space-y-6">
        <FormSection title="Pengaturan">
            <div class="space-y-5">
                <!-- Duration -->
                <div class="space-y-2">
                    <Label for="estimated_duration_minutes" class="flex items-center gap-2 text-sm font-medium">
                        <Clock class="h-4 w-4 text-muted-foreground" />
                        Estimasi Durasi (menit)
                    </Label>
                    <Input
                        id="estimated_duration_minutes"
                        :model-value="estimatedDurationMinutes"
                        type="number"
                        min="1"
                        placeholder="Contoh: 15"
                        class="h-11"
                        @update:model-value="emit('update:estimatedDurationMinutes', $event ? Number($event) : null)"
                    />
                    <InputError :message="errors.estimated_duration_minutes" />
                    <p class="text-xs text-muted-foreground">
                        Perkiraan waktu untuk menyelesaikan materi
                    </p>
                </div>

                <!-- Free Preview Checkbox -->
                <label class="flex cursor-pointer items-start gap-4 rounded-xl border-2 p-4 transition-all hover:border-primary/50 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                    <input
                        id="is_free_preview"
                        type="checkbox"
                        :checked="isFreePreview"
                        class="mt-0.5 h-5 w-5 rounded border-input accent-primary"
                        @change="emit('update:isFreePreview', ($event.target as HTMLInputElement).checked)"
                    />
                    <div class="flex-1">
                        <div class="flex items-center gap-2 font-medium">
                            <Eye class="h-4 w-4 text-primary" />
                            Preview Gratis
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Materi ini dapat diakses tanpa pendaftaran kursus
                        </p>
                    </div>
                </label>
            </div>
        </FormSection>

        <!-- Action Buttons -->
        <div class="sticky bottom-4 flex gap-3 rounded-xl border bg-card p-4 shadow-lg">
            <Link :href="cancelHref" class="flex-1">
                <Button type="button" variant="outline" class="w-full h-11">
                    Batal
                </Button>
            </Link>
            <Button type="submit" class="flex-1 h-11 gap-2" :disabled="isProcessing">
                <Save class="h-4 w-4" />
                {{ isProcessing ? 'Menyimpan...' : 'Simpan Materi' }}
            </Button>
        </div>
    </div>
</template>
