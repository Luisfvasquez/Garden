<script setup lang="ts">
import SpinnerDots from './SpinnerDots.vue'

withDefaults(
  defineProps<{
    variant?: 'primary' | 'ghost' | 'link'
    type?: 'button' | 'submit'
    loading?: boolean
    disabled?: boolean
    block?: boolean
  }>(),
  { variant: 'primary', type: 'button', loading: false, disabled: false, block: false },
)
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :aria-busy="loading"
    class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors duration-200 disabled:cursor-not-allowed disabled:opacity-60"
    :class="{
      'w-full': block,
      'bg-[var(--accent)] text-[var(--accent-contrast)] hover:brightness-110':
        variant === 'primary',
      'border border-[var(--border-soft)] text-[var(--text)] hover:bg-[var(--surface-sunken)]':
        variant === 'ghost',
      'text-[var(--accent)] underline underline-offset-4 hover:brightness-110 px-0 py-0':
        variant === 'link',
    }"
  >
    <SpinnerDots v-if="loading" />
    <slot />
  </button>
</template>
