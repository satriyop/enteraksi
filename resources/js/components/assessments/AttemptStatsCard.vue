<script setup lang="ts">
// =============================================================================
// AttemptStatsCard Component
// Displays attempt statistics (correct/wrong/pending)
// =============================================================================

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Check, X, AlertTriangle } from 'lucide-vue-next';
import { computed } from 'vue';

// =============================================================================
// Types
// =============================================================================

interface Answer {
    is_correct: boolean | null;
}

interface Props {
    answers: Answer[];
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

// =============================================================================
// Computed
// =============================================================================

const correctCount = computed(() => props.answers.filter(a => a.is_correct === true).length);
const wrongCount = computed(() => props.answers.filter(a => a.is_correct === false).length);
const pendingCount = computed(() => props.answers.filter(a => a.is_correct === null).length);
const totalCount = computed(() => props.answers.length);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Statistik</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="flex items-center gap-3">
                <Check class="h-5 w-5 text-green-600" />
                <div>
                    <p class="text-sm text-muted-foreground">Jawaban Benar</p>
                    <p class="text-xl font-bold text-green-600">{{ correctCount }} / {{ totalCount }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <X class="h-5 w-5 text-red-600" />
                <div>
                    <p class="text-sm text-muted-foreground">Jawaban Salah</p>
                    <p class="text-xl font-bold text-red-600">{{ wrongCount }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <AlertTriangle class="h-5 w-5 text-yellow-600" />
                <div>
                    <p class="text-sm text-muted-foreground">Menunggu Penilaian</p>
                    <p class="text-xl font-bold text-yellow-600">{{ pendingCount }}</p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
