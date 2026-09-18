import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import type { InternalAxiosRequestConfig } from 'axios'
import { api } from '../client'
import { reportsApi } from '../reports'

let seen: InternalAxiosRequestConfig

beforeEach(() => {
  document.cookie = 'XSRF-TOKEN=test'
  api.defaults.adapter = async (config) => {
    seen = config
    return { data: { data: { id: 'r1' } }, status: 201, statusText: 'Created', headers: {}, config }
  }
})

afterEach(() => {
  api.defaults.adapter = undefined as never
})

describe('reportsApi', () => {
  it('reports content by id', async () => {
    await reportsApi.create({
      reportable_type: 'public_post',
      reportable_id: 'p1',
      category: 'spam',
    })
    expect(seen.method).toBe('post')
    expect(seen.url).toBe('/reports')
    expect(JSON.parse(seen.data)).toMatchObject({ reportable_type: 'public_post', reportable_id: 'p1' })
  })

  it('reports a person by handle, never by uuid', async () => {
    await reportsApi.create({
      reportable_type: 'user',
      reportable_handle: 'cattleya-4f21',
      category: 'harassment',
    })
    const body = JSON.parse(seen.data)
    expect(body.reportable_handle).toBe('cattleya-4f21')
    expect(body.reportable_id).toBeUndefined()
  })

  it('carries the optional details through', async () => {
    await reportsApi.create({
      reportable_type: 'comment',
      reportable_id: 'c1',
      category: 'hate',
      details: 'Contexto.',
    })
    expect(JSON.parse(seen.data).details).toBe('Contexto.')
  })
})
