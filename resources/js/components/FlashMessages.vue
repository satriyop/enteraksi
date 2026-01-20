<script setup lang="ts">
// =============================================================================
// FlashMessages Component
// Displays success and error flash messages from Inertia page props
// =============================================================================

import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// =============================================================================
// Component Setup
// =============================================================================

const page = usePage();

const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);
</script>

<template>
    <div v-if="flash?.success || flash?.error" class="space-y-2">
        <div v-if="flash?.success" class="rounded-md bg-green-50 dark:bg-green-900/20 p-4">
            <div class="flex">
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800 dark:text-green-300">
                        {{ flash.success }}
                    </p>
                </div>
            </div>
        </div>
        <div v-if="flash?.error" class="rounded-md bg-red-50 dark:bg-red-900/20 p-4">
            <div class="flex">
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800 dark:text-red-300">
                        {{ flash.error }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
