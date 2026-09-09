<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  useSchedule,
  useScheduleOccurrences,
  useAssignOccurrenceLetter,
} from '@/composables/useSchedules'
import { useLetterList } from '@/composables/useLetters'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import { formatDateTime } from '@/lib/datetime'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'

const { t } = useI18n()
const route = useRoute()
const { messageFor } = useApiError()
const ui = useUiStore()

const id = computed(() => route.params.id as string)
const schedule = useSchedule(id)
const timeline = useScheduleOccurrences(id)
const drafts = useLetterList('draft')
const assign = useAssignOccurrenceLetter(id)

const badge: Record<string, string> = {
  empty: 'bg-[var(--border-soft)]',
  pending: 'bg-[var(--color-violet-500)]/20',
  queued: 'bg-[var(--color-sage-500)]/20',
  in_transit: 'bg-[var(--color-violet-500)]/20',
  delivered: 'bg-[var(--color-sage-500)]/30',
  cancelled: 'bg-[var(--color-wax-500)]/20',
}

function onAssign(date: string, letterId: string) {
  assign.mutate(
    { date, letterId: letterId || null },
    { onError: (e) => ui.pushToast('error', messageFor(e)) },
  )
}
</script>

<template>
  <AppLayout>
    <section class="flex flex-col gap-4">
      <RouterLink :to="{ name: 'schedules' }" class="text-sm text-[var(--accent)] underline">
        ← {{ t('schedules.backToList') }}
      </RouterLink>

      <SpinnerDots v-if="schedule.isPending.value" :label="t('common.loading')" />
      <AlertBox v-else-if="schedule.isError.value" kind="error">
        {{ messageFor(schedule.error.value) }}
      </AlertBox>

      <template v-else-if="schedule.data.value">
        <h1 class="text-2xl">{{ schedule.data.value.name }}</h1>
        <p class="text-sm text-[var(--text-muted)]">
          {{ t(`schedules.recurrence.${schedule.data.value.recurrence_type}`) }} ·
          {{ schedule.data.value.timezone }} ·
          {{ schedule.data.value.local_time }}
        </p>

        <div v-if="timeline.data.value" class="text-sm text-[var(--text-muted)]">
          {{
            t('schedules.timelineMeta', {
              filled: timeline.data.value.meta.filled,
              total: timeline.data.value.meta.total,
              delivered: timeline.data.value.meta.delivered,
            })
          }}
        </div>

        <SpinnerDots v-if="timeline.isPending.value" />
        <ol v-else class="flex flex-col gap-2">
          <li
            v-for="o in timeline.data.value?.data"
            :key="o.date"
            class="flex flex-wrap items-center gap-3 rounded-md border border-[var(--border-soft)] p-3 text-sm"
          >
            <span class="rounded px-1.5 py-0.5 text-xs" :class="badge[o.status]">
              {{ t(`schedules.occurrence.${o.status}`) }}
            </span>
            <span class="font-mono">{{ o.date }}</span>
            <span class="text-xs text-[var(--text-muted)]">{{ formatDateTime(o.runs_at) }}</span>

            <span v-if="o.letter" class="text-[var(--text)]">
              {{ o.letter.title || t('schedules.form.untitled') }}
            </span>

            <select
              v-if="o.status === 'empty' || o.status === 'pending'"
              class="ml-auto rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1 text-xs"
              :value="o.letter?.id ?? ''"
              :disabled="assign.isPending.value"
              @change="onAssign(o.date, ($event.target as HTMLSelectElement).value)"
            >
              <option value="">{{ t('schedules.pickLetter') }}</option>
              <option v-for="l in drafts.data.value?.data ?? []" :key="l.id" :value="l.id">
                {{ l.title || t('schedules.form.untitled') }}
              </option>
            </select>
            <RouterLink
              v-else-if="o.delivery_id"
              :to="{ name: 'delivery-tracking', params: { id: o.delivery_id } }"
              class="ml-auto text-[var(--accent)] underline"
            >
              {{ t('schedules.viewTracking') }}
            </RouterLink>
          </li>
        </ol>
      </template>
    </section>
  </AppLayout>
</template>
