<script setup lang="ts">
// =============================================================================
// GradeAnswerCard Component
// Individual answer grading form with score, correctness, and feedback
// =============================================================================

import InputError from '@/components/InputError.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { questionTypeLabel } from '@/lib/formatters';
import { Check, X } from 'lucide-vue-next';
import type { QuestionType } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface QuestionOption {
    id: number;
    option_text: string;
    is_correct: boolean;
}

interface Question {
    id: number;
    question_text: string;
    question_type: QuestionType;
    points: number;
    options: QuestionOption[];
}

interface Answer {
    id: number;
    question_id: number;
    answer_text: string | null;
    file_path: string | null;
    question: Question;
}

interface Props {
    answer: Answer;
    index: number;
    errors: Record<string, string>;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

const score = defineModel<number>('score', { required: true });
const isCorrect = defineModel<boolean>('isCorrect', { required: true });
const feedback = defineModel<string>('feedback', { required: true });

defineEmits<{
    scoreUpdated: [];
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center justify-between">
                <span>Pertanyaan {{ index + 1 }}</span>
                <span class="text-sm bg-primary/10 text-primary px-2 py-1 rounded-full">
                    {{ questionTypeLabel(answer.question.question_type) }}
                </span>
            </CardTitle>
            <CardDescription>
                {{ answer.question.question_text }}
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="space-y-2">
                <Label class="text-sm font-medium">Jawaban Peserta</Label>
                <div v-if="answer.question.question_type === 'file_upload' && answer.file_path" class="mb-3">
                    <a :href="answer.file_path" target="_blank" class="text-primary underline">
                        Unduh Berkas Jawaban
                    </a>
                </div>
                <div v-else class="rounded-lg border p-3 bg-muted/50">
                    <p>{{ answer.answer_text || 'Tidak ada jawaban' }}</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <Label :for="`answers[${index}][score]`" class="text-sm font-medium">
                        Poin (maks: {{ answer.question.points }})
                    </Label>
                    <Input
                        :id="`answers[${index}][score]`"
                        v-model="score"
                        :name="`answers[${index}][score]`"
                        type="number"
                        min="0"
                        :max="answer.question.points"
                        class="h-11"
                        @update:model-value="$emit('scoreUpdated')"
                    />
                    <InputError :message="errors[`answers.${index}.score`]" />
                </div>

                <div class="space-y-2">
                    <Label class="text-sm font-medium">Status</Label>
                    <div class="flex items-center gap-4">
                        <Label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="radio"
                                v-model="isCorrect"
                                :value="true"
                                :name="`answers[${index}][is_correct]`"
                                class="h-4 w-4"
                            />
                            <Check class="h-4 w-4 text-green-600" />
                            Benar
                        </Label>
                        <Label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="radio"
                                v-model="isCorrect"
                                :value="false"
                                :name="`answers[${index}][is_correct]`"
                                class="h-4 w-4"
                            />
                            <X class="h-4 w-4 text-red-600" />
                            Salah
                        </Label>
                    </div>
                    <InputError :message="errors[`answers.${index}.is_correct`]" />
                </div>
            </div>

            <div class="space-y-2">
                <Label :for="`answers[${index}][feedback]`" class="text-sm font-medium">
                    Umpan Balik
                </Label>
                <Textarea
                    :id="`answers[${index}][feedback]`"
                    v-model="feedback"
                    :name="`answers[${index}][feedback]`"
                    rows="3"
                    placeholder="Berikan umpan balik untuk jawaban ini"
                />
                <InputError :message="errors[`answers.${index}.feedback`]" />
            </div>
        </CardContent>
    </Card>
</template>
