<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDollRequests } from '@/composables/useDollRequests'
import { useApiError } from '@/composables/useApiError'
import { formatDateTime } from '@/lib/datetime'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'

const { t } = useI18n()
const { messageFor } = useApiError()

const role = ref<'client' | 'doll' | undefined>(undefined)
const requests = useDollRequests(role, () => undefined)

const statusColor: Record<string, string> = {
  pending: 'bg-[var(--color-sepia-400)]/20',
  accepted: 'bg-[var(--color-sage-500)]/20',
  in_progress: 'bg-[var(--color-sage-500)]/20',
  awaiting_client: 'bg-[var(--color-sepia-400)]/20',
  completed: 'bg-[var(--color-sage-500)]/30',
  rejected: 'bg-[var(--border-soft)]',
  expired: 'bg-[var(--border-soft)]',
  cancelled: 'bg-[var(--border-soft)]',
}
</script>

<template>
  <AppLayout>
    <section class="flex flex-col gap-4">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl">{{ t('dolls.requests.title') }}</h1>
        <RouterLink :to="{ name: 'dolls' }" class="text-sm text-[var(--accent)] underline">
          {{ t('dolls.profile.backToDirectory') }}
        </RouterLink>
      </div>

      <div class="flex gap-2 text-sm">
        <button
          type="button"
          class="rounded px-2 py-1"
          :class="role === undefined ? 'bg-[var(--surface-sunken)]' : ''"
          @click="role = undefined"
        >
          {{ t('dolls.requests.roleAll') }}
        </button>
        <button
          type="button"
          class="rounded px-2 py-1"
          :class="role === 'client' ? 'bg-[var(--surface-sunken)]' : ''"
          @click="role = 'client'"
        >
          {{ t('dolls.requests.roleClient') }}
        </button>
        <button
          type="button"
          class="rounded px-2 py-1"
          :class="role === 'doll' ? 'bg-[var(--surface-sunken)]' : ''"
          @click="role = 'doll'"
        >
          {{ t('dolls.requests.roleDoll') }}
        </button>
      </div>

      <SpinnerDots v-if="requests.isPending.value" :label="t('common.loading')" />
      <AlertBox v-else-if="requests.isError.value" kind="error">{{ messageFor(requests.error.value) }}</AlertBox>
      <p v-else-if="!requests.data.value?.data.length" class="text-sm text-[var(--text-muted)]">
        {{ t('dolls.requests.empty') }}
      </p>

      <ul v-else class="flex flex-col gap-2">
        <li v-for="r in requests.data.value?.data" :key="r.id">
          <RouterLink
            :to="{ name: 'doll-request-detail', params: { id: r.id } }"
            class="flex flex-wrap items-center gap-3 rounded-md border border-[var(--border-soft)] p-3 text-sm hover:bg-[var(--surface-sunken)]"
          >
            <span class="rounded px-1.5 py-0.5 text-xs" :class="statusColor[r.status]">
              {{ t(`dolls.status.${r.status}`) }}
            </span>
            <span class="font-medium">{{ r.occasion }}</span>
            <span class="text-[var(--text-muted)]">
              {{ r.client?.display_name }} → {{ r.doll?.display_name }}
            </span>
            <span class="ml-auto text-xs text-[var(--text-muted)]">{{ formatDateTime(r.created_at) }}</span>
          </RouterLink>
        </li>
      </ul>
    </section>
  </AppLayout>
</template>
