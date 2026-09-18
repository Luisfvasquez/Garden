<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  useDollRequest,
  useAcceptDollRequest,
  useRejectDollRequest,
  useStartDollRequest,
  useCancelDollRequest,
  useRateDollRequest,
} from '@/composables/useDollRequests'
import { isChannelOpen } from '@/composables/useDollChat'
import { useApiError } from '@/composables/useApiError'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { formatDateTime } from '@/lib/datetime'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import RatingForm from '@/components/dolls/RatingForm.vue'

const { t } = useI18n()
const route = useRoute()
const { messageFor } = useApiError()
const auth = useAuthStore()
const ui = useUiStore()

const id = computed(() => route.params.id as string)
const request = useDollRequest(id)

const accept = useAcceptDollRequest()
const reject = useRejectDollRequest()
const start = useStartDollRequest()
const cancel = useCancelDollRequest()
const rate = useRateDollRequest()

/** Only the client rates, and only once the request actually completed. */
const canRate = computed(
  () => isClient.value && request.data.value?.status === 'completed',
)

function submitRating(payload: { rating: number; comment: string | null }) {
  rate
    .mutateAsync({ id: id.value, ...payload })
    .then(() => ui.pushToast('success', t('dolls.rating.thanks')))
    .catch((error) => ui.pushToast('error', messageFor(error)))
}

const isDoll = computed(() => request.data.value?.doll?.postal_handle === auth.user?.postal_handle)
const isClient = computed(() => request.data.value?.client?.postal_handle === auth.user?.postal_handle)
// There is something to read as soon as the request has been started once.
const hasTranscript = computed(() =>
  ['in_progress', 'awaiting_client', 'completed', 'cancelled'].includes(request.data.value?.status ?? ''),
)
const isNonTerminal = computed(
  () => !!request.data.value && !['completed', 'rejected', 'expired', 'cancelled'].includes(request.data.value.status),
)

function run(mutation: { mutateAsync: (id: string) => Promise<unknown> }) {
  mutation.mutateAsync(id.value).catch((error) => ui.pushToast('error', messageFor(error)))
}
</script>

<template>
  <AppLayout>
    <section class="mx-auto flex max-w-xl flex-col gap-4">
      <RouterLink :to="{ name: 'doll-requests' }" class="text-sm text-[var(--accent)] underline">
        ← {{ t('dolls.requests.title') }}
      </RouterLink>

      <SpinnerDots v-if="request.isPending.value" :label="t('common.loading')" />
      <AlertBox v-else-if="request.isError.value" kind="error">{{ messageFor(request.error.value) }}</AlertBox>

      <template v-else-if="request.data.value">
        <header class="flex items-center justify-between">
          <h1 class="font-serif text-2xl">{{ request.data.value.occasion }}</h1>
          <span class="rounded-full bg-[var(--surface-sunken)] px-2 py-1 text-xs">
            {{ t(`dolls.status.${request.data.value.status}`) }}
          </span>
        </header>

        <p class="text-sm text-[var(--text-muted)]">
          {{ request.data.value.client?.display_name }} → {{ request.data.value.doll?.display_name }}
        </p>

        <p v-if="request.data.value.brief_notes" class="text-sm leading-relaxed whitespace-pre-line">
          {{ request.data.value.brief_notes }}
        </p>

        <dl class="grid grid-cols-2 gap-2 text-sm">
          <template v-if="request.data.value.target_recipient_hint">
            <dt class="text-[var(--text-muted)]">{{ t('dolls.newRequest.recipientHint') }}</dt>
            <dd>{{ request.data.value.target_recipient_hint }}</dd>
          </template>
          <template v-if="request.data.value.desired_tone.length">
            <dt class="text-[var(--text-muted)]">{{ t('dolls.newRequest.desiredTone') }}</dt>
            <dd>{{ request.data.value.desired_tone.join(', ') }}</dd>
          </template>
          <template v-if="request.data.value.deadline_at">
            <dt class="text-[var(--text-muted)]">{{ t('dolls.newRequest.deadline') }}</dt>
            <dd>{{ formatDateTime(request.data.value.deadline_at) }}</dd>
          </template>
          <template v-if="request.data.value.status === 'pending' && request.data.value.expires_at">
            <dt class="text-[var(--text-muted)]">{{ t('dolls.requests.expiresAt') }}</dt>
            <dd>{{ formatDateTime(request.data.value.expires_at) }}</dd>
          </template>
        </dl>

        <div class="flex flex-wrap gap-2">
          <!-- El chat sólo existe mientras se trabaja la solicitud (ADR-0005);
               una vez cerrada, el enlace lleva a la transcripción de sólo lectura. -->
          <RouterLink
            v-if="hasTranscript"
            :to="{ name: 'doll-request-chat', params: { id } }"
            class="rounded bg-[var(--accent)] px-3 py-1.5 text-sm text-[var(--surface)]"
          >
            {{ isChannelOpen(request.data.value.status) ? t('dolls.chat.open') : t('dolls.chat.viewTranscript') }}
          </RouterLink>

          <template v-if="isDoll && request.data.value.status === 'pending'">
            <BaseButton :loading="accept.isPending.value" @click="run(accept)">
              {{ t('dolls.requests.accept') }}
            </BaseButton>
            <button
              type="button"
              class="rounded border border-[var(--border-soft)] px-3 py-1.5 text-sm"
              @click="run(reject)"
            >
              {{ t('dolls.requests.reject') }}
            </button>
          </template>

          <BaseButton v-if="isDoll && request.data.value.status === 'accepted'" :loading="start.isPending.value" @click="run(start)">
            {{ t('dolls.requests.start') }}
          </BaseButton>

          <button
            v-if="isNonTerminal"
            type="button"
            class="text-sm text-[var(--color-wax-500)] underline"
            @click="run(cancel)"
          >
            {{ t('dolls.requests.cancel') }}
          </button>
        </div>

        <RatingForm
          v-if="canRate"
          :request="request.data.value"
          :busy="rate.isPending.value"
          @rate="submitRating"
        />
      </template>
    </section>
  </AppLayout>
</template>
