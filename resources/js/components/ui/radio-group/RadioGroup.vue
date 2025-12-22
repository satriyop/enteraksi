<script setup lang="ts">
import { cn } from '@/lib/utils'
import { type HTMLAttributes } from 'vue'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <div :class="cn('grid gap-2', props.class)" role="radiogroup">
    <slot />
  </div>
</template>