<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ThemePreference, UserSettings } from '@/types/api'
import { useSettings, useUpdateSettings } from '@/composables/useAccount'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import SettingsCard from './SettingsCard.vue'
import SettingsToggle from './SettingsToggle.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import AlertBox from '@/components/ui/AlertBox.vue'

const { t } = useI18n()
const ui = useUiStore()
const { messageFor } = useApiError()
const query = useSettings()
const update = useUpdateSettings()

const toggles = [
  'notify_email',
  'notify_push',
  'notify_on_arrival',
  'notify_on_dispatch_confirm',
  'notify_on_doll_message',
  'notify_on_blog_comment',
] as const

const form = reactive({
  notify_email: true,
  notify_push: true,
  notify_on_arrival: true,
  notify_on_dispatch_confirm: false,
  notify_on_doll_message: true,
  notify_on_blog_comment: true,
  quiet_hours_start: '' as string,
  quiet_hours_end: '' as string,
  theme: 'system' as ThemePreference,
})
const banner = ref('')

watch(
  () => query.data.value,
  (s) => {
    if (!s) return
    for (const key of toggles) form[key] = s[key]
    form.quiet_hours_start = s.quiet_hours_start ?? ''
    form.quiet_hours_end = s.quiet_hours_end ?? ''
    form.theme = s.theme
  },
  { immediate: true },
)

async function save() {
  banner.value = ''
  const payload: Partial<UserSettings> = {
    ...Object.fromEntries(toggles.map((k) => [k, form[k]])),
    quiet_hours_start: form.quiet_hours_start || null,
    quiet_hours_end: form.quiet_hours_end || null,
    theme: form.theme,
  }
  try {
    await update.mutateAsync(payload)
    ui.setTheme(form.theme)
    ui.pushToast('success', t('settings.saved'))
  } catch (error) {
    banner.value = messageFor(error)
  }
}
</script>

<template>
  <SettingsCard :title="t('settings.notifications.title')">
    <SpinnerDots v-if="query.isPending.value" />
    <template v-else>
      <AlertBox v-if="banner" kind="error">{{ banner }}</AlertBox>
      <SettingsToggle
        v-for="key in toggles"
        :key="key"
        v-model="form[key]"
        :label="t(`settings.notifications.${key}`)"
      />

      <div class="grid grid-cols-2 gap-3">
        <BaseInput
          v-model="form.quiet_hours_start"
          type="time"
          :label="t('settings.notifications.quietStart')"
        />
        <BaseInput
          v-model="form.quiet_hours_end"
          type="time"
          :label="t('settings.notifications.quietEnd')"
        />
      </div>

      <label class="flex flex-col gap-1 text-sm">
        <span class="font-medium">{{ t('settings.notifications.theme') }}</span>
        <select
          v-model="form.theme"
          class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5 text-sm text-[var(--text)]"
        >
          <option value="system">{{ t('settings.notifications.themeSystem') }}</option>
          <option value="light">{{ t('common.theme.light') }}</option>
          <option value="dark">{{ t('common.theme.dark') }}</option>
        </select>
      </label>

      <BaseButton :loading="update.isPending.value" @click="save">{{
        t('settings.save')
      }}</BaseButton>
    </template>
  </SettingsCard>
</template>
