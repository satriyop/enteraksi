<script setup lang="ts">
// =============================================================================
// LearningPathObjectivesField Component
// Dynamic list of learning objectives with add/remove
// =============================================================================

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus, X } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface Props {
    error?: string;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

const objectives = defineModel<string[]>({ required: true });

// =============================================================================
// Methods
// =============================================================================

const addObjective = () => {
    objectives.value.push('');
};

const removeObjective = (index: number) => {
    if (objectives.value.length > 1) {
        objectives.value.splice(index, 1);
    }
};
</script>

<template>
    <div class="space-y-3">
        <Label>Tujuan Pembelajaran</Label>
        <div v-for="(_, index) in objectives" :key="index" class="flex gap-2">
            <Input
                v-model="objectives[index]"
                type="text"
                class="flex-1"
                placeholder="Masukkan tujuan pembelajaran"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                :disabled="objectives.length === 1"
                @click="removeObjective(index)"
            >
                <X class="h-4 w-4" />
            </Button>
        </div>
        <Button type="button" variant="outline" size="sm" @click="addObjective">
            <Plus class="mr-2 h-4 w-4" />
            Tambah Tujuan
        </Button>
        <InputError :message="error" />
    </div>
</template>
