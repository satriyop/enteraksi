<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img
        src="/storage/enteraksi-logo-min.svg"
        alt="Enteraksi Logo"
        :class="className"
        v-bind="$attrs"
    />
</template>
