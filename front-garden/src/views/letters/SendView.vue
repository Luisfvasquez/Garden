<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { watchDebounced } from '@vueuse/core'
import type { PublicUser, TransitTier } from '@/types/api'
import { usersApi } from '@/api/users'
import { isApiError } from '@/api/client'
import { useLetter } from '@/composables/useLetters'
import { useSendLetter } from '@/composables/useLetters'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import { toIsoZ } from '@/lib/datetime'
import AppLayout from '@/layouts/AppLayout.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AlertBox from '@/components/ui/AlertBox.vue'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const ui = useUiStore()
const { messageFor, fieldErrors } = useApiError()

const id = computed(() => route.params.id as string)
const letterQuery = useLetter(id)
const send = useSendLetter()
const idempotencyKey = crypto.randomUUID()

const tiers: TransitTier[] = ['express', 'standard', 'slow']

const form = reactive({
  handle: '',
  tier: 'standard' as TransitTier,
  scheduleArrival: false,
  arriveLocal: '',
  isAnonymous: false,
  revealLocal: '',
  allowReadReceipt: true,
})

const recipient = ref<PublicUser | null>(null)
const recipientError = ref<'not_found' | null>(null)
const errors = ref<Record<string, string>>({})
const banner = ref('')

watchDebounced(
  () => form.handle.trim(),
  async (handle) => {
    recipient.value = null
    recipientError.value = null
    if (handle.length < 3) return
    try {
      recipient.value = await usersApi.byHandle(handle)
    } catch (error) {
      recipientError.value = isApiError(error) && error.status === 404 ? 'not_found' : null
    }
  },
  { debounce: 400 },
)

const canSubmit = computed(() => Boolean(recipient.value) && !send.isPending.value)

async function submit() {
  banner.value = ''
  errors.value = {}
  try {
    const deliveries = await send.mutateAsync({
      id: id.value,
      key: idempotencyKey,
      input: {
        recipients: [{ postal_handle: form.handle.trim() }],
        delivery: {
          tier: form.tier,
          arrive_at: form.scheduleArrival ? toIsoZ(form.arriveLocal) : null,
          arrive_timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        },
        is_anonymous: form.isAnonymous,
        reveal_sender_at: form.isAnonymous && form.revealLocal ? toIsoZ(form.revealLocal) : null,
        allow_read_receipt: form.allowReadReceipt,
      },
    })
    ui.pushToast('success', t('send.done', { count: deliveries.length }))
    void router.push({ name: 'outbox' })
  } catch (error) {
    errors.value = fieldErrors(error)
    banner.value = messageFor(error)
  }
}
</script>

<template>
  <AppLayout>
    <section class="mx-auto flex max-w-lg flex-col gap-5">
      <h1 class="text-xl">{{ t('send.title') }}</h1>
      <p v-if="letterQuery.data.value" class="text-sm text-[var(--text-muted)]">
        {{ letterQuery.data.value.title || t('editor.untitled') }}
      </p>

      <form class="flex flex-col gap-5" novalidate @submit.prevent="submit">
        <AlertBox v-if="banner" kind="error">{{ banner }}</AlertBox>

        <div class="flex flex-col gap-1">
          <BaseInput
            v-model="form.handle"
            :label="t('send.recipient')"
            :hint="t('send.recipientHint')"
            :error="errors['recipients.0.postal_handle'] || errors.recipients"
          />
          <p v-if="recipient" class="text-sm text-[var(--color-sage-500)]">
            {{ t('send.recipientFound', { name: recipient.display_name }) }}
          </p>
          <p v-else-if="recipientError === 'not_found'" class="text-sm text-[var(--color-wax-500)]">
            {{ t('send.recipientNotFound') }}
          </p>
        </div>

        <fieldset class="flex flex-col gap-2">
          <legend class="text-sm font-medium">{{ t('send.tier') }}</legend>
          <label
            v-for="tier in tiers"
            :key="tier"
            class="flex items-start gap-2 rounded-md border border-[var(--border-soft)] p-2 text-sm"
          >
            <input
              v-model="form.tier"
              type="radio"
              :value="tier"
              class="mt-0.5 accent-[var(--accent)]"
            />
            <span>
              <span class="font-medium">{{ t(`send.tiers.${tier}.name`) }}</span>
              <span class="block text-[var(--text-muted)]">{{ t(`send.tiers.${tier}.hint`) }}</span>
            </span>
          </label>
        </fieldset>

        <BaseCheckbox v-model="form.scheduleArrival" :label="t('send.scheduleArrival')" />
        <BaseInput
          v-if="form.scheduleArrival"
          v-model="form.arriveLocal"
          type="datetime-local"
          :label="t('send.arriveAt')"
          :error="errors['delivery.arrive_at']"
        />

        <BaseCheckbox v-model="form.isAnonymous" :label="t('send.anonymous')" />
        <BaseInput
          v-if="form.isAnonymous"
          v-model="form.revealLocal"
          type="datetime-local"
          :label="t('send.revealAt')"
          :hint="t('send.revealHint')"
        />

        <BaseCheckbox v-model="form.allowReadReceipt" :label="t('send.readReceipt')" />

        <BaseButton type="submit" :loading="send.isPending.value" :disabled="!canSubmit" block>
          {{ t('send.submit') }}
        </BaseButton>
      </form>
    </section>
  </AppLayout>
</template>
