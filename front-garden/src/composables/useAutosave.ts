import { ref, toValue, type MaybeRefOrGetter } from 'vue'
import { watchDebounced } from '@vueuse/core'
import { lettersApi } from '@/api/letters'
import { isApiError } from '@/api/client'
import { markDraftSynced, putLocalDraft, type DraftPayload } from '@/lib/db'

export type AutosaveState = 'idle' | 'saving' | 'saved' | 'pending' | 'error'

/**
 * Debounced autosave for the letter editor. Writes to IndexedDB **before** the
 * network attempt, so nothing typed is ever lost — offline or on reload
 * (docs/pwa-offline.md). A network failure leaves state `pending`; a real API
 * error (4xx) is `error`.
 */
export function useAutosave(
  letterId: MaybeRefOrGetter<string | undefined>,
  payload: MaybeRefOrGetter<DraftPayload>,
  options: { delay?: number } = {},
) {
  const state = ref<AutosaveState>('idle')
  const lastSavedAt = ref<Date | null>(null)
  const lastError = ref<string | null>(null)

  async function save() {
    const id = toValue(letterId)
    if (!id) return

    const snapshot = toValue(payload)
    const at = Date.now()

    state.value = 'saving'
    lastError.value = null
    await putLocalDraft(id, snapshot)

    try {
      await lettersApi.update(id, snapshot)
      await markDraftSynced(id, at)
      state.value = 'saved'
      lastSavedAt.value = new Date()
    } catch (error) {
      if (isApiError(error) && error.code !== 'NETWORK') {
        state.value = 'error'
        lastError.value = error.code
      } else {
        state.value = 'pending' // kept in IndexedDB, will retry when back online
        lastError.value = 'OFFLINE'
      }
    }
  }

  watchDebounced(() => toValue(payload), save, { debounce: options.delay ?? 5000, deep: true })

  return { state, lastSavedAt, lastError, saveNow: save }
}
