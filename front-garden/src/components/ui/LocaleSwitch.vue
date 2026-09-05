<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { setLocale, SUPPORTED_LOCALES } from '@/i18n'
import type { Locale } from '@/types/api'

const { locale, t } = useI18n()

function pick(event: Event) {
  setLocale((event.target as HTMLSelectElement).value as Locale)
}
</script>

<template>
  <label class="inline-flex items-center gap-1 text-xs text-[var(--text-muted)]">
    <span class="sr-only">{{ t('common.language') }}</span>
    <select
      :value="locale"
      class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1 text-[var(--text)]"
      @change="pick"
    >
      <option v-for="code in SUPPORTED_LOCALES" :key="code" :value="code">
        {{ code.toUpperCase() }}
      </option>
    </select>
  </label>
</template>
