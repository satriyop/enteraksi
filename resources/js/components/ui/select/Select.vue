<script setup lang="ts">
import { cn } from '@/lib/utils'
import { type HTMLAttributes } from 'vue'

const props = defineProps<{
  modelValue?: string
  defaultValue?: string
  disabled?: boolean
  class?: HTMLAttributes['class']
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const modelValue = defineModel<string>()

const handleSelect = (value: string) => {
  if (!props.disabled) {
    modelValue.value = value
    emits('update:modelValue', value)
  }
}
</script>

<template>
  <div :class="cn('relative', props.class)">
    <slot :value="modelValue" :on-select="handleSelect" />
  </div>
</template>