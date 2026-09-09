<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLetterList } from '@/composables/useLetters'
import { useRandomQuota, useSendRandom } from '@/composables/useRandom'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import type { TransitTier } from '@/types/api'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const { t } = useI18n()
const { messageFor } = useApiError()
const ui = useUiStore()

const drafts = useLetterList('draft')
const quota = useRandomQuota()
const send = useSendRandom()

const letterId = ref('')
const tier = ref<TransitTier>('standard')
const message = ref('')
const banner = ref('')
const held = ref(false)
const idempotencyKey = crypto.randomUUID()

const canSend = computed(
  () => Boolean(letterId.value) && quota.data.value?.eligible === true && !send.isPending.value,
)

async function submit() {
  banner.value = ''
  held.value = false
  try {
    const result = await send.mutateAsync({
      letterId: letterId.value,
      tier: tier.value,
      message: message.value.trim() || null,
      key: idempotencyKey,
    })
    if (result.held_for_review) {
      held.value = true
    } else {
      ui.pushToast('success', t('bottle.sent'))
    }
    letterId.value = ''
    message.value = ''
  } catch (error) {
    banner.value = messageFor(error)
  }
}
</script>

<template>
  <AppLayout>
    <section class="mx-auto flex max-w-xl flex-col gap-4">
      <h1 class="text-2xl">{{ t('bottle.title') }}</h1>
      <p class="text-sm text-[var(--text-muted)]">{{ t('bottle.intro') }}</p>

      <SpinnerDots v-if="quota.isPending.value" :label="t('common.loading')" />

      <template v-else-if="quota.data.value">
        <div class="rounded-md border border-[var(--border-soft)] p-3 text-sm">
          {{ t('bottle.quota', {
            daily: quota.data.value.daily_remaining,
            dailyLimit: quota.data.value.daily_limit,
            weekly: quota.data.value.weekly_remaining,
          }) }}
        </div>

        <AlertBox v-if="!quota.data.value.eligible" kind="info">
          <ul class="list-disc pl-4">
            <li v-for="r in quota.data.value.reasons" :key="r">{{ t(`bottle.reason.${r}`) }}</li>
          </ul>
        </AlertBox>

        <AlertBox v-if="held" kind="info">{{ t('bottle.held') }}</AlertBox>
        <AlertBox v-if="banner" kind="error">{{ banner }}</AlertBox>

        <form class="flex flex-col gap-3" @submit.prevent="submit">
          <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium">{{ t('bottle.pickLetter') }}</span>
            <select v-model="letterId" class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5">
              <option value="">{{ t('bottle.pickLetterPlaceholder') }}</option>
              <option v-for="l in drafts.data.value?.data ?? []" :key="l.id" :value="l.id">
                {{ l.title || t('bottle.untitled') }}
              </option>
            </select>
          </label>

          <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium">{{ t('bottle.tier') }}</span>
            <select v-model="tier" class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5">
              <option value="express">{{ t('schedules.tier.express') }}</option>
              <option value="standard">{{ t('schedules.tier.standard') }}</option>
              <option value="slow">{{ t('schedules.tier.slow') }}</option>
            </select>
          </label>

          <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium">{{ t('bottle.message') }}</span>
            <input
              v-model="message"
              maxlength="500"
              class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5"
            />
          </label>

          <BaseButton type="submit" :disabled="!canSend" :loading="send.isPending.value">
            {{ t('bottle.send') }}
          </BaseButton>
          <p class="text-xs text-[var(--text-muted)]">{{ t('bottle.anonymityNote') }}</p>
        </form>
      </template>
    </section>
  </AppLayout>
</template>
