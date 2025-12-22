<script setup lang="ts">
import { cn } from '@/lib/utils'
import { Check } from 'lucide-vue-next'
import { type HTMLAttributes } from 'vue'

const props = defineProps<{
  value: string
  disabled?: boolean
  class?: HTMLAttributes['class']
}>()

const emits = defineEmits<{
  (e: 'select', value: string): void
}>()

const handleClick = () => {
  if (!props.disabled) {
    emits('select', props.value)
  }
}
</script>

<template>
  <div
    :class="cn(
      'relative flex cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
      props.class
    )"
    role="option"
    :aria-selected="false"
    :data-disabled="props.disabled"
    @click="handleClick"
  >
    <span class="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">
      <Check v-if="false" class="h-4 w-4" />
    </span>
    <slot />
  </div>
</template>