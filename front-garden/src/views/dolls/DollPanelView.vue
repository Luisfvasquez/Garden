<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDollRequests, useAcceptDollRequest, useRejectDollRequest, useStartDollRequest } from '@/composables/useDollRequests'
import { useMyDollProfile, useSetDollAvailability } from '@/composables/useDolls'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import { formatDateTime } from '@/lib/datetime'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import type { DollRequest } from '@/types/api'

/**
 * The Doll's own workspace: what is waiting for an answer, and what is
 * actually in hand. Distinct from "mis solicitudes" (the client's view of the
 * same table) because the questions are different — a Doll needs to know
 * whether to take more work, not to browse history.
 */
const { t } = useI18n()
const { messageFor } = useApiError()
const ui = useUiStore()

const profile = useMyDollProfile()
const requests = useDollRequests(() => 'doll', () => undefined)

const accept = useAcceptDollRequest()
const reject = useRejectDollRequest()
const start = useStartDollRequest()
const availability = useSetDollAvailability()

const verified = computed(() => Boolean(profile.data.value?.verified_at))

const all = computed<DollRequest[]>(() => requests.data.value?.data ?? [])
const pending = computed(() => all.value.filter((r) => r.status === 'pending'))
const active = computed(() =>
  all.value.filter((r) => ['accepted', 'in_progress', 'awaiting_client'].includes(r.status)),
)
const done = computed(() => all.value.filter((r) => r.status === 'completed'))

/** What the backend counts against `max_concurrent_requests` — pending doesn't. */
const capacity = computed(() => profile.data.value?.max_concurrent_requests ?? null)
const atCapacity = computed(() => capacity.value !== null && active.value.length >= capacity.value)

function run(mutation: { mutateAsync: (id: string) => Promise<unknown> }, id: string) {
  mutation.mutateAsync(id).catch((error) => ui.pushToast('error', messageFor(error)))
}

function toggleAvailability() {
  availability
    .mutateAsync(!profile.data.value?.is_available)
    .catch((error) => ui.pushToast('error', messageFor(error)))
}
</script>

<template>
  <AppLayout>
    <section class="flex flex-col gap-5">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl">{{ t('dolls.panel.title') }}</h1>
        <RouterLink :to="{ name: 'doll-requests' }" class="text-sm text-[var(--accent)] underline">
          {{ t('dolls.requests.title') }}
        </RouterLink>
      </div>

      <SpinnerDots v-if="profile.isPending.value" :label="t('common.loading')" />

      <!-- No profile, or still pending review: nothing to run yet. -->
      <AlertBox v-else-if="!profile.data.value" kind="info">
        {{ t('dolls.panel.noProfile') }}
        <RouterLink :to="{ name: 'doll-become' }" class="underline">{{ t('dolls.panel.becomeDoll') }}</RouterLink>
      </AlertBox>
      <AlertBox v-else-if="!verified" kind="info">{{ t('dolls.panel.pendingReview') }}</AlertBox>

      <template v-else>
        <!-- Availability + capacity, the two things a Doll actually steers. -->
        <div class="flex flex-wrap items-center gap-3 rounded-lg border border-[var(--border-soft)] p-3 text-sm">
          <span :class="profile.data.value.is_available ? 'text-[var(--accent)]' : 'text-[var(--text-muted)]'">
            {{ profile.data.value.is_available ? t('dolls.panel.available') : t('dolls.panel.unavailable') }}
          </span>
          <button
            type="button"
            class="rounded border border-[var(--border-soft)] px-2 py-1"
            :disabled="availability.isPending.value"
            @click="toggleAvailability"
          >
            {{ profile.data.value.is_available ? t('dolls.panel.pause') : t('dolls.panel.resume') }}
          </button>
          <span v-if="capacity !== null" class="ml-auto text-[var(--text-muted)]">
            {{ t('dolls.panel.capacity', { active: active.length, max: capacity }) }}
          </span>
        </div>

        <AlertBox v-if="atCapacity" kind="info">{{ t('dolls.panel.atCapacity') }}</AlertBox>

        <SpinnerDots v-if="requests.isPending.value" :label="t('common.loading')" />
        <AlertBox v-else-if="requests.isError.value" kind="error">
          {{ messageFor(requests.error.value) }}
        </AlertBox>

        <template v-else>
          <!-- Waiting on me -->
          <section class="flex flex-col gap-2">
            <h2 class="font-serif text-lg">
              {{ t('dolls.panel.inbox') }} <span class="text-sm text-[var(--text-muted)]">({{ pending.length }})</span>
            </h2>
            <p v-if="!pending.length" class="text-sm text-[var(--text-muted)]">{{ t('dolls.panel.inboxEmpty') }}</p>

            <article
              v-for="r in pending"
              :key="r.id"
              class="flex flex-col gap-2 rounded-md border border-[var(--border-soft)] p-3"
            >
              <div class="flex flex-wrap items-baseline gap-2">
                <RouterLink
                  :to="{ name: 'doll-request-detail', params: { id: r.id } }"
                  class="font-medium underline"
                >
                  {{ r.occasion }}
                </RouterLink>
                <span class="text-sm text-[var(--text-muted)]">{{ r.client?.display_name }}</span>
                <span v-if="r.expires_at" class="ml-auto text-xs text-[var(--color-wax-500)]">
                  {{ t('dolls.requests.expiresAt') }} {{ formatDateTime(r.expires_at) }}
                </span>
              </div>

              <p v-if="r.brief_notes" class="line-clamp-2 text-sm text-[var(--text-muted)]">{{ r.brief_notes }}</p>

              <div class="flex gap-2">
                <BaseButton :loading="accept.isPending.value" @click="run(accept, r.id)">
                  {{ t('dolls.requests.accept') }}
                </BaseButton>
                <button
                  type="button"
                  class="rounded border border-[var(--border-soft)] px-3 py-1.5 text-sm"
                  @click="run(reject, r.id)"
                >
                  {{ t('dolls.requests.reject') }}
                </button>
              </div>
            </article>
          </section>

          <!-- In hand -->
          <section class="flex flex-col gap-2">
            <h2 class="font-serif text-lg">
              {{ t('dolls.panel.active') }} <span class="text-sm text-[var(--text-muted)]">({{ active.length }})</span>
            </h2>
            <p v-if="!active.length" class="text-sm text-[var(--text-muted)]">{{ t('dolls.panel.activeEmpty') }}</p>

            <article
              v-for="r in active"
              :key="r.id"
              class="flex flex-wrap items-center gap-3 rounded-md border border-[var(--border-soft)] p-3 text-sm"
            >
              <span class="rounded bg-[var(--surface-sunken)] px-1.5 py-0.5 text-xs">
                {{ t(`dolls.status.${r.status}`) }}
              </span>
              <RouterLink :to="{ name: 'doll-request-detail', params: { id: r.id } }" class="font-medium underline">
                {{ r.occasion }}
              </RouterLink>
              <span class="text-[var(--text-muted)]">{{ r.client?.display_name }}</span>

              <div class="ml-auto flex gap-2">
                <BaseButton v-if="r.status === 'accepted'" :loading="start.isPending.value" @click="run(start, r.id)">
                  {{ t('dolls.requests.start') }}
                </BaseButton>
                <RouterLink
                  v-else
                  :to="{ name: 'doll-request-chat', params: { id: r.id } }"
                  class="rounded bg-[var(--accent)] px-3 py-1.5 text-xs text-[var(--surface)]"
                >
                  {{ t('dolls.chat.open') }}
                </RouterLink>
              </div>
            </article>
          </section>

          <!-- Finished -->
          <section v-if="done.length" class="flex flex-col gap-2">
            <h2 class="font-serif text-lg">
              {{ t('dolls.panel.done') }} <span class="text-sm text-[var(--text-muted)]">({{ done.length }})</span>
            </h2>
            <ul class="flex flex-col gap-1 text-sm">
              <li v-for="r in done" :key="r.id" class="flex flex-wrap items-center gap-2">
                <RouterLink :to="{ name: 'doll-request-detail', params: { id: r.id } }" class="underline">
                  {{ r.occasion }}
                </RouterLink>
                <span v-if="r.client_rating" class="text-[var(--accent)]" aria-hidden="true">
                  {{ '★'.repeat(r.client_rating) }}
                </span>
                <span v-else class="text-xs text-[var(--text-muted)]">{{ t('dolls.panel.unrated') }}</span>
              </li>
            </ul>
          </section>
        </template>
      </template>
    </section>
  </AppLayout>
</template>
