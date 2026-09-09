import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'
import { mount } from '@vue/test-utils'
import { ApiError } from '@/api/client'
import { useAutosave } from '../useAutosave'

const update = vi.hoisted(() => vi.fn<() => Promise<unknown>>())
vi.mock('@/api/letters', () => ({ lettersApi: { update } }))

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
})
afterEach(() => vi.useRealTimers())

describe('useAutosave', () => {
  it('debounces writes and reports "saved"', async () => {
    const h = harness('l1')

    h.payload.value = { ...h.payload.value, title: 'b' }
    await nextTick()
    h.payload.value = { ...h.payload.value, title: 'c' }
    await nextTick()

    expect(update).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(60)

    expect(update).toHaveBeenCalledTimes(1)
    expect(update).toHaveBeenLastCalledWith('l1', expect.objectContaining({ title: 'c' }))
    expect(h.api.state.value).toBe('saved')
  })

  it('does nothing while there is no letter id yet', async () => {
    const h = harness(undefined)
    h.payload.value = { ...h.payload.value, title: 'x' }
    await vi.advanceTimersByTimeAsync(60)
    expect(update).not.toHaveBeenCalled()
  })

  it('surfaces an error state on failure', async () => {
    update.mockRejectedValueOnce(new ApiError(500, 'SERVER_ERROR', 'boom'))
    const h = harness('l1')

    h.payload.value = { ...h.payload.value, title: 'z' }
    await vi.advanceTimersByTimeAsync(60)

    expect(h.api.state.value).toBe('error')
    expect(h.api.lastError.value).toBe('SERVER_ERROR')
  })
})
