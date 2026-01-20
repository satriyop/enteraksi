<script setup lang="ts">
// =============================================================================
// QuestionInput Component
// Renders appropriate input based on question type during assessment attempt
// =============================================================================

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/InputError.vue';
import { Upload } from 'lucide-vue-next';
import type { QuestionType } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface QuestionOption {
    id: number;
    option_text: string;
}

interface Props {
    /** Question type */
    questionType: QuestionType;
    /** Question ID for unique input names */
    questionId: number;
    /** Question index for form array */
    questionIndex: number;
    /** Options for multiple choice/matching */
    options?: QuestionOption[];
    /** Validation error message */
    error?: string;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    options: () => [],
});

const answerText = defineModel<string>('answerText', { default: '' });
const file = defineModel<File | null>('file', { default: null });

// =============================================================================
// Methods
// =============================================================================

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const selectedFile = target.files?.[0];
    if (selectedFile) {
        file.value = selectedFile;
    }
};
</script>

<template>
    <!-- Multiple Choice -->
    <div v-if="questionType === 'multiple_choice'" class="space-y-3">
        <RadioGroup
            v-model="answerText"
            :name="`answers[${questionIndex}][answer_text]`"
        >
            <div v-for="(option, oIndex) in options" :key="option.id" class="flex items-center space-x-2">
                <RadioGroupItem
                    :value="String.fromCharCode(65 + oIndex)"
                    :id="`q${questionId}-opt${oIndex}`"
                />
                <Label :for="`q${questionId}-opt${oIndex}`" class="flex-1 cursor-pointer">
                    {{ String.fromCharCode(65 + oIndex) }}. {{ option.option_text }}
                </Label>
            </div>
        </RadioGroup>
        <InputError :message="error" />
    </div>

    <!-- True/False -->
    <div v-else-if="questionType === 'true_false'" class="space-y-3">
        <RadioGroup
            v-model="answerText"
            :name="`answers[${questionIndex}][answer_text]`"
        >
            <div class="flex items-center space-x-2">
                <RadioGroupItem value="true" :id="`q${questionId}-true`" />
                <Label :for="`q${questionId}-true`" class="cursor-pointer">Benar</Label>
            </div>
            <div class="flex items-center space-x-2">
                <RadioGroupItem value="false" :id="`q${questionId}-false`" />
                <Label :for="`q${questionId}-false`" class="cursor-pointer">Salah</Label>
            </div>
        </RadioGroup>
        <InputError :message="error" />
    </div>

    <!-- Short Answer -->
    <div v-else-if="questionType === 'short_answer'" class="space-y-3">
        <Textarea
            v-model="answerText"
            :name="`answers[${questionIndex}][answer_text]`"
            placeholder="Masukkan jawaban singkat Anda"
            rows="3"
            class="min-h-[100px]"
        />
        <InputError :message="error" />
    </div>

    <!-- Essay -->
    <div v-else-if="questionType === 'essay'" class="space-y-3">
        <Textarea
            v-model="answerText"
            :name="`answers[${questionIndex}][answer_text]`"
            placeholder="Tulis esai Anda di sini..."
            rows="6"
            class="min-h-[200px]"
        />
        <InputError :message="error" />
    </div>

    <!-- File Upload -->
    <div v-else-if="questionType === 'file_upload'" class="space-y-3">
        <div class="border-2 border-dashed rounded-lg p-6 text-center">
            <Upload class="mx-auto h-8 w-8 text-muted-foreground mb-2" />
            <p class="text-sm text-muted-foreground mb-2">
                Unggah jawaban Anda dalam format file
            </p>
            <Input
                type="file"
                :name="`answers[${questionIndex}][file]`"
                @change="handleFileChange"
                class="hidden"
                :id="`file-upload-${questionId}`"
            />
            <Label :for="`file-upload-${questionId}`" class="cursor-pointer">
                <Button type="button" variant="outline" size="sm">
                    Pilih Berkas
                </Button>
            </Label>
            <p v-if="file" class="text-sm mt-2">
                {{ file.name }}
            </p>
        </div>
        <InputError :message="error" />
    </div>

    <!-- Matching -->
    <div v-else-if="questionType === 'matching'" class="space-y-3">
        <p class="text-sm text-muted-foreground">
            Cocokkan item di kolom kiri dengan item yang sesuai di kolom kanan.
        </p>
        <div class="space-y-2">
            <div v-for="(option, oIndex) in options" :key="option.id" class="flex items-center gap-3">
                <span class="font-medium">{{ String.fromCharCode(65 + oIndex) }}.</span>
                <span class="flex-1">{{ option.option_text.split('|')[0] }}</span>
                <Input
                    v-model="answerText"
                    placeholder="Jawaban"
                    class="w-32"
                />
            </div>
        </div>
        <InputError :message="error" />
    </div>
</template>
