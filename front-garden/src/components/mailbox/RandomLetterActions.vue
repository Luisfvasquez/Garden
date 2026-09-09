<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useReplyAnonymous, useOpenCorrespondence } from '@/composables/useRandom'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import type { MailboxLetter } from '@/types/api'
import BaseButton from '@/components/ui/BaseButton.vue'
import AlertBox from '@/components/ui/AlertBox.vue'

const props = defineProps<{ letter: MailboxLetter }>()

const { t } = useI18n()
const { messageFor } = useApiError()
const ui = useUiStore()
const reply = useReplyAnonymous()
const openCorr = useOpenCorrespondence()

const showReply = ref(false)
const text = ref('')
const banner = ref('')
const canReply = ref(props.letter.can_reply_anonymously ?? false)
const corr = ref(props.letter.correspondence ?? null)

async function sendReply() {
  banner.value = ''
  const body = {
    type: 'doc',
    content: [{ type: 'paragraph', content: [{ type: 'text', text: text.value.trim() }] }],
  }
  try {
    await reply.mutateAsync({ deliveryId: props.letter.id, body })
    canReply.value = false
    showReply.value = false
    ui.pushToast('success', t('bottle.replySent'))
  } catch (e) {
    banner.value = messageFor(e)
  }
}

async function accept() {
  try {
    corr.value = await openCorr.mutateAsync(props.letter.id)
    if (corr.value.opened) ui.pushToast('success', t('bottle.corrOpened'))
  } catch (e) {
    ui.pushToast('error', messageFor(e))
  }
}
</script>

<template>
  <div class="flex flex-col gap-2 rounded-md border border-[var(--border-soft)] bg-[var(--surface-sunken)] p-3 text-sm">
    <p class="text-[var(--text-muted)]">{{ t('bottle.fromStranger') }}</p>
    <AlertBox v-if="banner" kind="error">{{ banner }}</AlertBox>

    <div class="flex flex-wrap gap-2">
      <BaseButton v-if="canReply && !showReply" variant="ghost" @click="showReply = true">
        {{ t('bottle.replyOnce') }}
      </BaseButton>
      <span v-else-if="!canReply" class="text-[var(--text-muted)]">{{ t('bottle.replyUsed') }}</span>

      <BaseButton
        v-if="corr && !corr.opened && !corr.recipient_accepted"
        variant="ghost"
        :loading="openCorr.isPending.value"
        @click="accept"
      >
        {{ t('bottle.openCorr') }}
      </BaseButton>
      <span v-else-if="corr?.opened" class="text-[var(--text-muted)]">{{ t('bottle.corrOpenedShort') }}</span>
      <span v-else-if="corr?.recipient_accepted" class="text-[var(--text-muted)]">{{ t('bottle.corrWaiting') }}</span>
    </div>

    <form v-if="showReply" class="flex flex-col gap-2" @submit.prevent="sendReply">
      <textarea
        v-model="text"
        rows="4"
        :placeholder="t('bottle.replyPlaceholder')"
        class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5"
      />
      <div class="flex gap-2">
        <BaseButton type="submit" :loading="reply.isPending.value" :disabled="!text.trim()">
          {{ t('bottle.replySend') }}
        </BaseButton>
        <button type="button" class="text-[var(--text-muted)] underline" @click="showReply = false">
          {{ t('common.cancel') }}
        </button>
      </div>
    </form>
  </div>
</template>
