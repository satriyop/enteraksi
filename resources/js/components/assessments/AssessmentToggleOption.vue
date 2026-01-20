<script setup lang="ts">
// =============================================================================
// AssessmentToggleOption Component
// Reusable toggle option with icon, title, description, and switch
// =============================================================================

import { Switch } from '@/components/ui/switch';
import { type Component } from 'vue';

// =============================================================================
// Types
// =============================================================================

interface Props {
    id: string;
    name: string;
    icon: Component;
    title: string;
    description: string;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

const modelValue = defineModel<boolean>({ required: true });
</script>

<template>
    <div class="flex items-center justify-between rounded-lg border p-4">
        <div class="flex items-center gap-3">
            <component :is="icon" class="h-5 w-5 text-muted-foreground" />
            <div>
                <h4 class="font-medium">{{ title }}</h4>
                <p class="text-sm text-muted-foreground">{{ description }}</p>
            </div>
        </div>
        <Switch :id="id" :name="name" v-model="modelValue" />
    </div>
</template>
