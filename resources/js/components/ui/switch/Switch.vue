<script setup lang="ts">
import { cn } from '@/lib/utils'
import { type HTMLAttributes, computed } from 'vue'

const props = defineProps<{
  modelValue?: boolean
  disabled?: boolean
  class?: HTMLAttributes['class']
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
}>()

const modelValue = defineModel<boolean>()

const handleClick = () => {
  if (!props.disabled) {
    modelValue.value = !modelValue.value
    emits('update:modelValue', modelValue.value)
  }
}
</script>

<template>
  <button
    type="button"
    role="switch"
    :aria-checked="modelValue"
    :disabled="props.disabled"
    :class="cn(
      'peer inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input',
      props.class
    )"
    :data-state="modelValue ? 'checked' : 'unchecked'"
    @click="handleClick"
  >
    <span
      :class="cn(
        'pointer-events-none block h-5 w-5 rounded-full bg-background shadow-lg ring-0 transition-transform data-[state=checked]:translate-x-5 data-[state=unchecked]:translate-x-0',
        props.class
      )"
    />
  </button>
</template>