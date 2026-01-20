<script setup lang="ts">
// =============================================================================
// AttemptAnswerReviewCard Component
// Displays answer review details with question, answer, and feedback
// =============================================================================

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { questionTypeLabel } from '@/lib/formatters';
import { Check, X, AlertTriangle, Award } from 'lucide-vue-next';
import type { QuestionType } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface Question {
    id: number;
    question_text: string;
    question_type: QuestionType;
    points: number;
}

interface Answer {
    id: number;
    question_id: number;
    answer_text: string | null;
    is_correct: boolean | null;
    score: number | null;
    feedback: string | null;
    question: Question;
}

interface Props {
    answers: Answer[];
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Detail Jawaban</CardTitle>
            <CardDescription>
                Review jawaban Anda untuk setiap pertanyaan
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <div v-for="(answer, index) in answers" :key="answer.id" class="space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="font-medium">Pertanyaan {{ index + 1 }}</h4>
                    <span class="text-sm bg-primary/10 text-primary px-2 py-1 rounded-full">
                        {{ questionTypeLabel(answer.question.question_type) }}
                    </span>
                </div>

                <p class="text-sm text-muted-foreground mb-2">Pertanyaan:</p>
                <p class="mb-3">{{ answer.question.question_text }}</p>

                <p class="text-sm text-muted-foreground mb-2">Jawaban Anda:</p>
                <div v-if="answer.question.question_type === 'file_upload' && answer.answer_text" class="mb-3">
                    <a :href="answer.answer_text" target="_blank" class="text-primary underline">
                        Unduh Berkas Jawaban
                    </a>
                </div>
                <div v-else class="mb-3">
                    <p>{{ answer.answer_text || 'Tidak ada jawaban' }}</p>
                </div>

                <div class="flex items-center gap-3 mb-3">
                    <div
                        class="flex h-6 w-6 items-center justify-center rounded-full"
                        :class="{
                            'bg-green-100': answer.is_correct,
                            'bg-red-100': answer.is_correct === false,
                            'bg-gray-100': answer.is_correct === null
                        }"
                    >
                        <Check v-if="answer.is_correct" class="h-4 w-4 text-green-600" />
                        <X v-else-if="answer.is_correct === false" class="h-4 w-4 text-red-600" />
                        <AlertTriangle v-else class="h-4 w-4 text-gray-600" />
                    </div>
                    <div>
                        <p class="text-sm font-medium">Status</p>
                        <p
                            class="text-sm"
                            :class="{
                                'text-green-600': answer.is_correct,
                                'text-red-600': answer.is_correct === false,
                                'text-gray-600': answer.is_correct === null
                            }"
                        >
                            {{ answer.is_correct ? 'Benar' : answer.is_correct === false ? 'Salah' : 'Menunggu Penilaian' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Award class="h-5 w-5 text-muted-foreground" />
                    <div>
                        <p class="text-sm text-muted-foreground">Poin</p>
                        <p class="font-medium">
                            {{ answer.score !== null ? `${answer.score} / ${answer.question.points}` : 'Menunggu Penilaian' }}
                        </p>
                    </div>
                </div>

                <div v-if="answer.feedback" class="mt-3 rounded-lg border p-3 bg-muted/50">
                    <p class="text-sm text-muted-foreground mb-1">Umpan Balik:</p>
                    <p class="whitespace-pre-wrap">{{ answer.feedback }}</p>
                </div>

                <hr v-if="index < answers.length - 1" class="my-4" />
            </div>
        </CardContent>
    </Card>
</template>
