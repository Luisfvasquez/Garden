import Dexie, { type Table } from 'dexie'
import type { LetterDraftInput } from '@/api/letters'

export type DraftPayload = Pick<LetterDraftInput, 'title' | 'body' | 'style'>

/**
 * A letter draft mirrored locally. IndexedDB is the source of truth until
 * `synced` flips to 1 (docs/pwa-offline.md). `synced` is 0/1, not a boolean,
 * so Dexie can index it.
 */
export interface LocalDraft {
  letterId: string
  payload: DraftPayload
  updatedAt: number
  synced: 0 | 1
}

class EvergardenDB extends Dexie {
  drafts!: Table<LocalDraft, string>

  constructor() {
    super('evergarden')
    this.version(1).stores({ drafts: 'letterId, synced, updatedAt' })
  }
}

export const db = new EvergardenDB()

export async function putLocalDraft(letterId: string, payload: DraftPayload): Promise<void> {
  await db.drafts.put({ letterId, payload, updatedAt: Date.now(), synced: 0 })
}

export async function markDraftSynced(letterId: string, at: number): Promise<void> {
  // Only clear the flag if nothing newer was written while the request was in flight.
  const current = await db.drafts.get(letterId)
  if (current && current.updatedAt <= at) {
    await db.drafts.update(letterId, { synced: 1 })
  }
}

export function pendingDrafts(): Promise<LocalDraft[]> {
  return db.drafts.where('synced').equals(0).toArray()
}
