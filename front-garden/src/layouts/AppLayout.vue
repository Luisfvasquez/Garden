<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useQueryClient } from '@tanstack/vue-query'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useApiError } from '@/composables/useApiError'
import { useUnreadCount } from '@/composables/useMailbox'
import { useFeature } from '@/composables/useFeatures'
import LocaleSwitch from '@/components/ui/LocaleSwitch.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'

const auth = useAuthStore()
const ui = useUiStore()
const router = useRouter()
const queryClient = useQueryClient()
const { t } = useI18n()
const { messageFor } = useApiError()
const unread = useUnreadCount()
const schedulesEnabled = useFeature('schedules')

const signingOut = ref(false)

async function signOut() {
  signingOut.value = true
  try {
    await auth.logout()
    queryClient.clear()
    await router.push({ name: 'login' })
  } catch (error) {
    ui.pushToast('error', messageFor(error))
  } finally {
    signingOut.value = false
  }
}
</script>

<template>
  <div class="flex min-h-dvh flex-col bg-[var(--surface)]">
    <header class="border-b border-[var(--border-soft)]">
      <nav class="mx-auto flex max-w-4xl items-center gap-4 p-4">
        <RouterLink
          :to="{ name: 'desk' }"
          class="font-serif text-lg font-semibold text-[var(--text)]"
        >
          {{ t('app.name') }}
        </RouterLink>
        <RouterLink
          :to="{ name: 'desk' }"
          class="text-sm text-[var(--text-muted)] hover:text-[var(--text)]"
          active-class="text-[var(--text)]"
        >
          {{ t('nav.desk') }}
        </RouterLink>
        <RouterLink
          :to="{ name: 'mailbox' }"
          class="flex items-center gap-1 text-sm text-[var(--text-muted)] hover:text-[var(--text)]"
          active-class="text-[var(--text)]"
        >
          {{ t('nav.mailbox') }}
          <span
            v-if="unread.data.value"
            class="rounded-full bg-[var(--accent)] px-1.5 text-xs text-[var(--accent-contrast)]"
          >
            {{ unread.data.value }}
          </span>
        </RouterLink>
        <RouterLink
          v-if="schedulesEnabled"
          :to="{ name: 'schedules' }"
          class="text-sm text-[var(--text-muted)] hover:text-[var(--text)]"
          active-class="text-[var(--text)]"
        >
          {{ t('nav.schedules') }}
        </RouterLink>
        <RouterLink
          :to="{ name: 'settings' }"
          class="text-sm text-[var(--text-muted)] hover:text-[var(--text)]"
          active-class="text-[var(--text)]"
        >
          {{ t('nav.settings') }}
        </RouterLink>

        <div class="ml-auto flex items-center gap-2">
          <LocaleSwitch />
          <ThemeToggle />
          <span class="hidden text-sm text-[var(--text-muted)] sm:inline">{{
            auth.user?.name
          }}</span>
          <button
            type="button"
            class="rounded-md border border-[var(--border-soft)] px-3 py-1.5 text-sm text-[var(--text)] transition-colors hover:bg-[var(--surface-sunken)] disabled:opacity-60"
            :disabled="signingOut"
            @click="signOut"
          >
            {{ t('nav.signOut') }}
          </button>
        </div>
      </nav>
    </header>

    <main class="mx-auto w-full max-w-4xl flex-1 p-4">
      <slot />
    </main>
  </div>
</template>
