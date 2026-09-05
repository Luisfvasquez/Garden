<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

function reload() {
  window.location.reload()
}
</script>

<template>
  <main
    class="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center gap-4 p-6 text-center"
  >
    <h1 class="text-2xl">{{ t('upgrade.title') }}</h1>
    <p class="text-[var(--text-muted)]">{{ t('upgrade.body') }}</p>
    <button
      type="button"
      class="rounded-md bg-[var(--accent)] px-4 py-2 text-sm font-medium text-[var(--accent-contrast)] hover:brightness-110"
      @click="reload"
    >
      {{ t('common.retry') }}
    </button>
  </main>
</template>
