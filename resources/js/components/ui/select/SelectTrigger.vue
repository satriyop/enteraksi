<script setup lang="ts">
import { cn } from '@/lib/utils'
import { ChevronDown } from 'lucide-vue-next'
import { type HTMLAttributes } from 'vue'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <button
    :class="cn(
      'flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 [&>span]:line-clamp-1',
      props.class
    )"
    type="button"
    role="combobox"
    aria-haspopup="listbox"
    aria-expanded="false"
    aria-controls="radix-:R1m:"
  >
    <slot />
    <ChevronDown class="h-4 w-4 opacity-50" />
  </button>
</template>