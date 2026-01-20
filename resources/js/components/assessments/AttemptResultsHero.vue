<script setup lang="ts">
// =============================================================================
// AttemptResultsHero Component
// Pass/fail celebration display with scores and completion times
// =============================================================================

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Trophy, AlertTriangle, Clock, CheckCircle } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface Props {
    passed: boolean;
    score: number;
    maxScore: number;
    percentage: number;
    correctAnswers: number;
    totalQuestions: number;
    passingScore: number;
    startedAt: string;
    submittedAt: string;
    attemptNumber: number;
    assessmentTitle: string;
    feedback: string | null;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

// =============================================================================
// Methods
// =============================================================================

const formatDateTime = (date: string): string => {
    return new Date(date).toLocaleString('id-ID');
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Hasil Penilaian</CardTitle>
            <CardDescription>
                Percobaan {{ attemptNumber }} - {{ assessmentTitle }}
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <!-- Hero Section -->
            <div class="flex flex-col items-center justify-center gap-4 py-8">
                <div
                    class="flex h-20 w-20 items-center justify-center rounded-full"
                    :class="passed ? 'bg-green-100' : 'bg-red-100'"
                >
                    <Trophy v-if="passed" class="h-12 w-12 text-green-600" />
                    <AlertTriangle v-else class="h-12 w-12 text-red-600" />
                </div>

                <h2 class="text-2xl font-bold" :class="passed ? 'text-green-600' : 'text-red-600'">
                    {{ passed ? 'Selamat! Anda Lulus!' : 'Anda Belum Lulus' }}
                </h2>

                <p class="text-muted-foreground">
                    {{ passed ? 'Anda telah berhasil menyelesaikan penilaian ini.' : 'Anda dapat mencoba lagi untuk meningkatkan nilai Anda.' }}
                </p>
            </div>

            <!-- Score Display -->
            <div class="grid gap-6 md:grid-cols-2">
                <div class="text-center">
                    <p class="text-sm text-muted-foreground mb-1">Nilai Anda</p>
                    <p class="text-3xl font-bold">{{ score }} / {{ maxScore }}</p>
                </div>
                <div class="text-center">
                    <p class="text-sm text-muted-foreground mb-1">Persentase</p>
                    <p class="text-3xl font-bold">{{ percentage }}%</p>
                </div>
            </div>

            <!-- Additional Stats -->
            <div class="grid gap-6 md:grid-cols-2">
                <div class="text-center">
                    <p class="text-sm text-muted-foreground mb-1">Jawaban Benar</p>
                    <p class="text-2xl font-bold text-green-600">{{ correctAnswers }} / {{ totalQuestions }}</p>
                </div>
                <div class="text-center">
                    <p class="text-sm text-muted-foreground mb-1">Nilai Kelulusan</p>
                    <p class="text-2xl font-bold">{{ passingScore }}%</p>
                </div>
            </div>

            <!-- Time Info -->
            <div class="space-y-3">
                <p class="text-sm text-muted-foreground">Waktu Penyelesaian</p>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="flex items-center gap-3">
                        <Clock class="h-5 w-5 text-muted-foreground" />
                        <div>
                            <p class="text-sm text-muted-foreground">Dimulai</p>
                            <p class="font-medium">{{ formatDateTime(startedAt) }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <CheckCircle class="h-5 w-5 text-muted-foreground" />
                        <div>
                            <p class="text-sm text-muted-foreground">Diserahkan</p>
                            <p class="font-medium">{{ formatDateTime(submittedAt) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feedback -->
            <div v-if="feedback" class="space-y-2">
                <p class="text-sm text-muted-foreground">Umpan Balik</p>
                <div class="rounded-lg border p-3 bg-muted/50">
                    <p class="whitespace-pre-wrap">{{ feedback }}</p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
