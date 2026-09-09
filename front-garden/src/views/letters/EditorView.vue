<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import type { LetterStyle } from '@/types/api'
import { EMPTY_DOC } from '@/components/letter/letterExtensions'
import {
  useCreateDraft,
  useDeleteLetter,
  useLetter,
  useStyleCatalog,
} from '@/composables/useLetters'
import { useAutosave } from '@/composables/useAutosave'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import AppLayout from '@/layouts/AppLayout.vue'
import LetterEditor from '@/components/letter/LetterEditor.vue'
import LetterPaper from '@/components/letter/LetterPaper.vue'
import StylePicker from '@/components/letter/StylePicker.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const ui = useUiStore()
const { messageFor } = useApiError()

const id = computed(() => (route.params.id as string | undefined) || undefined)

const draft = reactive({
  title: '' as string,
  body: structuredClone(EMPTY_DOC),
  style: {} as LetterStyle,
})
const showPreview = ref(false)
const showStyle = ref(false)
const seeded = ref(false)

const letterQuery = useLetter(id)
const catalogQuery = useStyleCatalog()
const createDraft = useCreateDraft()
const deleteDraft = useDeleteLetter()

// No id → create a draft and move to its URL.
if (!id.value) {
  createDraft.mutate(
    { body: structuredClone(EMPTY_DOC) },
    { onSuccess: (letter) => router.replace({ name: 'letter-edit', params: { id: letter.id } }) },
  )
}

watch(
  () => letterQuery.data.value,
  (letter) => {
    if (!letter || seeded.value) return
    if (letter.is_locked) {
      void router.replace({ name: 'desk' })
      return
    }
    draft.title = letter.title ?? ''
    draft.body = letter.body ?? structuredClone(EMPTY_DOC)
    draft.style = letter.style ?? {}
    seeded.value = true
  },
  { immediate: true },
)

const autosave = useAutosave(id, () => ({
  title: draft.title || null,
  body: draft.body,
  style: draft.style,
}))

const savingLabel = computed(
  () =>
    ({
      idle: '',
      saving: t('editor.saving'),
      saved: t('editor.saved'),
      error: t('editor.saveError'),
    })[autosave.state.value],
)

async function goToSend() {
  await autosave.saveNow()
  if (autosave.state.value === 'error') {
    ui.pushToast('error', t('editor.saveError'))
    return
  }
  void router.push({ name: 'letter-send', params: { id: id.value } })
}

function removeDraft() {
  if (!id.value) return
  deleteDraft.mutate(id.value, {
    onSuccess: () => router.replace({ name: 'desk' }),
    onError: (e) => ui.pushToast('error', messageFor(e)),
  })
}
</script>

<template>
  <AppLayout>
    <div v-if="letterQuery.isPending.value && id" class="flex justify-center py-16">
      <SpinnerDots :label="t('common.loading')" />
    </div>

    <AlertBox v-else-if="letterQuery.isError.value" kind="error">
      {{ messageFor(letterQuery.error.value) }}
    </AlertBox>

    <section v-else class="flex flex-col gap-4">
      <header class="flex flex-wrap items-center gap-3">
        <h1 class="text-xl">{{ t('editor.title') }}</h1>
        <span class="text-xs text-[var(--text-muted)]" aria-live="polite">{{ savingLabel }}</span>
        <div class="ml-auto flex gap-2">
          <BaseButton variant="ghost" @click="showPreview = !showPreview">
            {{ showPreview ? t('editor.backToEditing') : t('editor.preview') }}
          </BaseButton>
          <BaseButton :disabled="!id" @click="goToSend">{{ t('editor.send') }}</BaseButton>
        </div>
      </header>

      <BaseInput
        v-model="draft.title"
        :label="t('editor.letterTitle')"
        :hint="t('editor.letterTitleHint')"
      />

      <template v-if="!showPreview">
        <button
          type="button"
          class="self-start text-sm text-[var(--accent)] underline underline-offset-4"
          @click="showStyle = !showStyle"
        >
          {{ showStyle ? t('style.hide') : t('style.show') }}
        </button>
        <StylePicker
          v-if="showStyle && catalogQuery.data.value"
          v-model="draft.style"
          :catalog="catalogQuery.data.value"
        />
        <LetterEditor v-model="draft.body" />
      </template>

      <LetterPaper
        v-else
        :body="draft.body"
        :style="draft.style"
        :catalog="catalogQuery.data.value"
        :title="draft.title || null"
      />

      <footer class="flex items-center justify-between border-t border-[var(--border-soft)] pt-4">
        <BaseButton variant="ghost" @click="autosave.saveNow">{{ t('editor.saveNow') }}</BaseButton>
        <button
          type="button"
          class="text-sm text-[var(--color-wax-500)] underline underline-offset-4"
          @click="removeDraft"
        >
          {{ t('editor.discard') }}
        </button>
      </footer>
    </section>
  </AppLayout>
</template>
