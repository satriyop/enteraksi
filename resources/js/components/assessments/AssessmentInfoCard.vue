<script setup lang="ts">
// =============================================================================
// AssessmentInfoCard Component
// Displays assessment info with status, visibility, description, instructions
// =============================================================================

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    assessmentStatusLabel,
    visibilityLabel,
    statusBadgeColor,
    visibilityBadgeColor,
} from '@/lib/formatters';
import type { AssessmentStatus, AssessmentVisibility } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface Props {
    status: AssessmentStatus;
    visibility: AssessmentVisibility;
    description: string | null;
    instructions: string | null;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Informasi Penilaian</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm text-muted-foreground mb-1">Status</p>
                    <span :class="`text-sm font-medium px-3 py-1 rounded-full ${statusBadgeColor(status)}`">
                        {{ assessmentStatusLabel(status) }}
                    </span>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground mb-1">Visibilitas</p>
                    <span :class="`text-sm font-medium px-3 py-1 rounded-full ${visibilityBadgeColor(visibility)}`">
                        {{ visibilityLabel(visibility) }}
                    </span>
                </div>
            </div>

            <div v-if="description" class="space-y-2">
                <p class="text-sm text-muted-foreground">Deskripsi</p>
                <p>{{ description }}</p>
            </div>

            <div v-if="instructions" class="space-y-2">
                <p class="text-sm text-muted-foreground">Instruksi</p>
                <div class="rounded-lg border p-3 bg-muted/50">
                    <p class="whitespace-pre-wrap">{{ instructions }}</p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
