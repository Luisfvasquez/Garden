import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import type { InternalAxiosRequestConfig } from 'axios'
import { api } from '../client'
import { randomApi } from '../random'

let seen: InternalAxiosRequestConfig

beforeEach(() => {
  document.cookie = 'XSRF-TOKEN=test'
  api.defaults.adapter = async (config) => {
    seen = config
    return { data: { data: {} }, status: 200, statusText: 'OK', headers: {}, config }
  }
})

afterEach(() => {
  api.defaults.adapter = undefined as never
})

describe('randomApi', () => {
  it('sends a bottle with an Idempotency-Key header', async () => {
    await randomApi.send('l1', { tier: 'standard', message_to_stranger: null }, 'key-123')

    expect(seen.url).toBe('/letters/l1/send-random')
    expect(seen.headers?.['Idempotency-Key']).toBe('key-123')
  })

  it('posts an anonymous reply to the mailbox delivery', async () => {
    await randomApi.replyAnonymous('d9', { body: { type: 'doc', content: [] } })

    expect(seen.method).toBe('post')
    expect(seen.url).toBe('/mailbox/d9/reply-anonymous')
  })

  it('opens correspondence on a delivery', async () => {
    await randomApi.openCorrespondence('d9')

    expect(seen.url).toBe('/mailbox/d9/open-correspondence')
  })
})
