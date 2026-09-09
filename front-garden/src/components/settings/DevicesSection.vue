<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useDevices, useRevokeDevice } from '@/composables/useAccount'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import { relativeTime } from '@/lib/datetime'
import SettingsCard from './SettingsCard.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'

const { t } = useI18n()
const ui = useUiStore()
const { messageFor } = useApiError()
const query = useDevices()
const revoke = useRevokeDevice()

function drop(id: string) {
  revoke.mutate(id, { onError: (e) => ui.pushToast('error', messageFor(e)) })
}
</script>

<template>
  <SettingsCard
    :title="t('settings.devices.title')"
    :description="t('settings.devices.description')"
  >
    <SpinnerDots v-if="query.isPending.value" />
    <p v-else-if="!query.data.value?.length" class="text-sm text-[var(--text-muted)]">
      {{ t('settings.devices.empty') }}
    </p>
    <ul v-else class="flex flex-col divide-y divide-[var(--border-soft)]">
      <li
        v-for="d in query.data.value"
        :key="d.id"
        class="flex items-center justify-between py-2 text-sm"
      >
        <span>
          {{ d.device_name }}
          <span v-if="d.current" class="text-[var(--color-sage-500)]"
            >· {{ t('settings.devices.current') }}</span
          >
          <span v-else-if="d.last_used_at" class="text-[var(--text-muted)]">
            · {{ relativeTime(d.last_used_at) }}
          </span>
        </span>
        <button
          v-if="!d.current"
          type="button"
          class="text-[var(--color-wax-500)] underline underline-offset-4"
          @click="drop(d.id)"
        >
          {{ t('settings.devices.revoke') }}
        </button>
      </li>
    </ul>
  </SettingsCard>
</template>
