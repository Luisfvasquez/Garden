<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useApiError } from '@/composables/useApiError'
import { useLetterList } from '@/composables/useLetters'
import { useUnreadCount } from '@/composables/useMailbox'
import { relativeTime } from '@/lib/datetime'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'

const { t } = useI18n()
const auth = useAuthStore()
const { messageFor } = useApiError()

const drafts = useLetterList('draft')
const sent = useLetterList('sent')
const unread = useUnreadCount()
</script>

<template>
  <AppLayout>
    <section class="flex flex-col gap-6">
      <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-2xl">{{ t('nav.desk') }}</h1>
        <RouterLink
          :to="{ name: 'letter-new' }"
          class="ml-auto rounded-md bg-[var(--accent)] px-4 py-2 text-sm font-medium text-[var(--accent-contrast)] hover:brightness-110"
        >
          {{ t('desk.writeLetter') }}
        </RouterLink>
      </div>

      <AlertBox v-if="auth.user && !auth.isVerified" kind="info">
        {{ t('auth.verify.pending') }}
        <RouterLink :to="{ name: 'verify-email' }" class="ml-1 text-[var(--accent)] underline">
          {{ t('auth.verify.title') }}
        </RouterLink>
      </AlertBox>

      <div class="grid gap-6 md:grid-cols-3">
        <!-- Drafts -->
        <div class="flex flex-col gap-2">
          <h2 class="text-sm font-semibold text-[var(--text-muted)]">{{ t('desk.drafts') }}</h2>
          <SpinnerDots v-if="drafts.isPending.value" />
          <AlertBox v-else-if="drafts.isError.value" kind="error">{{
            messageFor(drafts.error.value)
          }}</AlertBox>
          <p v-else-if="!drafts.data.value?.data.length" class="text-sm text-[var(--text-muted)]">
            {{ t('desk.noDrafts') }}
          </p>
          <RouterLink
            v-for="letter in drafts.data.value?.data"
            :key="letter.id"
            :to="{ name: 'letter-edit', params: { id: letter.id } }"
            class="rounded-md border border-[var(--border-soft)] p-3 text-sm hover:bg-[var(--surface-sunken)]"
          >
            <span class="block font-medium">{{ letter.title || t('editor.untitled') }}</span>
            <span class="text-xs text-[var(--text-muted)]">{{
              relativeTime(letter.updated_at)
            }}</span>
          </RouterLink>
        </div>

        <!-- In transit / sent -->
        <div class="flex flex-col gap-2">
          <h2 class="text-sm font-semibold text-[var(--text-muted)]">{{ t('desk.sent') }}</h2>
          <SpinnerDots v-if="sent.isPending.value" />
          <p v-else-if="!sent.data.value?.data.length" class="text-sm text-[var(--text-muted)]">
            {{ t('desk.noSent') }}
          </p>
          <div
            v-for="letter in sent.data.value?.data"
            :key="letter.id"
            class="rounded-md border border-[var(--border-soft)] p-3 text-sm"
          >
            <span class="block font-medium">{{ letter.title || t('editor.untitled') }}</span>
            <span class="text-xs text-[var(--text-muted)]">
              {{ t('desk.recipients', { count: letter.deliveries_count ?? 0 }) }}
            </span>
          </div>
          <RouterLink :to="{ name: 'outbox' }" class="mt-1 text-sm text-[var(--accent)] underline">
            {{ t('desk.viewOutbox') }}
          </RouterLink>
        </div>

        <!-- Mailbox -->
        <div class="flex flex-col gap-2">
          <h2 class="text-sm font-semibold text-[var(--text-muted)]">{{ t('desk.mailbox') }}</h2>
          <RouterLink
            :to="{ name: 'mailbox' }"
            class="rounded-md border border-[var(--border-soft)] p-4 text-center hover:bg-[var(--surface-sunken)]"
          >
            <span class="block text-3xl font-serif">{{ unread.data.value ?? 0 }}</span>
            <span class="text-xs text-[var(--text-muted)]">{{ t('desk.unreadLetters') }}</span>
          </RouterLink>
        </div>
      </div>
    </section>
  </AppLayout>
</template>
