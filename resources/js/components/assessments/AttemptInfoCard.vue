<script setup lang="ts">
// =============================================================================
// AttemptInfoCard Component
// Displays attempt information: attempt number, time elapsed, time left, passing score, total questions
// =============================================================================

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Clock, CheckCircle, FileText, AlertTriangle, ListOrdered } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface Props {
    attemptNumber: number;
    timeElapsed: string;
    timeLeft?: string | null;
    hasTimeLimit: boolean;
    passingScore: number;
    totalQuestions: number;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Informasi Percobaan</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="flex items-center gap-3">
                <ListOrdered class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Percobaan Ke-</p>
                    <p class="font-medium">{{ attemptNumber }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Clock class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Waktu Berjalan</p>
                    <p class="font-medium">{{ timeElapsed }}</p>
                </div>
            </div>

            <div v-if="hasTimeLimit" class="flex items-center gap-3">
                <AlertTriangle class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Sisa Waktu</p>
                    <p class="font-medium">{{ timeLeft }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <CheckCircle class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Nilai Kelulusan</p>
                    <p class="font-medium">{{ passingScore }}%</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <FileText class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Total Pertanyaan</p>
                    <p class="font-medium">{{ totalQuestions }}</p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
