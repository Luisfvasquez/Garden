import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/client'
import { flushPendingDrafts } from '../useDraftSync'

const update = vi.hoisted(() => vi.fn<() => Promise<unknown>>())
const pendingDrafts = vi.hoisted(() => vi.fn<() => Promise<unknown[]>>())
const markDraftSynced = vi.hoisted(() => vi.fn<() => Promise<void>>())
const del = vi.hoisted(() => vi.fn<() => Promise<void>>())

vi.mock('@/api/letters', () => ({ lettersApi: { update } }))
vi.mock('@/lib/db', () => ({
  pendingDrafts,
  markDraftSynced,
  db: { drafts: { delete: del } },
}))

const draft = (letterId: string) => ({ letterId, payload: { title: 't' }, updatedAt: 1, synced: 0 })

beforeEach(() => {
  vi.clearAllMocks()
  vi.stubGlobal('navigator', { onLine: true })
  update.mockResolvedValue({})
  markDraftSynced.mockResolvedValue(undefined)
})

describe('flushPendingDrafts', () => {
  it('pushes every pending draft and marks it synced', async () => {
    pendingDrafts.mockResolvedValue([draft('a'), draft('b')])

    await flushPendingDrafts()

    expect(update).toHaveBeenCalledTimes(2)
    expect(markDraftSynced).toHaveBeenCalledWith('a', 1)
    expect(markDraftSynced).toHaveBeenCalledWith('b', 1)
  })

  it('drops a draft whose letter is gone (404/409)', async () => {
    pendingDrafts.mockResolvedValue([draft('a')])
    update.mockRejectedValueOnce(new ApiError(404, 'NOT_FOUND', 'gone'))

    await flushPendingDrafts()

    expect(del).toHaveBeenCalledWith('a')
  })

  it('stops and keeps drafts on a transient/offline error', async () => {
    pendingDrafts.mockResolvedValue([draft('a'), draft('b')])
    update.mockRejectedValueOnce(new ApiError(0, 'NETWORK', 'offline'))

    await flushPendingDrafts()

    expect(update).toHaveBeenCalledTimes(1)
    expect(del).not.toHaveBeenCalled()
    expect(markDraftSynced).not.toHaveBeenCalled()
  })

  it('does nothing while offline', async () => {
    vi.stubGlobal('navigator', { onLine: false })
    pendingDrafts.mockResolvedValue([draft('a')])

    await flushPendingDrafts()

    expect(pendingDrafts).not.toHaveBeenCalled()
  })
})
