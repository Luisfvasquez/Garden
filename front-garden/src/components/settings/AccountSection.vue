<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useQueryClient } from '@tanstack/vue-query'
import { accountApi } from '@/api/account'
import { useRotateHandle } from '@/composables/useAccount'
import { useAuthStore } from '@/stores/auth'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import SettingsCard from './SettingsCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AlertBox from '@/components/ui/AlertBox.vue'

const { t } = useI18n()
const router = useRouter()
const qc = useQueryClient()
const auth = useAuthStore()
const ui = useUiStore()
const { messageFor } = useApiError()
const rotate = useRotateHandle()

const banner = ref('')
const busy = ref(false)

async function rotateHandle() {
  banner.value = ''
  try {
    await rotate.mutateAsync()
    ui.pushToast('success', t('settings.account.handleRotated'))
  } catch (error) {
    banner.value = messageFor(error)
  }
}

async function leave(action: 'deactivate' | 'destroy') {
  if (!window.confirm(t(`settings.account.${action}Confirm`))) return
  busy.value = true
  try {
    if (action === 'deactivate') await accountApi.deactivate()
    else await accountApi.destroy()
    auth.clear()
    qc.clear()
    await router.push({ name: 'login' })
  } catch (error) {
    ui.pushToast('error', messageFor(error))
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <SettingsCard :title="t('settings.account.title')">
    <AlertBox v-if="banner" kind="error">{{ banner }}</AlertBox>

    <div class="flex items-center justify-between gap-4 text-sm">
      <span>
        {{ t('settings.account.handle') }}:
        <code>{{ auth.user?.postal_handle }}</code>
      </span>
      <BaseButton variant="ghost" :loading="rotate.isPending.value" @click="rotateHandle">
        {{ t('settings.account.rotateHandle') }}
      </BaseButton>
    </div>
    <p class="text-xs text-[var(--text-muted)]">{{ t('settings.account.rotateHint') }}</p>

    <div class="mt-4 rounded-md border border-[var(--color-wax-500)] p-4">
      <p class="text-sm font-medium">{{ t('settings.account.dangerZone') }}</p>
      <div class="mt-3 flex flex-wrap gap-3">
        <button
          type="button"
          class="text-sm text-[var(--color-wax-500)] underline underline-offset-4 disabled:opacity-60"
          :disabled="busy"
          @click="leave('deactivate')"
        >
          {{ t('settings.account.deactivate') }}
        </button>
        <button
          type="button"
          class="text-sm text-[var(--color-wax-500)] underline underline-offset-4 disabled:opacity-60"
          :disabled="busy"
          @click="leave('destroy')"
        >
          {{ t('settings.account.destroy') }}
        </button>
      </div>
    </div>
  </SettingsCard>
</template>
