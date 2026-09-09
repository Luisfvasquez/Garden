<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useSchedules, useSchedulePause, useDeleteSchedule } from '@/composables/useSchedules'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import { formatDateTime } from '@/lib/datetime'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ScheduleForm from '@/components/schedules/ScheduleForm.vue'

const { t } = useI18n()
const { messageFor } = useApiError()
const ui = useUiStore()

const query = useSchedules()
const toggle = useSchedulePause()
const remove = useDeleteSchedule()
const creating = ref(false)

function setPaused(id: string, resume: boolean) {
  toggle.mutate({ id, resume }, { onError: (e) => ui.pushToast('error', messageFor(e)) })
}

function destroy(id: string) {
  if (!window.confirm(t('schedules.deleteConfirm'))) return
  remove.mutate(id, { onError: (e) => ui.pushToast('error', messageFor(e)) })
}
</script>

<template>
  <AppLayout>
    <section class="flex flex-col gap-4">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl">{{ t('schedules.title') }}</h1>
        <BaseButton v-if="!creating" @click="creating = true">{{ t('schedules.new') }}</BaseButton>
      </div>
      <p class="text-sm text-[var(--text-muted)]">{{ t('schedules.intro') }}</p>

      <ScheduleForm
        v-if="creating"
        @created="creating = false"
        @cancel="creating = false"
      />

      <SpinnerDots v-if="query.isPending.value" :label="t('common.loading')" />
      <AlertBox v-else-if="query.isError.value" kind="error">{{ messageFor(query.error.value) }}</AlertBox>
      <p v-else-if="!query.data.value?.data.length" class="text-sm text-[var(--text-muted)]">
        {{ t('schedules.empty') }}
      </p>

      <ul v-else class="flex flex-col gap-2">
        <li
          v-for="s in query.data.value?.data"
          :key="s.id"
          class="flex flex-wrap items-center gap-3 rounded-md border border-[var(--border-soft)] p-3 text-sm"
        >
          <span
            class="rounded px-1.5 py-0.5 text-xs"
            :class="s.status === 'active' ? 'bg-[var(--color-sage-500)]/20' : 'bg-[var(--border-soft)]'"
          >
            {{ t(`schedules.status.${s.status}`) }}
          </span>
          <RouterLink
            :to="{ name: 'schedule-timeline', params: { id: s.id } }"
            class="font-medium hover:underline"
          >
            {{ s.name }}
          </RouterLink>
          <span class="text-[var(--text-muted)]">
            {{ t(`schedules.recurrence.${s.recurrence_type}`) }} ·
            {{ s.recipient?.display_name ?? '—' }}
          </span>
          <span class="text-xs text-[var(--text-muted)]">{{ formatDateTime(s.created_at) }}</span>

          <span class="ml-auto flex gap-2">
            <button
              v-if="s.status !== 'completed'"
              type="button"
              class="text-[var(--accent)] underline"
              @click="setPaused(s.id, s.status === 'paused')"
            >
              {{ s.status === 'paused' ? t('schedules.resume') : t('schedules.pause') }}
            </button>
            <button type="button" class="text-[var(--color-wax-500)] underline" @click="destroy(s.id)">
              {{ t('common.delete') }}
            </button>
          </span>
        </li>
      </ul>
    </section>
  </AppLayout>
</template>
