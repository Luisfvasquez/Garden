import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import type { InternalAxiosRequestConfig } from 'axios'
import { api } from '../client'
import { dollRequestsApi } from '../dollRequests'

let seen: InternalAxiosRequestConfig

beforeEach(() => {
  document.cookie = 'XSRF-TOKEN=test'
  api.defaults.adapter = async (config) => {
    seen = config
    return { data: { data: { id: 'r1' } }, status: 200, statusText: 'OK', headers: {}, config }
  }
})

afterEach(() => {
  api.defaults.adapter = undefined as never
})

describe('dollRequestsApi', () => {
  it('lists my requests with role/status filters', async () => {
    await dollRequestsApi.list({ role: 'client', status: 'pending' })
    expect(seen.url).toBe('/doll-requests')
    expect(seen.params).toEqual({ role: 'client', status: 'pending' })
  })

  it('fetches a request by id', async () => {
    await dollRequestsApi.get('r1')
    expect(seen.url).toBe('/doll-requests/r1')
  })

  it('creates a request', async () => {
    await dollRequestsApi.create({ doll_handle: 'cattleya', occasion: 'x' })
    expect(seen.method).toBe('post')
    expect(seen.url).toBe('/doll-requests')
  })

  it.each(['accept', 'reject', 'start', 'cancel'] as const)('posts to /%s', async (action) => {
    await dollRequestsApi[action]('r1')
    expect(seen.method).toBe('post')
    expect(seen.url).toBe(`/doll-requests/r1/${action}`)
  })
})
