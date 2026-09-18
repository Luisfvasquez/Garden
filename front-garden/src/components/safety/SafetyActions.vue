<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBlockUser } from '@/composables/useAccount'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import ReportDialog from './ReportDialog.vue'
import type { ReportableType } from '@/types/api'

/**
 * "Reportar" y "bloquear", juntos y discretos, para colgar de una carta, un
 * post, un comentario, un perfil o un mensaje del chat de Dolls.
 *
 * Deliberadamente sin estilo llamativo: tiene que estar **siempre disponible**
 * (checklist de lanzamiento) sin convertir cada pantalla en una invitación a
 * denunciar.
 *
 * `postalHandle` es opcional porque hay sitios donde no hay a quién bloquear:
 * una carta anónima cuyo remitente el buzón sigue ocultando, por ejemplo. Ahí
 * se puede reportar pero no bloquear, y es correcto — bloquear revelaría a
 * quién (ADR-0007, el bloqueo es silencioso).
 */
const props = defineProps<{
  reportableType: ReportableType
  /** Obligatorio salvo para `user`, que se identifica por handle. */
  reportableId?: string
  postalHandle?: string | null
}>()

const { t } = useI18n()
const { messageFor } = useApiError()
const ui = useUiStore()

const showReport = ref(false)
const block = useBlockUser()

function confirmBlock() {
  if (!props.postalHandle) return
  if (!window.confirm(t('safety.block.confirm', { handle: props.postalHandle }))) return

  block
    .mutateAsync({ postal_handle: props.postalHandle })
    .then(() => ui.pushToast('success', t('safety.block.done')))
    .catch((error) => ui.pushToast('error', messageFor(error)))
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 text-xs text-[var(--text-muted)]">
    <button type="button" class="underline hover:text-[var(--text)]" @click="showReport = true">
      {{ t('safety.report.action') }}
    </button>

    <button
      v-if="postalHandle"
      type="button"
      class="underline hover:text-[var(--text)]"
      :disabled="block.isPending.value"
      @click="confirmBlock"
    >
      {{ t('safety.block.action') }}
    </button>

    <ReportDialog
      :open="showReport"
      :reportable-type="reportableType"
      :reportable-id="reportableId"
      :postal-handle="postalHandle"
      @close="showReport = false"
    />
  </div>
</template>
