<script setup lang="ts">
// =============================================================================
// AssessmentAttemptCard Component
// Displays latest attempt summary with score and pass/fail status
// =============================================================================

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { attemptStatusLabel, attemptStatusBadgeColor } from '@/lib/formatters';
import { Link } from '@inertiajs/vue3';
import { Eye, Check, X } from 'lucide-vue-next';
import type { AttemptStatus } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface AttemptSummary {
    id: number;
    attempt_number: number;
    status: AttemptStatus;
    score: number | null;
    max_score: number;
    percentage: number | null;
    passed: boolean;
    started_at: string;
    submitted_at: string | null;
    graded_at: string | null;
}

interface Props {
    attempt: AttemptSummary;
    detailHref: string;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

// =============================================================================
// Methods
// =============================================================================

const formatDateTime = (date: string | null): string => {
    if (!date) return '-';
    return new Date(date).toLocaleString('id-ID');
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Percobaan Terakhir</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm text-muted-foreground mb-1">Status</p>
                    <span :class="`text-sm font-medium px-3 py-1 rounded-full ${attemptStatusBadgeColor(attempt.status)}`">
                        {{ attemptStatusLabel(attempt.status) }}
                    </span>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground mb-1">Percobaan Ke-</p>
                    <p class="font-medium">{{ attempt.attempt_number }}</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm text-muted-foreground mb-1">Nilai</p>
                    <p class="text-2xl font-bold">
                        {{ attempt.score !== null ? attempt.score : '-' }} / {{ attempt.max_score }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground mb-1">Persentase</p>
                    <p class="text-2xl font-bold">
                        {{ attempt.percentage !== null ? `${attempt.percentage}%` : '-' }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full"
                    :class="attempt.passed ? 'bg-green-100' : 'bg-red-100'"
                >
                    <Check v-if="attempt.passed" class="h-5 w-5 text-green-600" />
                    <X v-else class="h-5 w-5 text-red-600" />
                </div>
                <div>
                    <p class="text-sm font-medium">Status Kelulusan</p>
                    <p class="font-medium" :class="attempt.passed ? 'text-green-600' : 'text-red-600'">
                        {{ attempt.passed ? 'Lulus' : 'Tidak Lulus' }}
                    </p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm text-muted-foreground mb-1">Dimulai</p>
                    <p class="font-medium">{{ formatDateTime(attempt.started_at) }}</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground mb-1">Diserahkan</p>
                    <p class="font-medium">{{ formatDateTime(attempt.submitted_at) }}</p>
                </div>
            </div>

            <div v-if="attempt.graded_at">
                <p class="text-sm text-muted-foreground mb-1">Dinilai</p>
                <p class="font-medium">{{ formatDateTime(attempt.graded_at) }}</p>
            </div>
        </CardContent>
        <CardFooter>
            <Link :href="detailHref" class="w-full">
                <Button class="w-full gap-2">
                    <Eye class="h-4 w-4" />
                    Lihat Detail Percobaan
                </Button>
            </Link>
        </CardFooter>
    </Card>
</template>
