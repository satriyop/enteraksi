<script setup lang="ts">
// =============================================================================
// CourseTagsField Component
// Tag selection with checkboxes for courses
// =============================================================================

import FormSection from '@/components/crud/FormSection.vue';
import InputError from '@/components/InputError.vue';
import { type Tag } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface Props {
    tags: Tag[];
    selectedTagIds?: number[];
    error?: string;
}

// =============================================================================
// Component Setup
// =============================================================================

withDefaults(defineProps<Props>(), {
    selectedTagIds: () => [],
});
</script>

<template>
    <FormSection title="Tag">
        <div v-if="tags.length > 0" class="flex flex-wrap gap-2">
            <label
                v-for="tag in tags"
                :key="tag.id"
                class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-4 py-2 text-sm transition-all has-[:checked]:border-primary has-[:checked]:bg-primary/10 has-[:checked]:text-primary hover:border-primary/50"
            >
                <input
                    type="checkbox"
                    name="tag_ids[]"
                    :value="tag.id"
                    :checked="selectedTagIds.includes(tag.id)"
                    class="sr-only"
                />
                {{ tag.name }}
            </label>
        </div>
        <p v-else class="text-sm text-muted-foreground">
            Belum ada tag tersedia
        </p>
        <InputError :message="error" />
    </FormSection>
</template>
