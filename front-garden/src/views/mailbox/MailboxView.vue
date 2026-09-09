<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import type { MailboxFilter } from '@/api/mailbox'
import { useMailboxList } from '@/composables/useMailbox'
import { useApiError } from '@/composables/useApiError'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import EnvelopeClosed from '@/components/mailbox/EnvelopeClosed.vue'

const { t } = useI18n()
const { messageFor } = useApiError()

const filters: (MailboxFilter | undefined)[] = [undefined, 'unread', 'favorite', 'archived']
const active = ref<MailboxFilter | undefined>(undefined)
const query = useMailboxList(active)
</script>

<template>
  <AppLayout>
    <section class="flex flex-col gap-4">
      <h1 class="text-2xl">{{ t('nav.mailbox') }}</h1>

      <div class="flex flex-wrap gap-2">
        <button
          v-for="f in filters"
          :key="f ?? 'all'"
          type="button"
          class="rounded-full border px-3 py-1 text-sm"
          :class="
            active === f
              ? 'border-[var(--accent)] text-[var(--accent)]'
              : 'border-[var(--border-soft)] text-[var(--text-muted)]'
          "
          @click="active = f"
        >
          {{ t(`mailbox.filters.${f ?? 'all'}`) }}
        </button>
      </div>

      <SpinnerDots v-if="query.isPending.value" :label="t('common.loading')" />
      <AlertBox v-else-if="query.isError.value" kind="error">{{
        messageFor(query.error.value)
      }}</AlertBox>
      <p v-else-if="!query.data.value?.data.length" class="text-sm text-[var(--text-muted)]">
        {{ t('mailbox.empty') }}
      </p>

      <div v-else class="grid gap-3 sm:grid-cols-2">
        <RouterLink
          v-for="env in query.data.value?.data"
          :key="env.id"
          :to="{ name: 'mailbox-read', params: { id: env.id } }"
          class="focus-visible:outline-none"
        >
          <EnvelopeClosed :envelope="env" />
        </RouterLink>
      </div>
    </section>
  </AppLayout>
</template>
