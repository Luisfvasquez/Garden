<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSettings, useUpdateSettings } from '@/composables/useAccount'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import SettingsCard from './SettingsCard.vue'
import SettingsToggle from './SettingsToggle.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import AlertBox from '@/components/ui/AlertBox.vue'

const { t } = useI18n()
const ui = useUiStore()
const { messageFor } = useApiError()
const query = useSettings()
const update = useUpdateSettings()

const form = reactive({
  accepts_random_letters: false,
  random_letters_daily_cap: 3,
  share_read_receipts: false,
  show_transit_countdown: true,
})
const banner = ref('')

watch(
  () => query.data.value,
  (s) => {
    if (!s) return
    form.accepts_random_letters = s.accepts_random_letters
    form.random_letters_daily_cap = s.random_letters_daily_cap
    form.share_read_receipts = s.share_read_receipts
    form.show_transit_countdown = s.show_transit_countdown
  },
  { immediate: true },
)

async function save() {
  banner.value = ''
  try {
    await update.mutateAsync({ ...form })
    ui.pushToast('success', t('settings.saved'))
  } catch (error) {
    banner.value = messageFor(error)
  }
}
</script>

<template>
  <SettingsCard
    :title="t('settings.privacy.title')"
    :description="t('settings.privacy.description')"
  >
    <SpinnerDots v-if="query.isPending.value" />
    <template v-else>
      <AlertBox v-if="banner" kind="error">{{ banner }}</AlertBox>

      <SettingsToggle
        v-model="form.accepts_random_letters"
        :label="t('settings.privacy.acceptsRandom')"
        :description="t('settings.privacy.acceptsRandomHint')"
      />
      <label v-if="form.accepts_random_letters" class="flex items-center gap-2 text-sm">
        <span>{{ t('settings.privacy.dailyCap') }}</span>
        <input
          v-model.number="form.random_letters_daily_cap"
          type="number"
          min="0"
          max="10"
          class="w-16 rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1"
        />
      </label>

      <SettingsToggle
        v-model="form.share_read_receipts"
        :label="t('settings.privacy.readReceipts')"
        :description="t('settings.privacy.readReceiptsHint')"
      />
      <SettingsToggle
        v-model="form.show_transit_countdown"
        :label="t('settings.privacy.transitCountdown')"
        :description="t('settings.privacy.transitCountdownHint')"
      />

      <BaseButton :loading="update.isPending.value" @click="save">{{
        t('settings.save')
      }}</BaseButton>
    </template>
  </SettingsCard>
</template>
