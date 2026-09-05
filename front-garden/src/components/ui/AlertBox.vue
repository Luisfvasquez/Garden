<script setup lang="ts">
withDefaults(defineProps<{ kind?: 'info' | 'error' | 'success' }>(), { kind: 'info' })
</script>

<template>
  <div
    class="rounded-md border px-3 py-2 text-sm"
    :class="{
      'border-[var(--border-soft)] bg-[var(--surface-sunken)] text-[var(--text)]': kind === 'info',
      'border-[var(--color-wax-500)] bg-[color-mix(in_srgb,var(--color-wax-500)_12%,transparent)] text-[var(--text)]':
        kind === 'error',
      'border-[var(--color-sage-500)] bg-[color-mix(in_srgb,var(--color-sage-500)_14%,transparent)] text-[var(--text)]':
        kind === 'success',
    }"
    :role="kind === 'error' ? 'alert' : 'status'"
  >
    <slot />
  </div>
</template>
