<script setup lang="ts">
// =============================================================================
// ContentPlaceholder Component
// Displays a placeholder when content is not available
// =============================================================================

import type { LucideIcon } from 'lucide-vue-next';
import { BookOpen } from 'lucide-vue-next';

interface Props {
    /** Icon to display */
    icon?: LucideIcon;
    /** Message to show */
    message: string;
    /** Whether to use minimal styling (no background) */
    minimal?: boolean;
}

withDefaults(defineProps<Props>(), {
    icon: () => BookOpen,
    minimal: false,
});
</script>

<template>
    <div
        :class="[
            'flex items-center justify-center',
            minimal ? 'py-8' : 'min-h-[50vh] p-8 rounded-lg bg-muted',
        ]"
    >
        <div class="text-center text-muted-foreground">
            <component :is="icon" class="h-16 w-16 mx-auto mb-2" />
            <p>{{ message }}</p>
        </div>
    </div>
</template>
