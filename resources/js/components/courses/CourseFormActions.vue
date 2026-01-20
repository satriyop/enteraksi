<script setup lang="ts">
// =============================================================================
// CourseFormActions Component
// Sticky submit/cancel buttons for course forms
// =============================================================================

import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';

// =============================================================================
// Types
// =============================================================================

interface Props {
    cancelHref: string;
    submitText: string;
    processingText: string;
    processing: boolean;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();
</script>

<template>
    <div class="sticky bottom-4 flex gap-3 rounded-xl border bg-card p-4 shadow-lg">
        <Link :href="cancelHref" class="flex-1">
            <Button type="button" variant="outline" class="w-full h-11">
                Batal
            </Button>
        </Link>
        <Button type="submit" class="flex-1 h-11" :disabled="processing">
            {{ processing ? processingText : submitText }}
        </Button>
    </div>
</template>
