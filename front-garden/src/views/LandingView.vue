<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import LocaleSwitch from '@/components/ui/LocaleSwitch.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'

const { t } = useI18n()
const auth = useAuthStore()
</script>

<template>
  <div class="flex min-h-dvh flex-col bg-[var(--surface)]">
    <header class="flex items-center justify-between p-4">
      <span class="font-serif text-lg font-semibold text-[var(--text)]">{{ t('app.name') }}</span>
      <div class="flex items-center gap-2">
        <LocaleSwitch />
        <ThemeToggle />
      </div>
    </header>

    <main class="mx-auto flex max-w-2xl flex-1 flex-col justify-center gap-6 p-6">
      <h1 class="text-3xl leading-tight sm:text-4xl">{{ t('landing.title') }}</h1>
      <p class="text-lg text-[var(--text-muted)]">{{ t('landing.body') }}</p>

      <div class="flex flex-wrap gap-3">
        <RouterLink
          v-if="auth.isAuthenticated"
          :to="{ name: 'desk' }"
          class="rounded-md bg-[var(--accent)] px-4 py-2 text-sm font-medium text-[var(--accent-contrast)] hover:brightness-110"
        >
          {{ t('nav.desk') }}
        </RouterLink>
        <template v-else>
          <RouterLink
            :to="{ name: 'login' }"
            class="rounded-md bg-[var(--accent)] px-4 py-2 text-sm font-medium text-[var(--accent-contrast)] hover:brightness-110"
          >
            {{ t('landing.signIn') }}
          </RouterLink>
          <RouterLink
            :to="{ name: 'register' }"
            class="rounded-md border border-[var(--border-soft)] px-4 py-2 text-sm font-medium text-[var(--text)] hover:bg-[var(--surface-sunken)]"
          >
            {{ t('landing.createAccount') }}
          </RouterLink>
        </template>
      </div>
    </main>
  </div>
</template>
