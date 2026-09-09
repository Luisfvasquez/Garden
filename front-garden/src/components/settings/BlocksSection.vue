<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBlockUser, useBlocks, useUnblockUser } from '@/composables/useAccount'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import SettingsCard from './SettingsCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import AlertBox from '@/components/ui/AlertBox.vue'

const { t } = useI18n()
const ui = useUiStore()
const { messageFor } = useApiError()
const query = useBlocks()
const block = useBlockUser()
const unblock = useUnblockUser()

const handle = ref('')
const banner = ref('')

async function add() {
  banner.value = ''
  try {
    await block.mutateAsync({ postal_handle: handle.value.trim() })
    handle.value = ''
  } catch (error) {
    banner.value = messageFor(error)
  }
}

function remove(userId: string) {
  unblock.mutate(userId, { onError: (e) => ui.pushToast('error', messageFor(e)) })
}
</script>

<template>
  <SettingsCard :title="t('settings.blocks.title')" :description="t('settings.blocks.description')">
    <AlertBox v-if="banner" kind="error">{{ banner }}</AlertBox>

    <form class="flex items-end gap-2" @submit.prevent="add">
      <div class="flex-1">
        <BaseInput v-model="handle" :label="t('settings.blocks.handle')" />
      </div>
      <BaseButton type="submit" :loading="block.isPending.value">{{
        t('settings.blocks.add')
      }}</BaseButton>
    </form>

    <SpinnerDots v-if="query.isPending.value" />
    <p v-else-if="!query.data.value?.length" class="text-sm text-[var(--text-muted)]">
      {{ t('settings.blocks.empty') }}
    </p>
    <ul v-else class="flex flex-col divide-y divide-[var(--border-soft)]">
      <li
        v-for="b in query.data.value"
        :key="b.id"
        class="flex items-center justify-between py-2 text-sm"
      >
        <span>
          {{ b.user.display_name }}
          <span class="text-[var(--text-muted)]">· {{ b.user.postal_handle }}</span>
        </span>
        <button
          type="button"
          class="text-[var(--accent)] underline underline-offset-4"
          @click="remove(b.user.id)"
        >
          {{ t('settings.blocks.remove') }}
        </button>
      </li>
    </ul>
  </SettingsCard>
</template>
