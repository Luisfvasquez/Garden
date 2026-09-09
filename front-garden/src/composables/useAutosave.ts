import { ref, toValue, type MaybeRefOrGetter } from 'vue'
import { watchDebounced } from '@vueuse/core'
import { lettersApi, type LetterDraftInput } from '@/api/letters'
import { isApiError } from '@/api/client'

export type AutosaveState = 'idle' | 'saving' | 'saved' | 'error'

/**
 * Debounced autosave for the letter editor (docs/sistema-diseno.md: every 5 s).
 * Nunca pierdas texto escrito — on failure the state goes `error` and the caller
 * keeps the content in memory; `saveNow()` retries.
 *
 * TODO (chunk PWA): write to IndexedDB before the network attempt so drafts
 * survive offline and reloads.
 */
export function useAutosave(
  letterId: MaybeRefOrGetter<string | undefined>,
  payload: MaybeRefOrGetter<Pick<LetterDraftInput, 'title' | 'body' | 'style'>>,
  options: { delay?: number } = {},
) {
  const state = ref<AutosaveState>('idle')
  const lastSavedAt = ref<Date | null>(null)
  const lastError = ref<string | null>(null)

  async function save() {
    const id = toValue(letterId)
    if (!id) return

    state.value = 'saving'
    lastError.value = null
    try {
      await lettersApi.update(id, toValue(payload))
      state.value = 'saved'
      lastSavedAt.value = new Date()
    } catch (error) {
      state.value = 'error'
      lastError.value = isApiError(error) ? error.code : 'UNKNOWN'
    }
  }

  watchDebounced(() => toValue(payload), save, {
    debounce: options.delay ?? 5000,
    deep: true,
  })

  return { state, lastSavedAt, lastError, saveNow: save }
}
