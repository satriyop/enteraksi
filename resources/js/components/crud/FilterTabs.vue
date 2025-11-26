<script setup lang="ts">
interface Tab {
    value: string;
    label: string;
    count?: number;
}

interface Props {
    tabs: Tab[];
    modelValue: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const selectTab = (value: string) => {
    emit('update:modelValue', value);
};
</script>

<template>
    <div class="flex flex-wrap gap-1 rounded-lg bg-muted/50 p-1">
        <button
            v-for="tab in tabs"
            :key="tab.value"
            type="button"
            class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-all"
            :class="
                modelValue === tab.value
                    ? 'bg-background text-foreground shadow-sm'
                    : 'text-muted-foreground hover:bg-background/50 hover:text-foreground'
            "
            @click="selectTab(tab.value)"
        >
            {{ tab.label }}
            <span
                v-if="tab.count !== undefined"
                class="rounded-full px-2 py-0.5 text-xs"
                :class="modelValue === tab.value ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'"
            >
                {{ tab.count }}
            </span>
        </button>
    </div>
</template>
