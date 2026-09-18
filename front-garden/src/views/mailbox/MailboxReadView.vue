<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { usePreferredReducedMotion } from '@vueuse/core'
import type { MailboxLetter } from '@/types/api'
import {
  useArchiveLetter,
  useFavoriteLetter,
  useOpenLetter,
  useReplyToLetter,
} from '@/composables/useMailbox'
import { useStyleCatalog } from '@/composables/useLetters'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import { formatDateTime } from '@/lib/datetime'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import LetterPaper from '@/components/letter/LetterPaper.vue'
import WaxSeal from '@/components/letter/WaxSeal.vue'
import SafetyActions from '@/components/safety/SafetyActions.vue'
import RandomLetterActions from '@/components/mailbox/RandomLetterActions.vue'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const ui = useUiStore()
const { messageFor } = useApiError()
const reduced = usePreferredReducedMotion()

const id = computed(() => route.params.id as string)
const catalog = useStyleCatalog()
const open = useOpenLetter()
const archive = useArchiveLetter()
const favorite = useFavoriteLetter()
const reply = useReplyToLetter()

const letter = ref<MailboxLetter | null>(null)
const error = ref<unknown>(null)
const sealBroken = ref(false)

onMounted(async () => {
  try {
    letter.value = await open.mutateAsync(id.value)
    if (reduced.value === 'reduce') sealBroken.value = true
  } catch (e) {
    error.value = e
  }
})

function breakSeal() {
  sealBroken.value = true
}

function doArchive() {
  if (!letter.value) return
  archive.mutate(
    { id: id.value, archived: !letter.value.is_archived },
    {
      onSuccess: (env) => {
        if (letter.value) letter.value.is_archived = env.is_archived
      },
    },
  )
}

function doFavorite() {
  if (!letter.value) return
  favorite.mutate(
    { id: id.value, favorite: !letter.value.is_favorite },
    {
      onSuccess: (env) => {
        if (letter.value) letter.value.is_favorite = env.is_favorite
      },
    },
  )
}

function doReply() {
  reply.mutate(id.value, {
    onSuccess: (draft) => router.push({ name: 'letter-edit', params: { id: draft.id } }),
    onError: (e) => ui.pushToast('error', messageFor(e)),
  })
}
</script>

<template>
  <AppLayout>
    <div v-if="open.isPending.value && !letter" class="flex justify-center py-16">
      <SpinnerDots :label="t('common.loading')" />
    </div>

    <AlertBox v-else-if="error" kind="error">{{ messageFor(error) }}</AlertBox>

    <section v-else-if="letter" class="flex flex-col gap-4">
      <!-- Sealed envelope; opening it is the moment. -->
      <button
        v-if="!sealBroken"
        type="button"
        class="mx-auto flex flex-col items-center gap-4 rounded-lg border border-[var(--border-soft)] bg-[var(--surface-sunken)] px-10 py-16"
        @click="breakSeal"
      >
        <WaxSeal :sigil="letter.style.seal?.sigil" :size="88" />
        <span class="text-sm text-[var(--text-muted)]">{{ t('mailbox.tapToOpen') }}</span>
      </button>

      <template v-else>
        <header class="flex flex-wrap items-center gap-3">
          <div>
            <p class="text-sm text-[var(--text-muted)]">
              {{ t('mailbox.from', { name: letter.sender.display_name }) }}
            </p>
            <p class="text-xs text-[var(--text-muted)]">
              {{ formatDateTime(letter.delivered_at) }}
            </p>
          </div>
          <div class="ml-auto flex gap-2">
            <BaseButton variant="ghost" @click="doFavorite">
              {{ letter.is_favorite ? t('mailbox.unfavorite') : t('mailbox.favorite') }}
            </BaseButton>
            <BaseButton variant="ghost" @click="doArchive">
              {{ letter.is_archived ? t('mailbox.unarchive') : t('mailbox.archive') }}
            </BaseButton>
            <BaseButton :loading="reply.isPending.value" @click="doReply">
              {{ t('mailbox.reply') }}
            </BaseButton>
          </div>
        </header>

        <RandomLetterActions v-if="letter.is_random" :letter="letter" />

        <LetterPaper
          :body="letter.body"
          :style="letter.style"
          :catalog="catalog.data.value"
          :title="letter.title"
        />

        <footer class="flex flex-wrap items-center gap-3 border-t border-[var(--border-soft)] pt-3">
          <a
            :href="`/api/v1/mailbox/${letter.id}/pdf`"
            class="text-xs text-[var(--text-muted)] underline hover:text-[var(--text)]"
          >
            {{ t('mailbox.downloadPdf') }}
          </a>
          <!-- `postal_handle` es null si el buzón sigue ocultando a quien escribe:
               entonces se puede reportar pero no bloquear (ADR-0007). -->
          <SafetyActions
            class="ml-auto"
            reportable-type="letter_delivery"
            :reportable-id="letter.id"
            :postal-handle="letter.sender.postal_handle"
          />
        </footer>
      </template>
    </section>
  </AppLayout>
</template>
