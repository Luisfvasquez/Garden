<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCreateDollRequest } from '@/composables/useDollRequests'
import { useApiError } from '@/composables/useApiError'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { messageFor } = useApiError()
const create = useCreateDollRequest()

const dollHandle = (route.query.handle as string) ?? ''
const banner = ref('')

const form = reactive({
  occasion: '',
  brief_notes: '',
  target_recipient_hint: '',
  desired_tone: '',
  deadline_at: '',
})

async function submit() {
  banner.value = ''
  try {
    const request = await create.mutateAsync({
      doll_handle: dollHandle,
      occasion: form.occasion.trim(),
      brief_notes: form.brief_notes.trim() || null,
      target_recipient_hint: form.target_recipient_hint.trim() || null,
      desired_tone: form.desired_tone
        .split(/[,\n]+/)
        .map((s) => s.trim())
        .filter(Boolean),
      deadline_at: form.deadline_at ? new Date(form.deadline_at).toISOString() : null,
    })
    router.push({ name: 'doll-request-detail', params: { id: request.id } })
  } catch (error) {
    banner.value = messageFor(error)
  }
}
</script>

<template>
  <AppLayout>
    <section class="mx-auto flex max-w-xl flex-col gap-4">
      <RouterLink :to="{ name: 'doll-profile', params: { id: dollHandle } }" class="text-sm text-[var(--accent)] underline">
        ← {{ t('dolls.profile.backToDirectory') }}
      </RouterLink>

      <h1 class="text-2xl">{{ t('dolls.newRequest.title') }}</h1>
      <p class="text-sm text-[var(--text-muted)]">{{ t('dolls.newRequest.intro') }}</p>

      <form class="flex flex-col gap-3 rounded-md border border-[var(--border-soft)] p-4" @submit.prevent="submit">
        <AlertBox v-if="banner" kind="error">{{ banner }}</AlertBox>

        <BaseInput v-model="form.occasion" :label="t('dolls.newRequest.occasion')" required />

        <label class="flex flex-col gap-1 text-sm">
          <span class="font-medium">{{ t('dolls.newRequest.briefNotes') }}</span>
          <textarea
            v-model="form.brief_notes"
            rows="5"
            class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5"
          />
        </label>

        <BaseInput v-model="form.target_recipient_hint" :label="t('dolls.newRequest.recipientHint')" />
        <p class="-mt-2 text-xs text-[var(--text-muted)]">{{ t('dolls.newRequest.recipientHintWarning') }}</p>

        <BaseInput v-model="form.desired_tone" :label="t('dolls.newRequest.desiredTone')" />
        <BaseInput v-model="form.deadline_at" type="date" :label="t('dolls.newRequest.deadline')" />

        <div class="flex gap-2">
          <BaseButton type="submit" :loading="create.isPending.value">{{ t('dolls.newRequest.submit') }}</BaseButton>
        </div>
      </form>
    </section>
  </AppLayout>
</template>
