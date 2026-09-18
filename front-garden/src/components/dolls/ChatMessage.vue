<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatDateTime } from '@/lib/datetime'
import PostBody from '@/components/blog/PostBody.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import type { DollChatMessage } from '@/types/api'

/**
 * One line of the transcript. Three shapes: a text bubble, a platform notice
 * (the contact-exchange warning), and a draft card the client can approve.
 */
const props = defineProps<{
  message: DollChatMessage
  canApprove: boolean
  approving: boolean
}>()

const emit = defineEmits<{ approve: [draftId: string] }>()

const { t } = useI18n()

const isSystem = computed(() => props.message.type === 'system')
const isDraft = computed(() => props.message.type === 'draft')
const flagged = computed(() => props.message.pii_flags.length > 0)
</script>

<template>
  <!-- Platform notice: never attributed to a person. -->
  <li v-if="isSystem" class="self-center max-w-md">
    <p
      class="rounded-lg border border-[var(--color-wax-500)] bg-[var(--surface-sunken)] px-3 py-2 text-center text-xs leading-relaxed text-[var(--text-muted)]"
    >
      {{ message.body }}
    </p>
  </li>

  <!-- A versioned draft. -->
  <li v-else-if="isDraft" class="self-stretch">
    <article class="rounded-lg border border-[var(--accent)] bg-[var(--surface)] p-4">
      <header class="mb-2 flex items-baseline justify-between gap-2">
        <h3 class="font-serif text-lg">
          {{ message.draft_payload?.title || t('dolls.chat.draftUntitled') }}
        </h3>
        <span class="shrink-0 text-xs text-[var(--text-muted)]">
          {{ t('dolls.chat.draftVersion', { n: message.draft_version }) }}
        </span>
      </header>

      <p v-if="message.body" class="mb-3 text-sm italic text-[var(--text-muted)]">{{ message.body }}</p>

      <div class="rounded border border-[var(--border-soft)] bg-[var(--surface-sunken)] p-3">
        <PostBody v-if="message.draft_payload" :body="message.draft_payload.body" />
      </div>

      <footer class="mt-3 flex items-center justify-between gap-2">
        <span class="text-xs text-[var(--text-muted)]">{{ formatDateTime(message.created_at) }}</span>

        <p v-if="message.draft_approved_at" class="text-xs text-[var(--accent)]">
          {{ t('dolls.chat.draftApproved') }}
        </p>
        <BaseButton
          v-else-if="canApprove"
          :loading="approving"
          @click="emit('approve', message.id)"
        >
          {{ t('dolls.chat.approveDraft') }}
        </BaseButton>
      </footer>

      <p v-if="canApprove && !message.draft_approved_at" class="mt-2 text-xs text-[var(--text-muted)]">
        {{ t('dolls.chat.approveHint') }}
      </p>
    </article>
  </li>

  <!-- An ordinary message. -->
  <li v-else class="flex max-w-[80%] flex-col gap-1" :class="message.is_mine ? 'self-end items-end' : 'self-start'">
    <div
      class="rounded-lg px-3 py-2 text-sm leading-relaxed whitespace-pre-line"
      :class="
        message.is_mine
          ? 'bg-[var(--accent)] text-[var(--surface)]'
          : 'border border-[var(--border-soft)] bg-[var(--surface)]'
      "
    >
      {{ message.body }}
    </div>
    <p class="flex items-center gap-2 text-xs text-[var(--text-muted)]">
      <span v-if="!message.is_mine && message.sender">{{ message.sender.display_name }}</span>
      <span>{{ formatDateTime(message.created_at) }}</span>
      <span v-if="flagged" :title="t('dolls.chat.flaggedTitle')" aria-hidden="true">⚠</span>
    </p>
  </li>
</template>
