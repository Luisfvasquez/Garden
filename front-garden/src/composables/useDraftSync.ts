import { onScopeDispose } from 'vue'
import { lettersApi } from '@/api/letters'
import { isApiError } from '@/api/client'
import { db, markDraftSynced, pendingDrafts } from '@/lib/db'

let flushing = false

/**
 * Replays drafts saved to IndexedDB while offline. Runs on startup, whenever the
 * connection comes back, and on a slow interval as a safety net.
 * Conflict policy: the local copy wins on flush — it holds the user's latest
 * keystrokes (docs/pwa-offline.md).
 */
export async function flushPendingDrafts(): Promise<void> {
  if (flushing || !navigator.onLine) return
  flushing = true
  try {
    for (const draft of await pendingDrafts()) {
      try {
        await lettersApi.update(draft.letterId, draft.payload)
        await markDraftSynced(draft.letterId, draft.updatedAt)
      } catch (error) {
        // A letter that no longer exists (deleted / sent) can't be synced — drop it.
        if (isApiError(error) && (error.status === 404 || error.status === 409)) {
          await db.drafts.delete(draft.letterId)
        } else {
          break // offline again or transient — try later
        }
      }
    }
  } finally {
    flushing = false
  }
}

export function useDraftSync(): void {
  const onOnline = () => void flushPendingDrafts()
  const timer = window.setInterval(onOnline, 30_000)
  window.addEventListener('online', onOnline)
  void flushPendingDrafts()

  onScopeDispose(() => {
    window.clearInterval(timer)
    window.removeEventListener('online', onOnline)
  })
}
