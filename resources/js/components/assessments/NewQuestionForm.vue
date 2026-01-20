<script setup lang="ts">
// =============================================================================
// NewQuestionForm Component
// Form for creating new assessment questions with type selection
// =============================================================================

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus, X } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import type { QuestionType } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface NewQuestionOption {
    text: string;
    is_correct: boolean;
    feedback: string;
}

interface NewQuestionData {
    question_text: string;
    question_type: QuestionType;
    points: number;
    feedback: string;
    options: NewQuestionOption[];
}

// =============================================================================
// Component Setup
// =============================================================================

const emit = defineEmits<{
    add: [question: NewQuestionData];
    cancel: [];
}>();

// =============================================================================
// State
// =============================================================================

const form = ref<NewQuestionData>({
    question_text: '',
    question_type: 'multiple_choice',
    points: 1,
    feedback: '',
    options: [
        { text: '', is_correct: false, feedback: '' },
        { text: '', is_correct: false, feedback: '' },
    ],
});

const hasOptions = computed(() =>
    form.value.question_type === 'multiple_choice' ||
    form.value.question_type === 'true_false'
);

// =============================================================================
// Methods
// =============================================================================

const addOption = () => {
    form.value.options.push({ text: '', is_correct: false, feedback: '' });
};

const removeOption = (index: number) => {
    if (form.value.options.length > 2) {
        form.value.options.splice(index, 1);
    }
};

const handleSubmit = () => {
    emit('add', { ...form.value });

    // Reset form
    form.value = {
        question_text: '',
        question_type: 'multiple_choice',
        points: 1,
        feedback: '',
        options: [
            { text: '', is_correct: false, feedback: '' },
            { text: '', is_correct: false, feedback: '' },
        ],
    };
};
</script>

<template>
    <div class="rounded-lg border p-4">
        <div class="flex items-center justify-between mb-4">
            <h4 class="font-medium">Tambah Pertanyaan Baru</h4>
            <Button type="button" variant="ghost" size="icon" @click="emit('cancel')">
                <X class="h-4 w-4" />
            </Button>
        </div>

        <div class="space-y-3">
            <!-- Question Text -->
            <div class="space-y-2">
                <Label class="text-sm font-medium">Teks Pertanyaan</Label>
                <textarea
                    v-model="form.question_text"
                    rows="3"
                    class="flex w-full rounded-lg border border-input bg-background px-4 py-3 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary disabled:cursor-not-allowed disabled:opacity-50"
                    placeholder="Masukkan teks pertanyaan"
                />
            </div>

            <!-- Question Type -->
            <div class="space-y-2">
                <Label class="text-sm font-medium">Tipe Pertanyaan</Label>
                <select
                    v-model="form.question_type"
                    class="flex h-11 w-full rounded-lg border border-input bg-background px-4 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary"
                >
                    <option value="multiple_choice">Pilihan Ganda</option>
                    <option value="true_false">Benar/Salah</option>
                    <option value="matching">Pencocokan</option>
                    <option value="short_answer">Jawaban Singkat</option>
                    <option value="essay">Esai</option>
                    <option value="file_upload">Unggah Berkas</option>
                </select>
            </div>

            <!-- Points -->
            <div class="space-y-2">
                <Label class="text-sm font-medium">Poin</Label>
                <Input v-model="form.points" type="number" min="1" class="h-11 w-24" />
            </div>

            <!-- Feedback -->
            <div class="space-y-2">
                <Label class="text-sm font-medium">Umpan Balik</Label>
                <textarea
                    v-model="form.feedback"
                    rows="2"
                    class="flex w-full rounded-lg border border-input bg-background px-4 py-3 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary disabled:cursor-not-allowed disabled:opacity-50"
                    placeholder="Umpan balik untuk pertanyaan ini"
                />
            </div>

            <!-- Options (for multiple choice / true-false) -->
            <div v-if="hasOptions" class="space-y-3">
                <h5 class="font-medium">Opsi Jawaban</h5>
                <div v-for="(option, oIndex) in form.options" :key="oIndex" class="flex items-center gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-medium text-primary">
                        {{ String.fromCharCode(65 + oIndex) }}
                    </div>
                    <Input
                        v-model="option.text"
                        placeholder="Teks opsi"
                        class="h-11 flex-1"
                    />
                    <div class="flex items-center gap-2">
                        <Label class="flex items-center gap-1 text-sm">
                            <input type="checkbox" v-model="option.is_correct" class="h-4 w-4" />
                            Benar
                        </Label>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :disabled="form.options.length <= 2"
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

            <!-- Submit Button -->
            <Button type="button" class="gap-2" @click="handleSubmit">
                <Plus class="h-4 w-4" />
                Tambah Pertanyaan
            </Button>
        </div>
    </div>
</template>
