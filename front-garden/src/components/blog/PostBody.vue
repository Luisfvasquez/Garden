<script setup lang="ts">
import { computed } from 'vue'
import type { TiptapDoc } from '@/types/api'

const props = defineProps<{ body: TiptapDoc }>()

interface Node {
  type?: string
  text?: string
  content?: Node[]
}

function textOf(node: Node): string {
  if (node.text) return node.text
  return (node.content ?? []).map(textOf).join('')
}

const blocks = computed<{ kind: string; text: string }[]>(() => {
  const doc = props.body as Node
  return (doc.content ?? []).map((n) => ({ kind: n.type ?? 'paragraph', text: textOf(n) }))
})
</script>

<template>
  <div class="flex flex-col gap-3 font-serif text-[var(--text)]">
    <template v-for="(b, i) in blocks" :key="i">
      <hr v-if="b.kind === 'horizontalRule'" class="border-[var(--border-soft)]" />
      <blockquote
        v-else-if="b.kind === 'blockquote'"
        class="border-l-2 border-[var(--accent)] pl-3 italic text-[var(--text-muted)]"
      >
        {{ b.text }}
      </blockquote>
      <p v-else class="leading-relaxed whitespace-pre-line">{{ b.text }}</p>
    </template>
  </div>
</template>
