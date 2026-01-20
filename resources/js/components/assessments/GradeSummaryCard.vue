<script setup lang="ts">
// =============================================================================
// GradeSummaryCard Component
// Displays current grading summary with score and pass status
// =============================================================================

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Award, CheckCircle, AlertTriangle, FileText } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface Props {
    totalScore: number;
    maxScore: number;
    percentage: number;
    passed: boolean;
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
            <CardTitle>Ringkasan Penilaian</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="flex items-center gap-3">
                <Award class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Nilai Saat Ini</p>
                    <p class="text-xl font-bold">
                        {{ totalScore }} / {{ maxScore }} ({{ percentage }}%)
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <CheckCircle class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Status</p>
                    <p class="font-medium" :class="passed ? 'text-green-600' : 'text-red-600'">
                        {{ passed ? 'Lulus' : 'Tidak Lulus' }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <AlertTriangle class="h-5 w-5 text-muted-foreground" />
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
