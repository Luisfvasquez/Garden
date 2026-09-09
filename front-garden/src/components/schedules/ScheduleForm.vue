<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCreateSchedule } from '@/composables/useSchedules'
import { useLetterList } from '@/composables/useLetters'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import type { CreateScheduleInput, RecurrenceType } from '@/types/api'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import AlertBox from '@/components/ui/AlertBox.vue'

const emit = defineEmits<{ created: []; cancel: [] }>()

const { t } = useI18n()
const ui = useUiStore()
const { messageFor } = useApiError()
const create = useCreateSchedule()
const drafts = useLetterList('draft')

const recurrences: RecurrenceType[] = ['yearly', 'monthly', 'weekly', 'once', 'custom_dates']
const banner = ref('')

const form = reactive({
  name: '',
  postal_handle: '',
  recurrence_type: 'yearly' as RecurrenceType,
  anchor_date: '',
  local_time: '09:00',
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
  occurrences_total: '10',
  custom_dates: '',
  leap_day_policy: 'feb_28' as 'feb_28' | 'mar_01',
  tier: 'standard' as CreateScheduleInput['tier'],
  letter_id: '' as string,
  is_anonymous: false,
})

const needsTotal = computed(() => ['yearly', 'monthly', 'weekly'].includes(form.recurrence_type))
const isCustom = computed(() => form.recurrence_type === 'custom_dates')

async function submit() {
  banner.value = ''
  const payload: CreateScheduleInput = {
    name: form.name.trim(),
    recipient: { postal_handle: form.postal_handle.trim() },
    recurrence_type: form.recurrence_type,
    anchor_date: form.anchor_date,
    local_time: form.local_time,
    timezone: form.timezone,
    tier: form.tier,
    is_anonymous: form.is_anonymous,
    letter_id: form.letter_id || null,
  }
  if (needsTotal.value) payload.occurrences_total = Number(form.occurrences_total)
  if (form.recurrence_type === 'yearly') payload.leap_day_policy = form.leap_day_policy
  if (isCustom.value) {
    payload.custom_dates = form.custom_dates
      .split(/[\s,]+/)
      .map((s) => s.trim())
      .filter(Boolean)
  }

  try {
    await create.mutateAsync(payload)
    ui.pushToast('success', t('schedules.form.created'))
    emit('created')
  } catch (error) {
    banner.value = messageFor(error)
  }
}
</script>

<template>
  <form class="flex flex-col gap-3 rounded-md border border-[var(--border-soft)] p-4" @submit.prevent="submit">
    <AlertBox v-if="banner" kind="error">{{ banner }}</AlertBox>

    <BaseInput v-model="form.name" :label="t('schedules.form.name')" required />
    <BaseInput v-model="form.postal_handle" :label="t('schedules.form.recipient')" required />

    <label class="flex flex-col gap-1 text-sm">
      <span class="font-medium">{{ t('schedules.form.recurrence') }}</span>
      <select v-model="form.recurrence_type" class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5">
        <option v-for="r in recurrences" :key="r" :value="r">{{ t(`schedules.recurrence.${r}`) }}</option>
      </select>
    </label>

    <div class="flex flex-wrap gap-3">
      <BaseInput v-model="form.anchor_date" type="date" :label="t('schedules.form.anchorDate')" required />
      <BaseInput v-model="form.local_time" type="time" :label="t('schedules.form.localTime')" required />
    </div>
    <BaseInput v-model="form.timezone" :label="t('schedules.form.timezone')" required />

    <BaseInput
      v-if="needsTotal"
      v-model="form.occurrences_total"
      type="number"
      :label="t('schedules.form.total')"
      required
    />

    <label v-if="isCustom" class="flex flex-col gap-1 text-sm">
      <span class="font-medium">{{ t('schedules.form.customDates') }}</span>
      <textarea
        v-model="form.custom_dates"
        rows="3"
        placeholder="2027-04-12 2028-04-12"
        class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5 font-mono text-sm"
      />
    </label>

    <label v-if="form.recurrence_type === 'yearly'" class="flex flex-col gap-1 text-sm">
      <span class="font-medium">{{ t('schedules.form.leapDay') }}</span>
      <select v-model="form.leap_day_policy" class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5">
        <option value="feb_28">{{ t('schedules.leap.feb_28') }}</option>
        <option value="mar_01">{{ t('schedules.leap.mar_01') }}</option>
      </select>
    </label>

    <label class="flex flex-col gap-1 text-sm">
      <span class="font-medium">{{ t('schedules.form.letter') }}</span>
      <select v-model="form.letter_id" class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5">
        <option value="">{{ t('schedules.form.letterPerOccurrence') }}</option>
        <option v-for="l in drafts.data.value?.data ?? []" :key="l.id" :value="l.id">
          {{ l.title || t('schedules.form.untitled') }}
        </option>
      </select>
    </label>

    <label class="flex flex-col gap-1 text-sm">
      <span class="font-medium">{{ t('schedules.form.tier') }}</span>
      <select v-model="form.tier" class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5">
        <option value="express">{{ t('schedules.tier.express') }}</option>
        <option value="standard">{{ t('schedules.tier.standard') }}</option>
        <option value="slow">{{ t('schedules.tier.slow') }}</option>
      </select>
    </label>

    <BaseCheckbox v-model="form.is_anonymous" :label="t('schedules.form.anonymous')" />

    <div class="flex gap-2">
      <BaseButton type="submit" :loading="create.isPending.value">{{ t('schedules.form.submit') }}</BaseButton>
      <button type="button" class="text-sm text-[var(--text-muted)] underline" @click="emit('cancel')">
        {{ t('common.cancel') }}
      </button>
    </div>
  </form>
</template>
