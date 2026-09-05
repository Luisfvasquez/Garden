<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
const { t } = useI18n()
</script>

<template>
  <button
    type="button"
    class="rounded-md border border-[var(--border-soft)] p-2 text-[var(--text)] transition-colors hover:bg-[var(--surface-sunken)]"
    :aria-label="t('common.theme.toggle')"
    @click="ui.toggleTheme()"
  >
    <svg
      width="16"
      height="16"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      aria-hidden="true"
    >
      <circle cx="12" cy="12" r="4" />
      <path
        d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"
      />
    </svg>
  </button>
</template>
