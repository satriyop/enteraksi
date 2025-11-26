<script setup lang="ts">
interface Props {
    title: string;
    description?: string;
    collapsible?: boolean;
}

withDefaults(defineProps<Props>(), {
    collapsible: false,
});
</script>

<template>
    <div class="rounded-xl border bg-card">
        <div class="border-b px-6 py-4">
            <h3 class="font-semibold text-foreground">{{ title }}</h3>
            <p v-if="description" class="mt-0.5 text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div class="p-6">
            <slot />
        </div>
    </div>
</template>
