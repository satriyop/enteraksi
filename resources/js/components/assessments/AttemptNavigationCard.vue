<script setup lang="ts">
// =============================================================================
// AttemptNavigationCard Component
// Quick navigation buttons to jump to specific questions
// =============================================================================

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

// =============================================================================
// Types
// =============================================================================

interface Props {
    totalQuestions: number;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

const emit = defineEmits<{
    navigate: [index: number];
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Navigasi Pertanyaan</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="grid grid-cols-5 gap-2">
                <Button
                    v-for="index in totalQuestions"
                    :key="index"
                    variant="outline"
                    size="sm"
                    class="h-8 w-8 p-0"
                    @click="emit('navigate', index - 1)"
                >
                    {{ index }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
