<script setup lang="ts">
import { useId } from 'vue'

defineProps<{ label: string; modelValue: boolean; error?: string }>()
defineEmits<{ 'update:modelValue': [value: boolean] }>()

const id = useId()
</script>

<template>
  <div class="flex flex-col gap-1">
    <label :for="id" class="flex items-start gap-2 text-sm text-[var(--text)]">
      <input
        :id="id"
        type="checkbox"
        :checked="modelValue"
        class="mt-0.5 size-4 rounded border-[var(--border-soft)] accent-[var(--accent)]"
        @change="$emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
      />
      <span>{{ label }}</span>
    </label>
    <p v-if="error" class="text-xs text-[var(--color-wax-500)]">{{ error }}</p>
  </div>
</template>
