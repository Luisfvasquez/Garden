<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { MailboxEnvelope } from '@/types/api'
import WaxSeal from '@/components/letter/WaxSeal.vue'

defineProps<{ envelope: MailboxEnvelope }>()
const { t } = useI18n()

const paperTint: Record<string, string> = {
  parchment: '#f7f1e3',
  cream_laid: '#f4ead2',
  ivory_smooth: '#fffef8',
  kraft: '#d8c3a0',
}

const sealHex: Record<string, string> = {
  wax_burgundy: '#8b2635',
  wax_forest: '#3f5a3a',
  wax_gold: '#a1815c',
}
</script>

<template>
  <article
    class="relative flex flex-col gap-2 rounded-md border border-[var(--border-soft)] p-4 transition-transform"
    :style="{
      background: paperTint[envelope.envelope.paper ?? 'parchment'] ?? paperTint.parchment,
    }"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <p class="truncate font-serif text-lg text-ink-800">
          {{ envelope.title || t('mailbox.noSubject') }}
        </p>
        <p class="text-sm text-ink-700/80">
          {{ t('mailbox.from', { name: envelope.sender.display_name }) }}
        </p>
      </div>
      <WaxSeal
        v-if="envelope.envelope.seal?.color"
        :color="sealHex[envelope.envelope.seal?.color ?? '']"
        :sigil="envelope.envelope.seal?.sigil"
        :size="40"
      />
    </div>

    <div class="flex items-center gap-2 text-xs text-ink-700/70">
      <span v-if="!envelope.is_opened" class="rounded bg-ink-800/10 px-1.5 py-0.5">
        {{ t('mailbox.unopened') }}
      </span>
      <span v-if="envelope.is_favorite" aria-hidden="true">★</span>
      <span v-if="envelope.has_attachments" aria-hidden="true">📎</span>
    </div>
  </article>
</template>
