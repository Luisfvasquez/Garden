import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import type { InternalAxiosRequestConfig } from 'axios'
import { api } from '../client'
import { dollChatApi } from '../dollChat'
import { isChannelOpen } from '@/composables/useDollChat'
import type { DollRequestStatus } from '@/types/api'

let seen: InternalAxiosRequestConfig

beforeEach(() => {
  document.cookie = 'XSRF-TOKEN=test'
  api.defaults.adapter = async (config) => {
    seen = config
    return { data: { data: { id: 'm1' } }, status: 200, statusText: 'OK', headers: {}, config }
  }
})

afterEach(() => {
  api.defaults.adapter = undefined as never
})

describe('dollChatApi', () => {
  it('reads the transcript', async () => {
    await dollChatApi.messages('r1')
    expect(seen.method).toBe('get')
    expect(seen.url).toBe('/doll-requests/r1/messages')
  })

  it('pages the transcript by cursor', async () => {
    await dollChatApi.messages('r1', { cursor: 'abc' })
    expect(seen.params).toEqual({ cursor: 'abc' })
  })

  it('sends a message', async () => {
    await dollChatApi.send('r1', 'hola')
    expect(seen.method).toBe('post')
    expect(seen.url).toBe('/doll-requests/r1/messages')
    expect(JSON.parse(seen.data)).toEqual({ body: 'hola' })
  })

  it('shares a draft without inventing a version number', async () => {
    const body = { type: 'doc', content: [] }
    await dollChatApi.shareDraft('r1', { draft_payload: { title: 'x', body } })
    expect(seen.url).toBe('/doll-requests/r1/drafts')
    // The server assigns the version; the client must never send one.
    expect(JSON.parse(seen.data)).not.toHaveProperty('draft_payload.version')
  })

  it('approves a draft', async () => {
    await dollChatApi.approveDraft('r1', 'd9')
    expect(seen.method).toBe('post')
    expect(seen.url).toBe('/doll-requests/r1/drafts/d9/approve')
  })
})

describe('isChannelOpen', () => {
  it('is open only while the request is being worked on', () => {
    expect(isChannelOpen('in_progress')).toBe(true)
    expect(isChannelOpen('awaiting_client')).toBe(true)
  })

  it.each<DollRequestStatus>(['pending', 'accepted', 'completed', 'rejected', 'expired', 'cancelled'])(
    'is closed while %s',
    (status) => {
      expect(isChannelOpen(status)).toBe(false)
    },
  )
})
