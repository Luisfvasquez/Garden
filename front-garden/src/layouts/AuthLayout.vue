<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import LocaleSwitch from '@/components/ui/LocaleSwitch.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'

defineProps<{ title: string }>()
const { t } = useI18n()
</script>

<template>
  <div class="flex min-h-dvh flex-col bg-[var(--surface)]">
    <header class="flex items-center justify-between p-4">
      <RouterLink
        :to="{ name: 'landing' }"
        class="font-serif text-lg font-semibold text-[var(--text)]"
      >
        {{ t('app.name') }}
      </RouterLink>
      <div class="flex items-center gap-2">
        <LocaleSwitch />
        <ThemeToggle />
      </div>
    </header>

    <main class="flex flex-1 items-center justify-center p-4">
      <div class="w-full max-w-sm">
        <div
          class="rounded-lg border border-[var(--border-soft)] bg-[var(--surface-raised)] p-6 shadow-sm"
        >
          <h1 class="mb-1 text-xl">{{ title }}</h1>
          <p class="mb-5 text-sm text-[var(--text-muted)]">{{ t('app.tagline') }}</p>
          <slot />
        </div>
        <div class="mt-4 text-center text-sm text-[var(--text-muted)]">
          <slot name="footer" />
        </div>
      </div>
    </main>
  </div>
</template>
