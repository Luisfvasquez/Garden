<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMutation } from '@tanstack/vue-query'
import { reportsApi } from '@/api/reports'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import BaseButton from '@/components/ui/BaseButton.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import type { ReportCategory, ReportableType } from '@/types/api'

/**
 * Reportar contenido. Regla de producto: bloqueo y reporte accesibles desde
 * cada carta, post y perfil (checklist de lanzamiento).
 *
 * `self_harm` no es una denuncia, es una petición de ayuda para otra persona:
 * el backend la escala como crítica y **nunca** borra el contenido por ella
 * (backend-garden/docs/moderacion.md). Por eso aquí se presenta aparte y con
 * un texto que no habla de "infracción".
 */
const props = defineProps<{
  open: boolean
  reportableType: ReportableType
  reportableId?: string
  postalHandle?: string | null
}>()

const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const { messageFor } = useApiError()
const ui = useUiStore()

const category = ref<ReportCategory | ''>('')
const details = ref('')

const categories: ReportCategory[] = [
  'harassment',
  'sexual',
  'hate',
  'violence',
  'spam',
  'minor_safety',
  'self_harm',
  'other',
]

// Empezar de cero en cada apertura: un motivo heredado del reporte anterior
// sería un error silencioso con consecuencias para otra persona.
watch(
  () => props.open,
  (open) => {
    if (open) {
      category.value = ''
      details.value = ''
    }
  },
)

const report = useMutation({
  mutationFn: () =>
    reportsApi.create({
      reportable_type: props.reportableType,
      // Una persona se identifica por handle; el contenido, por id.
      ...(props.reportableType === 'user'
        ? { reportable_handle: props.postalHandle ?? undefined }
        : { reportable_id: props.reportableId }),
      category: category.value as ReportCategory,
      details: details.value.trim() || null,
    }),
  onSuccess: () => {
    ui.pushToast('success', t('safety.report.sent'))
    emit('close')
  },
  onError: (error) => ui.pushToast('error', messageFor(error)),
})

function submit() {
  if (!category.value) return
  report.mutate()
}
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    role="dialog"
    aria-modal="true"
    :aria-label="t('safety.report.title')"
    @click.self="emit('close')"
    @keydown.esc="emit('close')"
  >
    <div class="w-full max-w-md rounded-lg border border-[var(--border-soft)] bg-[var(--surface)] p-5">
      <h2 class="mb-1 font-serif text-xl">{{ t('safety.report.title') }}</h2>
      <p class="mb-4 text-sm text-[var(--text-muted)]">{{ t('safety.report.intro') }}</p>

      <form class="flex flex-col gap-4" @submit.prevent="submit">
        <fieldset class="flex flex-col gap-2">
          <legend class="mb-1 text-sm font-medium">{{ t('safety.report.reason') }}</legend>

          <label
            v-for="c in categories"
            :key="c"
            class="flex cursor-pointer items-start gap-2 text-sm"
          >
            <input v-model="category" type="radio" :value="c" name="report-category" class="mt-1" />
            <span>
              {{ t(`safety.report.category.${c}`) }}
              <span
                v-if="c === 'self_harm' || c === 'minor_safety'"
                class="block text-xs text-[var(--text-muted)]"
              >
                {{ t(`safety.report.categoryHint.${c}`) }}
              </span>
            </span>
          </label>
        </fieldset>

        <label class="flex flex-col gap-1">
          <span class="text-sm">{{ t('safety.report.details') }}</span>
          <textarea
            v-model="details"
            rows="3"
            maxlength="2000"
            class="w-full rounded border border-[var(--border-soft)] bg-[var(--surface-sunken)] px-3 py-2 text-sm"
          />
        </label>

        <AlertBox v-if="category === 'self_harm'" kind="info">
          {{ t('safety.report.selfHarmNotice') }}
        </AlertBox>

        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="rounded border border-[var(--border-soft)] px-3 py-1.5 text-sm"
            @click="emit('close')"
          >
            {{ t('common.cancel') }}
          </button>
          <BaseButton type="submit" :loading="report.isPending.value" :disabled="!category">
            {{ t('safety.report.submit') }}
          </BaseButton>
        </div>
      </form>
    </div>
  </div>
</template>
