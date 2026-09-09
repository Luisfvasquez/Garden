<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import type { DeliveryStatus } from '@/types/api'
import { useDeliveryList } from '@/composables/useDeliveries'
import { useApiError } from '@/composables/useApiError'
import { relativeTime } from '@/lib/datetime'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'

const { t } = useI18n()
const { messageFor } = useApiError()

const filters: (DeliveryStatus | undefined)[] = [undefined, 'queued', 'in_transit', 'delivered']
const active = ref<DeliveryStatus | undefined>(undefined)
const query = useDeliveryList(active)

const badgeClass: Record<string, string> = {
  queued: 'bg-[var(--color-sage-500)]/20',
  in_transit: 'bg-[var(--color-violet-500)]/20',
  delivered: 'bg-[var(--color-sage-500)]/30',
  read: 'bg-[var(--color-sage-500)]/30',
  cancelled: 'bg-[var(--border-soft)]',
  failed: 'bg-[var(--color-wax-500)]/20',
}
</script>

<template>
  <AppLayout>
    <section class="flex flex-col gap-4">
      <h1 class="text-2xl">{{ t('outbox.title') }}</h1>

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
          {{ t(`outbox.filters.${f ?? 'all'}`) }}
        </button>
      </div>

      <SpinnerDots v-if="query.isPending.value" :label="t('common.loading')" />
      <AlertBox v-else-if="query.isError.value" kind="error">{{
        messageFor(query.error.value)
      }}</AlertBox>
      <p v-else-if="!query.data.value?.data.length" class="text-sm text-[var(--text-muted)]">
        {{ t('outbox.empty') }}
      </p>

      <ul v-else class="flex flex-col gap-2">
        <li v-for="d in query.data.value?.data" :key="d.id">
          <RouterLink
            :to="{ name: 'delivery-tracking', params: { id: d.id } }"
            class="flex items-center gap-3 rounded-md border border-[var(--border-soft)] p-3 text-sm hover:bg-[var(--surface-sunken)]"
          >
            <span class="rounded px-1.5 py-0.5 text-xs" :class="badgeClass[d.status]">
              {{ t(`delivery.status.${d.status}`) }}
            </span>
            <span class="font-medium">
              {{ d.is_anonymous ? t('outbox.anonymous') : (d.recipient?.display_name ?? '—') }}
            </span>
            <span class="ml-auto text-xs text-[var(--text-muted)]">
              {{ d.estimated_delivery_at ? relativeTime(d.estimated_delivery_at) : '' }}
            </span>
          </RouterLink>
        </li>
      </ul>
    </section>
  </AppLayout>
</template>
