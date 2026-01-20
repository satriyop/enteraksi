<script setup lang="ts">
// =============================================================================
// CourseThumbnailUpload Component
// Thumbnail upload with preview for courses
// =============================================================================

import FormSection from '@/components/crud/FormSection.vue';
import InputError from '@/components/InputError.vue';
import { ImagePlus } from 'lucide-vue-next';
import { ref } from 'vue';

// =============================================================================
// Types
// =============================================================================

interface Props {
    error?: string;
    existingUrl?: string | null;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

const thumbnailPreview = ref<string | null>(props.existingUrl ?? null);

// =============================================================================
// Methods
// =============================================================================

const handleThumbnailChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        thumbnailPreview.value = URL.createObjectURL(file);
    }
};
</script>

<template>
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
                <!-- Allow re-upload when preview exists -->
                <label
                    v-if="thumbnailPreview"
                    class="absolute inset-0 flex cursor-pointer items-center justify-center bg-black/50 opacity-0 transition-opacity hover:opacity-100"
                >
                    <div class="flex flex-col items-center gap-2 text-white">
                        <ImagePlus class="h-8 w-8" />
                        <span class="text-sm font-medium">Ganti Gambar</span>
                    </div>
                    <input
                        type="file"
                        name="thumbnail"
                        accept="image/*"
                        class="hidden"
                        @change="handleThumbnailChange"
                    />
                </label>
            </div>
            <InputError :message="error" />
        </div>
    </FormSection>
</template>
