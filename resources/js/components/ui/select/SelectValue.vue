<script setup lang="ts">
import { type HTMLAttributes } from 'vue'

const props = defineProps<{
  placeholder?: string
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <span :class="props.class">
    <slot>{{ placeholder }}</slot>
  </span>
</template>