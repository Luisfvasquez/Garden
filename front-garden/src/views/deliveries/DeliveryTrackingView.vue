<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCancelDelivery, useDelivery, useTracking } from '@/composables/useDeliveries'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import { formatDateTime, relativeTime } from '@/lib/datetime'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const route = useRoute()
const { t } = useI18n()
const ui = useUiStore()
const { messageFor } = useApiError()

const id = computed(() => route.params.id as string)
const delivery = useDelivery(id)
const tracking = useTracking(id)
const cancel = useCancelDelivery()

function doCancel() {
  cancel.mutate(id.value, {
    onSuccess: () => ui.pushToast('success', t('tracking.cancelled')),
    onError: (e) => ui.pushToast('error', messageFor(e)),
  })
}
</script>

<template>
  <AppLayout>
    <div v-if="delivery.isPending.value" class="flex justify-center py-16">
      <SpinnerDots :label="t('common.loading')" />
    </div>

    <AlertBox v-else-if="delivery.isError.value" kind="error">
      {{ messageFor(delivery.error.value) }}
    </AlertBox>

    <section v-else-if="delivery.data.value" class="mx-auto flex max-w-lg flex-col gap-5">
      <header class="flex flex-col gap-1">
        <h1 class="text-xl">{{ t('tracking.title') }}</h1>
        <p class="text-sm text-[var(--text-muted)]">
          {{
            delivery.data.value.is_anonymous
              ? t('outbox.anonymous')
              : (delivery.data.value.recipient?.display_name ?? '—')
          }}
          · {{ t(`delivery.status.${delivery.data.value.status}`) }}
        </p>
        <p
          v-if="delivery.data.value.estimated_delivery_at"
          class="text-sm text-[var(--text-muted)]"
        >
          {{ t('tracking.eta', { when: relativeTime(delivery.data.value.estimated_delivery_at) }) }}
        </p>
      </header>

      <BaseButton
        v-if="delivery.data.value.can_cancel"
        variant="ghost"
        :loading="cancel.isPending.value"
        @click="doCancel"
      >
        {{ t('tracking.cancel') }}
      </BaseButton>

      <ol class="flex flex-col gap-4 border-l border-[var(--border-soft)] pl-4">
        <li v-for="(event, i) in tracking.data.value ?? []" :key="i" class="relative">
          <span
            class="absolute -left-[21px] top-1 size-2.5 rounded-full bg-[var(--accent)]"
            aria-hidden="true"
          />
          <p class="text-sm">{{ event.label }}</p>
          <p class="text-xs text-[var(--text-muted)]">{{ formatDateTime(event.occurred_at) }}</p>
        </li>
      </ol>
      <p v-if="tracking.isPending.value" class="text-sm text-[var(--text-muted)]">
        {{ t('common.loading') }}
      </p>
    </section>
  </AppLayout>
</template>
