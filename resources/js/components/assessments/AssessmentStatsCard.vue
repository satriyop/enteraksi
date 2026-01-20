<script setup lang="ts">
// =============================================================================
// AssessmentStatsCard Component
// Displays assessment statistics (attempts, questions, difficulty)
// =============================================================================

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Users, FileText, AlertTriangle } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface Props {
    attemptsCount: number;
    questionsCount: number;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Statistik</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="flex items-center gap-3">
                <Users class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Total Percobaan</p>
                    <p class="text-xl font-bold">{{ attemptsCount }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <FileText class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Total Pertanyaan</p>
                    <p class="text-xl font-bold">{{ questionsCount }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <AlertTriangle class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Tingkat Kesulitan</p>
                    <p class="text-xl font-bold">Menengah</p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
