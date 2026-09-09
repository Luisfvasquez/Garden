import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import type { InternalAxiosRequestConfig } from 'axios'
import { api } from '../client'
import { pushApi } from '../push'

let seen: InternalAxiosRequestConfig

beforeEach(() => {
  document.cookie = 'XSRF-TOKEN=test'
  api.defaults.adapter = async (config) => {
    seen = config
    return {
      data: { data: { public_key: 'BKey', enabled: true, id: 'sub1', platform: 'web' } },
      status: 201,
      statusText: 'OK',
      headers: {},
      config,
    }
  }
})

afterEach(() => {
  api.defaults.adapter = undefined as never
})

describe('pushApi', () => {
  it('reads the public VAPID key', async () => {
    const key = await pushApi.vapidKey()
    expect(seen.url).toBe('/push/vapid-public-key')
    expect(key.enabled).toBe(true)
  })

  it('registers a web subscription', async () => {
    await pushApi.subscribe({
      platform: 'web',
      endpoint: 'https://example.com/x',
      public_key: 'p',
      auth_token: 'a',
    })
    expect(seen.method).toBe('post')
    expect(seen.url).toBe('/push-subscriptions')
  })

  it('removes a subscription by id', async () => {
    await pushApi.unsubscribe('sub1')
    expect(seen.method).toBe('delete')
    expect(seen.url).toBe('/push-subscriptions/sub1')
  })
})
