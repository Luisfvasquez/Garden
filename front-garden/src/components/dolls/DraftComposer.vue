<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import LetterEditor from '@/components/letter/LetterEditor.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { EMPTY_DOC } from '@/components/letter/letterExtensions'
import type { CreateDollDraftInput, TiptapDoc } from '@/types/api'

/**
 * The Doll shares a version of the letter. Same restricted editor as an
 * ordinary letter — what the client approves is exactly what becomes theirs.
 *
 * The version number is not editable here: the server assigns it, so two
 * drafts can never both claim to be v3.
 */
defineProps<{ busy?: boolean }>()
const emit = defineEmits<{ share: [input: CreateDollDraftInput] }>()

const { t } = useI18n()

const open = ref(false)
const title = ref('')
const note = ref('')
const body = ref<TiptapDoc>(structuredClone(EMPTY_DOC))

function share() {
  emit('share', {
    draft_payload: { title: title.value || null, body: body.value },
    note: note.value || null,
  })
  title.value = ''
  note.value = ''
  body.value = structuredClone(EMPTY_DOC)
  open.value = false
}
</script>

<template>
  <section class="rounded-lg border border-[var(--border-soft)] bg-[var(--surface)] p-3">
    <button
      v-if="!open"
      type="button"
      class="text-sm text-[var(--accent)] underline"
      @click="open = true"
    >
      {{ t('dolls.chat.shareDraft') }}
    </button>

    <form v-else class="flex flex-col gap-3" @submit.prevent="share">
      <BaseInput v-model="title" :label="t('dolls.chat.draftTitle')" />

      <div>
        <p class="mb-1 text-sm">{{ t('dolls.chat.draftBody') }}</p>
        <LetterEditor v-model="body" />
      </div>

      <BaseInput v-model="note" :label="t('dolls.chat.draftNote')" />

      <div class="flex gap-2">
        <BaseButton type="submit" :loading="busy">{{ t('dolls.chat.shareDraftSubmit') }}</BaseButton>
        <button
          type="button"
          class="rounded border border-[var(--border-soft)] px-3 py-1.5 text-sm"
          @click="open = false"
        >
          {{ t('common.cancel') }}
        </button>
      </div>
    </form>
  </section>
</template>
