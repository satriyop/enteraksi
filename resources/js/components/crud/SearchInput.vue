<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';

interface Props {
    modelValue: string;
    placeholder?: string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Cari...',
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const clearSearch = () => {
    emit('update:modelValue', '');
};
</script>

<template>
    <div class="relative">
        <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <input
            :value="modelValue"
            type="text"
            :placeholder="placeholder"
            class="h-11 w-full rounded-lg border border-input bg-background pl-11 pr-10 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-primary/20"
            @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        />
        <button
            v-if="modelValue"
            type="button"
            class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
            @click="clearSearch"
        >
            <X class="h-4 w-4" />
        </button>
    </div>
</template>
