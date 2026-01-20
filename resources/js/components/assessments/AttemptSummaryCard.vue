<script setup lang="ts">
// =============================================================================
// AttemptSummaryCard Component
// Sidebar summary with final score, status, and actions
// =============================================================================

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { BarChart2, CheckCircle, Users, Clock, Eye, PlayCircle } from 'lucide-vue-next';
import { computed } from 'vue';

// =============================================================================
// Types
// =============================================================================

interface Props {
    score: number;
    maxScore: number;
    percentage: number;
    passed: boolean;
    attemptNumber: number;
    maxAttempts: number;
    startedAt: string;
    submittedAt: string;
    courseId: number;
    assessmentId: number;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

// =============================================================================
// Computed
// =============================================================================

const completionMinutes = computed(() => {
    const start = new Date(props.startedAt).getTime();
    const end = new Date(props.submittedAt).getTime();
    return Math.floor((end - start) / 60000);
});

const canRetry = computed(() => !props.passed && props.attemptNumber < props.maxAttempts);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Ringkasan</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="flex items-center gap-3">
                <BarChart2 class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Nilai Akhir</p>
                    <p class="text-xl font-bold">
                        {{ score }} / {{ maxScore }} ({{ percentage }}%)
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
                <Users class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Percobaan Ke-</p>
                    <p class="font-medium">{{ attemptNumber }} / {{ maxAttempts }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Clock class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Waktu Penyelesaian</p>
                    <p class="font-medium">{{ completionMinutes }} menit</p>
                </div>
            </div>
        </CardContent>
        <CardFooter class="flex flex-col gap-2">
            <Link :href="`/courses/${courseId}/assessments/${assessmentId}`" class="w-full">
                <Button class="w-full gap-2" variant="outline">
                    <Eye class="h-4 w-4" />
                    Kembali ke Penilaian
                </Button>
            </Link>

            <Link v-if="canRetry" :href="`/courses/${courseId}/assessments/${assessmentId}/start`" class="w-full">
                <Button class="w-full gap-2 bg-blue-600 hover:bg-blue-700">
                    <PlayCircle class="h-4 w-4" />
                    Coba Lagi
                </Button>
            </Link>
        </CardFooter>
    </Card>
</template>
