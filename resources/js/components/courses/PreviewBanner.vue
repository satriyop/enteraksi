<script setup lang="ts">
// =============================================================================
// PreviewBanner Component
// Banner showing preview mode status with enroll button
// =============================================================================

import { Button } from '@/components/ui/button';
import { Eye } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface Props {
    isEnrolled: boolean;
    isEnrolling: boolean;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

const emit = defineEmits<{
    enroll: [];
}>();
</script>

<template>
    <div class="mb-6 rounded-lg bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 p-4">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                <Eye class="h-5 w-5 text-green-600 dark:text-green-400" />
            </div>
            <div class="flex-1">
                <p class="font-medium text-green-800 dark:text-green-200">Mode Preview</p>
                <p class="text-sm text-green-700 dark:text-green-300">
                    Anda sedang melihat preview materi. Daftar ke kursus untuk akses penuh.
                </p>
            </div>
            <Button
                v-if="!isEnrolled"
                size="sm"
                :disabled="isEnrolling"
                @click="emit('enroll')"
            >
                {{ isEnrolling ? 'Mendaftar...' : 'Daftar Kursus' }}
            </Button>
        </div>
    </div>
</template>
