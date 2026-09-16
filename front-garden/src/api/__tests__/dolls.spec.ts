import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import type { InternalAxiosRequestConfig } from 'axios'
import { api } from '../client'
import { dollsApi } from '../dolls'

let seen: InternalAxiosRequestConfig

beforeEach(() => {
  document.cookie = 'XSRF-TOKEN=test'
  api.defaults.adapter = async (config) => {
    seen = config
    return { data: { data: { id: 'p1' } }, status: 200, statusText: 'OK', headers: {}, config }
  }
})

afterEach(() => {
  api.defaults.adapter = undefined as never
})

describe('dollsApi', () => {
  it('lists the directory, dropping an undefined availability filter', async () => {
    await dollsApi.list({ specialty: 'duelo' })
    expect(seen.url).toBe('/dolls')
    expect(seen.params).toEqual({ specialty: 'duelo', language: undefined, available: undefined })
  })

  it('sends availability as 0/1', async () => {
    await dollsApi.list({ available: true })
    expect(seen.params.available).toBe(1)

    await dollsApi.list({ available: false })
    expect(seen.params.available).toBe(0)
  })

  it('fetches a profile by postal handle', async () => {
    await dollsApi.get('violet-evergarden')
    expect(seen.url).toBe('/dolls/violet-evergarden')
  })

  it('requests the doll role', async () => {
    await dollsApi.requestRole({ headline: 'x' })
    expect(seen.method).toBe('post')
    expect(seen.url).toBe('/me/doll-profile')
  })

  it('toggles availability', async () => {
    await dollsApi.setAvailability(true)
    expect(seen.url).toBe('/me/doll-profile/availability')
    expect(seen.data).toBe(JSON.stringify({ is_available: true }))
  })
})
