<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { LetterStyle, StyleCatalog } from '@/types/api'

const props = defineProps<{ modelValue: LetterStyle; catalog: StyleCatalog }>()
const emit = defineEmits<{ 'update:modelValue': [style: LetterStyle] }>()
const { t } = useI18n()

const dims = [
  { field: 'paper', list: 'papers' },
  { field: 'font', list: 'fonts' },
  { field: 'ink', list: 'inks' },
  { field: 'stamp', list: 'stamps' },
  { field: 'border', list: 'borders' },
] as const

function set(patch: Partial<LetterStyle>) {
  emit('update:modelValue', { ...props.modelValue, ...patch })
}

function setSeal(patch: { color?: string; sigil?: string }) {
  set({ seal: { type: 'wax', ...props.modelValue.seal, ...patch } })
}

const seal = computed(() => props.modelValue.seal ?? {})
</script>

<template>
  <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
    <label v-for="d in dims" :key="d.field" class="flex flex-col gap-1 text-xs">
      <span class="text-[var(--text-muted)]">{{ t(`style.${d.field}`) }}</span>
      <select
        class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5 text-sm text-[var(--text)]"
        :value="modelValue[d.field] ?? ''"
        @change="set({ [d.field]: ($event.target as HTMLSelectElement).value || undefined })"
      >
        <option value="">{{ t('style.none') }}</option>
        <option
          v-for="opt in catalog[d.list]"
          :key="opt.key"
          :value="opt.key"
          :disabled="opt.locked"
        >
          {{ opt.name }}
        </option>
      </select>
    </label>

    <label class="flex flex-col gap-1 text-xs">
      <span class="text-[var(--text-muted)]">{{ t('style.seal') }}</span>
      <select
        class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5 text-sm text-[var(--text)]"
        :value="seal.color ?? ''"
        @change="setSeal({ color: ($event.target as HTMLSelectElement).value || undefined })"
      >
        <option value="">{{ t('style.none') }}</option>
        <option v-for="opt in catalog.seals" :key="opt.key" :value="opt.key">{{ opt.name }}</option>
      </select>
    </label>

    <label class="flex flex-col gap-1 text-xs">
      <span class="text-[var(--text-muted)]">{{ t('style.sigil') }}</span>
      <select
        class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5 text-sm text-[var(--text)]"
        :value="seal.sigil ?? ''"
        @change="setSeal({ sigil: ($event.target as HTMLSelectElement).value || undefined })"
      >
        <option value="">{{ t('style.none') }}</option>
        <option v-for="opt in catalog.sigils" :key="opt.key" :value="opt.key">
          {{ opt.name }}
        </option>
      </select>
    </label>
  </div>
</template>
