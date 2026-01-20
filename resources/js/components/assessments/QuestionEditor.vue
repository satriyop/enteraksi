<script setup lang="ts">
// =============================================================================
// QuestionEditor Component
// Editable question card with options management
// =============================================================================

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { GripVertical, ArrowUp, ArrowDown, Trash2, Plus, X } from 'lucide-vue-next';
import { questionTypeLabel } from '@/lib/utils';
import type { QuestionType } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface QuestionOption {
    id: number;
    option_text: string;
    is_correct: boolean;
    feedback: string | null;
    order: number;
}

interface EditableQuestion {
    id: number;
    question_text: string;
    question_type: QuestionType;
    points: number;
    feedback: string | null;
    order: number;
    options: QuestionOption[];
}

interface Props {
    /** The question data */
    question: EditableQuestion;
    /** Question index (0-based) */
    index: number;
    /** Whether this is the first question */
    isFirst?: boolean;
    /** Whether this is the last question */
    isLast?: boolean;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    isFirst: false,
    isLast: false,
});

const emit = defineEmits<{
    moveUp: [];
    moveDown: [];
    remove: [];
    'update:question': [question: EditableQuestion];
}>();

// =============================================================================
// Methods
// =============================================================================

const updateField = <K extends keyof EditableQuestion>(field: K, value: EditableQuestion[K]) => {
    emit('update:question', { ...props.question, [field]: value });
};

const updateOptionText = (optionIndex: number, text: string) => {
    const newOptions = [...props.question.options];
    newOptions[optionIndex] = { ...newOptions[optionIndex], option_text: text };
    emit('update:question', { ...props.question, options: newOptions });
};

const updateOptionCorrect = (optionIndex: number, isCorrect: boolean) => {
    const newOptions = [...props.question.options];
    newOptions[optionIndex] = { ...newOptions[optionIndex], is_correct: isCorrect };
    emit('update:question', { ...props.question, options: newOptions });
};

const addOption = () => {
    const newOption: QuestionOption = {
        id: 0,
        option_text: `Opsi ${String.fromCharCode(65 + props.question.options.length)}`,
        is_correct: false,
        feedback: null,
        order: props.question.options.length,
    };
    emit('update:question', {
        ...props.question,
        options: [...props.question.options, newOption],
    });
};

const removeOption = (optionIndex: number) => {
    if (props.question.options.length <= 2) return;
    const newOptions = props.question.options.filter((_, i) => i !== optionIndex);
    emit('update:question', { ...props.question, options: newOptions });
};

const hasOptions = props.question.question_type === 'multiple_choice' ||
    props.question.question_type === 'true_false';
</script>

<template>
    <div class="rounded-lg border p-4">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <GripVertical class="h-4 w-4 cursor-move text-muted-foreground" />
                <h4 class="font-medium">Pertanyaan {{ index + 1 }}</h4>
                <span class="text-xs bg-primary/10 text-primary px-2 py-1 rounded-full">
                    {{ questionTypeLabel(question.question_type) }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :disabled="isFirst"
                    @click="emit('moveUp')"
                >
                    <ArrowUp class="h-4 w-4" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :disabled="isLast"
                    @click="emit('moveDown')"
                >
                    <ArrowDown class="h-4 w-4" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="text-destructive hover:text-destructive"
                    @click="emit('remove')"
                >
                    <Trash2 class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <!-- Question Fields -->
        <div class="space-y-3">
            <div class="space-y-2">
                <Label class="text-sm font-medium">Teks Pertanyaan</Label>
                <textarea
                    :value="question.question_text"
                    @input="updateField('question_text', ($event.target as HTMLTextAreaElement).value)"
                    rows="3"
                    class="flex w-full rounded-lg border border-input bg-background px-4 py-3 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary disabled:cursor-not-allowed disabled:opacity-50"
                />
            </div>

            <div class="space-y-2">
                <Label class="text-sm font-medium">Poin</Label>
                <Input
                    :model-value="question.points"
                    @update:model-value="updateField('points', Number($event))"
                    type="number"
                    min="1"
                    class="h-11 w-24"
                />
            </div>

            <div class="space-y-2">
                <Label class="text-sm font-medium">Umpan Balik</Label>
                <textarea
                    :value="question.feedback || ''"
                    @input="updateField('feedback', ($event.target as HTMLTextAreaElement).value || null)"
                    rows="2"
                    class="flex w-full rounded-lg border border-input bg-background px-4 py-3 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary disabled:cursor-not-allowed disabled:opacity-50"
                    placeholder="Umpan balik untuk pertanyaan ini"
                />
            </div>

            <!-- Options (for multiple choice / true-false) -->
            <div v-if="hasOptions" class="space-y-3">
                <h5 class="font-medium">Opsi Jawaban</h5>
                <div v-for="(option, oIndex) in question.options" :key="option.id || oIndex" class="flex items-center gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-medium text-primary">
                        {{ String.fromCharCode(65 + oIndex) }}
                    </div>
                    <Input
                        :model-value="option.option_text"
                        @update:model-value="updateOptionText(oIndex, String($event))"
                        placeholder="Teks opsi"
                        class="h-11 flex-1"
                    />
                    <div class="flex items-center gap-2">
                        <Label class="flex items-center gap-1 text-sm">
                            <input
                                type="checkbox"
                                :checked="option.is_correct"
                                @change="updateOptionCorrect(oIndex, ($event.target as HTMLInputElement).checked)"
                                class="h-4 w-4"
                            />
                            Benar
                        </Label>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :disabled="question.options.length <= 2"
                            @click="removeOption(oIndex)"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
                <Button type="button" variant="outline" size="sm" class="gap-2" @click="addOption">
                    <Plus class="h-4 w-4" />
                    Tambah Opsi
                </Button>
            </div>
        </div>
    </div>
</template>
