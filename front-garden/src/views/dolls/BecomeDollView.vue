<script setup lang="ts">
import { reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  useMyDollProfile,
  useRequestDollRole,
  useUpdateDollProfile,
  useSetDollAvailability,
} from '@/composables/useDolls'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import type { DollRateType } from '@/types/api'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const { t } = useI18n()
const ui = useUiStore()
const { messageFor } = useApiError()

const myProfile = useMyDollProfile()
const request = useRequestDollRole()
const update = useUpdateDollProfile()
const availability = useSetDollAvailability()

const form = reactive({
  headline: '',
  bio: '',
  specialties: '',
  languages: 'es',
  tone_tags: '',
  rate_type: 'free' as DollRateType,
})

watch(
  () => myProfile.data.value,
  (p) => {
    if (!p) return
    form.headline = p.headline
    form.bio = p.bio ?? ''
    form.specialties = p.specialties.join(', ')
    form.languages = p.languages.join(', ')
    form.tone_tags = p.tone_tags.join(', ')
    form.rate_type = p.rate_type
  },
  { immediate: true },
)

function toList(s: string): string[] {
  return s
    .split(',')
    .map((v) => v.trim())
    .filter(Boolean)
}

async function submit() {
  const payload = {
    headline: form.headline.trim(),
    bio: form.bio.trim() || null,
    specialties: toList(form.specialties),
    languages: toList(form.languages),
    tone_tags: toList(form.tone_tags),
    rate_type: form.rate_type,
  }

  try {
    if (myProfile.data.value) {
      await update.mutateAsync(payload)
      ui.pushToast('success', t('dolls.become.updated'))
    } else {
      await request.mutateAsync(payload)
      ui.pushToast('success', t('dolls.become.requested'))
    }
  } catch (e) {
    ui.pushToast('error', messageFor(e))
  }
}

function toggleAvailability() {
  if (!myProfile.data.value) return
  availability.mutate(!myProfile.data.value.is_available, {
    onError: (e) => ui.pushToast('error', messageFor(e)),
  })
}
</script>

<template>
  <AppLayout>
    <section class="mx-auto flex max-w-xl flex-col gap-3">
      <h1 class="text-2xl">{{ t('dolls.become.title') }}</h1>
      <p class="text-sm text-[var(--text-muted)]">{{ t('dolls.become.intro') }}</p>

      <SpinnerDots v-if="myProfile.isPending.value" :label="t('common.loading')" />

      <template v-else>
        <AlertBox v-if="myProfile.data.value && !myProfile.data.value.verified_at" kind="info">
          {{ t('dolls.become.pendingReview') }}
        </AlertBox>
        <AlertBox v-else-if="myProfile.data.value?.verified_at" kind="success">
          {{ t('dolls.become.verified') }}
        </AlertBox>

        <label v-if="myProfile.data.value?.verified_at" class="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            :checked="myProfile.data.value.is_available"
            @change="toggleAvailability"
          />
          {{ t('dolls.become.available') }}
        </label>

        <BaseInput v-model="form.headline" :label="t('dolls.become.headline')" required />

        <label class="flex flex-col gap-1 text-sm">
          <span class="font-medium">{{ t('dolls.become.bio') }}</span>
          <textarea
            v-model="form.bio"
            rows="4"
            class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5"
          />
        </label>

        <BaseInput v-model="form.specialties" :label="t('dolls.become.specialties')" :hint="t('dolls.become.commaHint')" />
        <BaseInput v-model="form.languages" :label="t('dolls.become.languages')" :hint="t('dolls.become.commaHint')" />
        <BaseInput v-model="form.tone_tags" :label="t('dolls.become.tone')" :hint="t('dolls.become.commaHint')" />

        <label class="flex flex-col gap-1 text-sm">
          <span class="font-medium">{{ t('dolls.become.rateType') }}</span>
          <select
            v-model="form.rate_type"
            class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5"
          >
            <option value="free">{{ t('dolls.rateType.free') }}</option>
            <option value="per_letter">{{ t('dolls.rateType.per_letter') }}</option>
            <option value="hourly">{{ t('dolls.rateType.hourly') }}</option>
          </select>
        </label>

        <BaseButton :loading="request.isPending.value || update.isPending.value" @click="submit">
          {{ myProfile.data.value ? t('dolls.become.save') : t('dolls.become.submit') }}
        </BaseButton>
      </template>
    </section>
  </AppLayout>
</template>
