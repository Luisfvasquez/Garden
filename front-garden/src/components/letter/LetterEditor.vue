<script setup lang="ts">
import { watch } from 'vue'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { useI18n } from 'vue-i18n'
import type { TiptapDoc } from '@/types/api'
import { EMPTY_DOC, letterExtensions } from './letterExtensions'

const props = defineProps<{ modelValue: TiptapDoc; disabled?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [doc: TiptapDoc] }>()
const { t } = useI18n()

const editor = useEditor({
  extensions: letterExtensions,
  content: props.modelValue ?? EMPTY_DOC,
  editable: !props.disabled,
  editorProps: { attributes: { class: 'letter-prose', 'aria-label': t('editor.aria') } },
  onUpdate: ({ editor }) => emit('update:modelValue', editor.getJSON() as TiptapDoc),
})

watch(
  () => props.disabled,
  (d) => editor.value?.setEditable(!d),
)

// Adopt external content only when it genuinely differs (e.g. loaded draft).
watch(
  () => props.modelValue,
  (doc) => {
    if (editor.value && JSON.stringify(doc) !== JSON.stringify(editor.value.getJSON())) {
      editor.value.commands.setContent(doc ?? EMPTY_DOC, { emitUpdate: false })
    }
  },
)

const marks = [
  { name: 'bold', icon: 'B', cls: 'font-bold' },
  { name: 'italic', icon: 'I', cls: 'italic' },
  { name: 'underline', icon: 'U', cls: 'underline' },
] as const

function toggle(kind: 'bold' | 'italic' | 'underline' | 'blockquote' | 'horizontalRule') {
  const chain = editor.value?.chain().focus()
  if (!chain) return
  if (kind === 'blockquote') chain.toggleBlockquote().run()
  else if (kind === 'horizontalRule') chain.setHorizontalRule().run()
  else chain.toggleMark(kind).run()
}
</script>

<template>
  <div class="rounded-lg border border-[var(--border-soft)] bg-[var(--surface-raised)]">
    <div
      class="flex flex-wrap gap-1 border-b border-[var(--border-soft)] p-2"
      role="toolbar"
      :aria-label="t('editor.toolbar')"
    >
      <button
        v-for="m in marks"
        :key="m.name"
        type="button"
        class="size-8 rounded text-sm hover:bg-[var(--surface-sunken)]"
        :class="[m.cls, { 'bg-[var(--surface-sunken)]': editor?.isActive(m.name) }]"
        :aria-pressed="editor?.isActive(m.name) ?? false"
        :aria-label="t(`editor.${m.name}`)"
        :disabled="disabled"
        @click="toggle(m.name)"
      >
        {{ m.icon }}
      </button>
      <button
        type="button"
        class="size-8 rounded text-sm hover:bg-[var(--surface-sunken)]"
        :class="{ 'bg-[var(--surface-sunken)]': editor?.isActive('blockquote') }"
        :aria-pressed="editor?.isActive('blockquote') ?? false"
        :aria-label="t('editor.quote')"
        :disabled="disabled"
        @click="toggle('blockquote')"
      >
        &rdquo;
      </button>
      <button
        type="button"
        class="size-8 rounded text-sm hover:bg-[var(--surface-sunken)]"
        :aria-label="t('editor.divider')"
        :disabled="disabled"
        @click="toggle('horizontalRule')"
      >
        &mdash;
      </button>
    </div>

    <EditorContent :editor="editor" class="min-h-64 p-4" />
  </div>
</template>

<style>
.letter-prose {
  font-family: var(--font-serif);
  font-size: 1.05rem;
  line-height: 1.8;
  outline: none;
}
.letter-prose blockquote {
  border-left: 3px solid var(--border-soft);
  padding-left: 1rem;
  color: var(--text-muted);
  font-style: italic;
}
.letter-prose hr {
  border: none;
  border-top: 1px solid var(--border-soft);
  margin: 1.5rem 0;
}
.letter-prose p {
  margin: 0 0 0.75rem;
}
</style>
