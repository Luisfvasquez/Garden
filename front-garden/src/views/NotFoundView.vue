<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <main
    class="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center gap-4 p-6 text-center"
  >
    <h1 class="text-2xl">{{ t('notFound.title') }}</h1>
    <p class="text-[var(--text-muted)]">{{ t('notFound.body') }}</p>
    <RouterLink
      :to="{ name: 'landing' }"
      class="rounded-md border border-[var(--border-soft)] px-4 py-2 text-sm text-[var(--text)] hover:bg-[var(--surface-sunken)]"
    >
      {{ t('notFound.home') }}
    </RouterLink>
  </main>
</template>
