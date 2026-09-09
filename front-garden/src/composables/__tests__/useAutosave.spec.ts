import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'
import { mount } from '@vue/test-utils'
import { ApiError } from '@/api/client'
import { useAutosave } from '../useAutosave'

const update = vi.hoisted(() => vi.fn<() => Promise<unknown>>())
const putLocalDraft = vi.hoisted(() => vi.fn<() => Promise<void>>())
const markDraftSynced = vi.hoisted(() => vi.fn<() => Promise<void>>())

vi.mock('@/api/letters', () => ({ lettersApi: { update } }))
vi.mock('@/lib/db', () => ({ putLocalDraft, markDraftSynced }))

function harness(id: string | undefined) {
  const payload = ref({ title: 'a', body: { type: 'doc' }, style: {} })
  let api!: ReturnType<typeof useAutosave>
  const Cmp = defineComponent({
    setup() {
      api = useAutosave(
        () => id,
        () => payload.value,
        { delay: 50 },
      )
      return () => h('div')
    },
  })
  mount(Cmp)
  return {
    payload,
    get api() {
      return api
    },
  }
}

beforeEach(() => {
  vi.useFakeTimers()
  update.mockReset().mockResolvedValue({})
  putLocalDraft.mockReset().mockResolvedValue(undefined)
  markDraftSynced.mockReset().mockResolvedValue(undefined)
})
afterEach(() => vi.useRealTimers())

describe('useAutosave', () => {
  it('writes to IndexedDB first, then debounces the network write and reports "saved"', async () => {
    const h = harness('l1')

    h.payload.value = { ...h.payload.value, title: 'b' }
    await nextTick()
    h.payload.value = { ...h.payload.value, title: 'c' }
    await nextTick()

    expect(update).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(60)

    expect(putLocalDraft).toHaveBeenCalledWith('l1', expect.objectContaining({ title: 'c' }))
    expect(update).toHaveBeenCalledTimes(1)
    expect(h.api.state.value).toBe('saved')
  })

  it('does nothing while there is no letter id yet', async () => {
    const h = harness(undefined)
    h.payload.value = { ...h.payload.value, title: 'x' }
    await vi.advanceTimersByTimeAsync(60)
    expect(putLocalDraft).not.toHaveBeenCalled()
    expect(update).not.toHaveBeenCalled()
  })

  it('stays "pending" (kept in IndexedDB) when the network is down', async () => {
    update.mockRejectedValueOnce(new ApiError(0, 'NETWORK', 'offline'))
    const h = harness('l1')

    h.payload.value = { ...h.payload.value, title: 'z' }
    await vi.advanceTimersByTimeAsync(60)

    expect(putLocalDraft).toHaveBeenCalled()
    expect(h.api.state.value).toBe('pending')
    expect(h.api.lastError.value).toBe('OFFLINE')
  })

  it('surfaces "error" on a real API failure', async () => {
    update.mockRejectedValueOnce(new ApiError(422, 'VALIDATION_FAILED', 'boom'))
    const h = harness('l1')

    h.payload.value = { ...h.payload.value, title: 'z' }
    await vi.advanceTimersByTimeAsync(60)

    expect(h.api.state.value).toBe('error')
    expect(h.api.lastError.value).toBe('VALIDATION_FAILED')
  })
})
