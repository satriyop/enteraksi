<script setup lang="ts">
// =============================================================================
// DynamicListField Component
// Reusable dynamic list with add/remove functionality
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
    label: string;
    inputName: string;
    placeholder?: string;
    addButtonText: string;
    error?: string;
    indicatorVariant?: 'primary' | 'muted';
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    indicatorVariant: 'primary',
});

const items = defineModel<string[]>({ required: true });

// =============================================================================
// Methods
// =============================================================================

const addItem = () => {
    items.value.push('');
};

const removeItem = (index: number) => {
    if (items.value.length > 1) {
        items.value.splice(index, 1);
    }
};
</script>

<template>
    <div class="space-y-3">
        <Label>{{ label }}</Label>
        <div v-for="(_, index) in items" :key="index" class="flex items-center gap-3">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-sm font-medium"
                :class="indicatorVariant === 'primary'
                    ? 'bg-primary/10 text-primary'
                    : 'bg-muted text-muted-foreground'"
            >
                {{ index + 1 }}
            </div>
            <Input
                v-model="items[index]"
                :name="`${inputName}[${index}]`"
                :placeholder="placeholder"
                class="h-11 flex-1"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-10 w-10 shrink-0 text-muted-foreground hover:text-destructive"
                :disabled="items.length === 1"
                @click="removeItem(index)"
            >
                <X class="h-4 w-4" />
            </Button>
        </div>
        <Button type="button" variant="outline" size="sm" class="gap-2" @click="addItem">
            <Plus class="h-4 w-4" />
            {{ addButtonText }}
        </Button>
        <InputError :message="error" />
    </div>
</template>
