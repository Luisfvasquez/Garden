<script setup lang="ts">
import { useId } from 'vue'

defineProps<{ label: string; description?: string; modelValue: boolean }>()
defineEmits<{ 'update:modelValue': [value: boolean] }>()
const id = useId()
</script>

<template>
  <div class="flex items-start justify-between gap-4">
    <label :for="id" class="text-sm">
      <span class="font-medium text-[var(--text)]">{{ label }}</span>
      <span v-if="description" class="block text-[var(--text-muted)]">{{ description }}</span>
    </label>
    <input
      :id="id"
      type="checkbox"
      :checked="modelValue"
      class="mt-1 size-4 shrink-0 accent-[var(--accent)]"
      @change="$emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
    />
  </div>
</template>
