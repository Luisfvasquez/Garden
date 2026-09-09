<script setup lang="ts">
import { computed } from 'vue'
import { generateHTML } from '@tiptap/core'
import type { LetterStyle, StyleCatalog, TiptapDoc } from '@/types/api'
import { letterExtensions } from './letterExtensions'

const props = defineProps<{
  body: TiptapDoc
  style: LetterStyle
  catalog?: StyleCatalog
  title?: string | null
}>()

const html = computed(() => {
  try {
    return generateHTML(props.body ?? { type: 'doc', content: [] }, letterExtensions)
  } catch {
    return ''
  }
})

function entry(list: StyleCatalog[keyof StyleCatalog] | undefined, key?: string) {
  return list?.find((e) => e.key === key)
}

const fontFamily = computed(
  () =>
    (entry(props.catalog?.fonts, props.style.font)?.css_family as string) ?? 'var(--font-serif)',
)
const inkColor = computed(
  () => (entry(props.catalog?.inks, props.style.ink)?.hex as string) ?? 'var(--text)',
)
const paperClass = computed(() => `paper-${props.style.paper ?? 'parchment'}`)
</script>

<template>
  <article
    class="letter-paper mx-auto max-w-2xl rounded-sm px-8 py-10 shadow-sm sm:px-12"
    :class="paperClass"
    :style="{ fontFamily, color: inkColor }"
  >
    <h1 v-if="title" class="mb-6 text-2xl" :style="{ fontFamily }">{{ title }}</h1>
    <!-- Safe: server-sanitised doc re-rendered from the restricted Tiptap schema. -->
    <!-- eslint-disable-next-line vue/no-v-html -->
    <div class="letter-prose" v-html="html" />
  </article>
</template>

<style scoped>
.letter-paper {
  background: var(--color-parchment-50, #fdfbf5);
  background-image: repeating-linear-gradient(
    0deg,
    rgba(0, 0, 0, 0.015),
    rgba(0, 0, 0, 0.015) 1px,
    transparent 1px,
    transparent 28px
  );
}
.paper-kraft {
  background-color: #d8c3a0;
}
.paper-ivory_smooth {
  background-color: #fffef8;
  background-image: none;
}
.letter-prose {
  font-size: 1.05rem;
  line-height: 1.9;
}
.letter-prose :deep(blockquote) {
  border-left: 3px solid currentColor;
  opacity: 0.85;
  padding-left: 1rem;
  font-style: italic;
}
.letter-prose :deep(hr) {
  border: none;
  border-top: 1px solid currentColor;
  opacity: 0.4;
  margin: 1.75rem 0;
}
.letter-prose :deep(p) {
  margin: 0 0 0.9rem;
}
</style>
