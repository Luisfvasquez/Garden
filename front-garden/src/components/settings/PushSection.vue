<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePush } from '@/composables/usePush'
import SettingsCard from './SettingsCard.vue'
import SettingsToggle from './SettingsToggle.vue'
import AlertBox from '@/components/ui/AlertBox.vue'

const { t } = useI18n()
const push = usePush()

onMounted(() => push.refresh())

async function onToggle(value: boolean) {
  if (value) await push.enable()
  else await push.disable()
}
</script>

<template>
  <SettingsCard :title="t('settings.push.title')" :description="t('settings.push.description')">
    <p v-if="!push.supported" class="text-sm text-[var(--text-muted)]">
      {{ t('settings.push.unsupported') }}
    </p>

    <template v-else>
      <SettingsToggle
        :model-value="push.subscribed.value"
        :label="t('settings.push.enable')"
        :description="t('settings.push.enableHint')"
        @update:model-value="onToggle"
      />
      <p v-if="push.busy.value" class="text-sm text-[var(--text-muted)]">{{ t('common.loading') }}</p>
      <AlertBox v-if="push.error.value" kind="error">
        {{ t(`settings.push.error.${push.error.value}`, t('settings.push.error.unknown')) }}
      </AlertBox>
    </template>
  </SettingsCard>
</template>
