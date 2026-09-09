import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import type { InternalAxiosRequestConfig } from 'axios'
import { api } from '../client'
import { supportApi } from '../support'

let seen: InternalAxiosRequestConfig

beforeEach(() => {
  document.cookie = 'XSRF-TOKEN=test'
  api.defaults.adapter = async (config) => {
    seen = config
    return { data: { data: [] }, status: 200, statusText: 'OK', headers: {}, config }
  }
})

afterEach(() => {
  api.defaults.adapter = undefined as never
})

describe('supportApi.list', () => {
  it('hits the public endpoint and unwraps data', async () => {
    const result = await supportApi.list({ country_code: 'ES', topic: 'self_harm' })

    expect(seen.url).toBe('/support-resources')
    expect(seen.params).toEqual({ country_code: 'ES', topic: 'self_harm' })
    expect(result).toEqual([])
  })

  it('omits empty params rather than sending blanks', async () => {
    await supportApi.list({ country_code: null })

    expect(seen.params).toEqual({ country_code: undefined, topic: undefined })
  })
})
