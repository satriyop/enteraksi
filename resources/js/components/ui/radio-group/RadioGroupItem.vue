<script setup lang="ts">
import { cn } from '@/lib/utils'
import { Circle } from 'lucide-vue-next'
import { type HTMLAttributes, computed } from 'vue'

const props = defineProps<{
  value: string
  id?: string
  disabled?: boolean
  class?: HTMLAttributes['class']
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const modelValue = defineModel<string>()

const isChecked = computed(() => {
  return modelValue.value === props.value
})

const handleChange = () => {
  if (!props.disabled) {
    modelValue.value = props.value
    emits('update:modelValue', props.value)
  }
}
</script>

<template>
  <div :class="cn('flex items-center space-x-2', props.class)">
    <button
      :id="props.id"
      type="button"
      role="radio"
      :aria-checked="isChecked"
      :disabled="props.disabled"
      :class="cn(
        'flex size-4 items-center justify-center rounded-full border border-primary text-primary ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
        isChecked ? 'bg-primary text-primary-foreground' : 'bg-background',
      )"
      @click="handleChange"
    >
      <Circle v-if="isChecked" class="size-2 fill-current" />
    </button>
    <slot />
  </div>
</template>