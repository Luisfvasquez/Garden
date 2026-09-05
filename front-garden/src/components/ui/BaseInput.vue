<script setup lang="ts">
import { useId } from 'vue'

const props = withDefaults(
  defineProps<{
    label: string
    type?: string
    modelValue: string
    error?: string
    hint?: string
    autocomplete?: string
    required?: boolean
    disabled?: boolean
  }>(),
  { type: 'text', required: false, disabled: false },
)

defineEmits<{ 'update:modelValue': [value: string] }>()

const id = useId()
const describedBy = () =>
  [props.error ? `${id}-err` : null, props.hint ? `${id}-hint` : null].filter(Boolean).join(' ') ||
  undefined
</script>

<template>
  <div class="flex flex-col gap-1">
    <label :for="id" class="text-sm font-medium text-[var(--text)]">
      {{ label
      }}<span v-if="required" aria-hidden="true" class="text-[var(--color-wax-500)]"> *</span>
    </label>
    <input
      :id="id"
      :type="type"
      :value="modelValue"
      :autocomplete="autocomplete"
      :required="required"
      :disabled="disabled"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="describedBy()"
      class="rounded-md border bg-[var(--surface-raised)] px-3 py-2 text-sm text-[var(--text)] transition-colors focus:outline-none"
      :class="error ? 'border-[var(--color-wax-500)]' : 'border-[var(--border-soft)]'"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <p v-if="hint && !error" :id="`${id}-hint`" class="text-xs text-[var(--text-muted)]">
      {{ hint }}
    </p>
    <p v-if="error" :id="`${id}-err`" class="text-xs text-[var(--color-wax-500)]">{{ error }}</p>
  </div>
</template>
