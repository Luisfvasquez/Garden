<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDollRequest } from '@/composables/useDollRequests'
import {
  isChannelOpen,
  useApproveDollDraft,
  useDollChatMessages,
  useDollChatRealtime,
  useSendDollChatMessage,
  useShareDollDraft,
} from '@/composables/useDollChat'
import { useApiError } from '@/composables/useApiError'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ChatMessage from '@/components/dolls/ChatMessage.vue'
import DraftComposer from '@/components/dolls/DraftComposer.vue'
import type { CreateDollDraftInput } from '@/types/api'

/**
 * The Doll chat (ADR-0005 — the only real-time surface in the product).
 *
 * The channel closes with the request, so this view has two modes: live, and a
 * read-only transcript. It never pretends the conversation is still open.
 */
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { messageFor } = useApiError()
const auth = useAuthStore()
const ui = useUiStore()

const id = computed(() => route.params.id as string)
const request = useDollRequest(id)
const messages = useDollChatMessages(id)

const status = computed(() => request.data.value?.status)
const { typingName, notifyTyping } = useDollChatRealtime(id, status)

const open = computed(() => isChannelOpen(status.value))
const isDoll = computed(() => request.data.value?.doll?.postal_handle === auth.user?.postal_handle)
const isClient = computed(() => request.data.value?.client?.postal_handle === auth.user?.postal_handle)

const send = useSendDollChatMessage(id)
const shareDraft = useShareDollDraft(id)
const approve = useApproveDollDraft(id)

const body = ref('')
const listEnd = ref<HTMLElement | null>(null)

watch(
  () => messages.data.value?.data.length,
  async () => {
    await nextTick()
    listEnd.value?.scrollIntoView({ block: 'end' })
  },
)

/** Whisper "typing" while composing; the composable throttles the wire. */
function onTyping() {
  // `me` is the authenticated user shape, which carries pen_name/name, not the
  // public `display_name` the API composes for other people.
  const me = auth.user?.pen_name || auth.user?.name || auth.user?.postal_handle
  if (me) notifyTyping(me)
}

function submit() {
  const text = body.value.trim()
  if (!text) return
  send
    .mutateAsync(text)
    .then(() => {
      body.value = ''
    })
    .catch((error) => ui.pushToast('error', messageFor(error)))
}

function onShare(input: CreateDollDraftInput) {
  shareDraft.mutateAsync(input).catch((error) => ui.pushToast('error', messageFor(error)))
}

/** Approving completes the request and hands the client a letter to send. */
function onApprove(draftId: string) {
  approve
    .mutateAsync(draftId)
    .then((letter) => {
      ui.pushToast('success', t('dolls.chat.approvedToast'))
      void router.push({ name: 'letter-edit', params: { id: letter.id } })
    })
    .catch((error) => ui.pushToast('error', messageFor(error)))
}
</script>

<template>
  <AppLayout>
    <section class="mx-auto flex h-full max-w-2xl flex-col gap-4">
      <RouterLink :to="{ name: 'doll-request-detail', params: { id } }" class="text-sm text-[var(--accent)] underline">
        ← {{ t('dolls.chat.backToRequest') }}
      </RouterLink>

      <SpinnerDots v-if="request.isPending.value" :label="t('common.loading')" />
      <AlertBox v-else-if="request.isError.value" kind="error">
        {{ messageFor(request.error.value) }}
      </AlertBox>

      <template v-else-if="request.data.value">
        <header>
          <h1 class="font-serif text-2xl">{{ request.data.value.occasion }}</h1>
          <p class="text-sm text-[var(--text-muted)]">
            {{ t(`dolls.status.${request.data.value.status}`) }}
          </p>
        </header>

        <AlertBox v-if="!open" kind="info">{{ t('dolls.chat.closed') }}</AlertBox>
        <AlertBox v-else-if="isDoll" kind="info">{{ t('dolls.chat.dollNotice') }}</AlertBox>

        <SpinnerDots v-if="messages.isPending.value" :label="t('common.loading')" />
        <AlertBox v-else-if="messages.isError.value" kind="error">
          {{ messageFor(messages.error.value) }}
        </AlertBox>

        <template v-else>
          <p v-if="!messages.data.value?.data.length" class="text-sm text-[var(--text-muted)]">
            {{ t('dolls.chat.empty') }}
          </p>

          <ul v-else class="flex flex-col gap-3">
            <ChatMessage
              v-for="message in messages.data.value.data"
              :key="message.id"
              :message="message"
              :can-approve="isClient && open"
              :approving="approve.isPending.value"
              @approve="onApprove"
            />
          </ul>
          <div ref="listEnd" />
        </template>

        <template v-if="open">
          <DraftComposer v-if="isDoll" :busy="shareDraft.isPending.value" @share="onShare" />

          <p class="h-4 text-xs text-[var(--text-muted)]" aria-live="polite">
            <template v-if="typingName">{{ t('dolls.chat.typing', { name: typingName }) }}</template>
          </p>

          <form class="flex items-end gap-2" @submit.prevent="submit">
            <label class="flex-1">
              <span class="sr-only">{{ t('dolls.chat.messageLabel') }}</span>
              <textarea
                v-model="body"
                rows="2"
                maxlength="4000"
                :placeholder="t('dolls.chat.placeholder')"
                class="w-full rounded border border-[var(--border-soft)] bg-[var(--surface)] px-3 py-2 text-sm"
                @input="onTyping"
                @keydown.enter.exact.prevent="submit"
              />
            </label>
            <BaseButton type="submit" :loading="send.isPending.value">
              {{ t('dolls.chat.send') }}
            </BaseButton>
          </form>

          <p class="text-xs text-[var(--text-muted)]">{{ t('dolls.chat.privacyHint') }}</p>
        </template>
      </template>
    </section>
  </AppLayout>
</template>
