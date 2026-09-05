<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
const { toasts } = storeToRefs(ui)
</script>

<template>
  <Teleport to="body">
    <div
      class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 p-4"
    >
      <TransitionGroup name="toast">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="pointer-events-auto flex max-w-sm items-start gap-3 rounded-md border px-3 py-2 text-sm shadow-lg"
          :class="{
            'border-[var(--border-soft)] bg-[var(--surface-raised)] text-[var(--text)]':
              toast.kind === 'info',
            'border-[var(--color-wax-500)] bg-[var(--surface-raised)] text-[var(--text)]':
              toast.kind === 'error',
            'border-[var(--color-sage-500)] bg-[var(--surface-raised)] text-[var(--text)]':
              toast.kind === 'success',
          }"
          role="status"
        >
          <span class="flex-1">{{ toast.text }}</span>
          <button
            class="text-[var(--text-muted)] hover:text-[var(--text)]"
            aria-label="×"
            @click="ui.dismissToast(toast.id)"
          >
            ×
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition:
    opacity 0.3s var(--ease-calm, ease),
    transform 0.3s var(--ease-calm, ease);
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
</style>
