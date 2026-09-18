import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import type { InternalAxiosRequestConfig } from 'axios'
import { api } from '../client'
import { blogApi } from '../blog'

let seen: InternalAxiosRequestConfig

beforeEach(() => {
  document.cookie = 'XSRF-TOKEN=test'
  api.defaults.adapter = async (config) => {
    seen = config
    return {
      data: { data: config.method === 'get' ? [] : { slug: 's', my_reactions: [] } },
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

describe('blogApi', () => {
  it('lists the feed with filters', async () => {
    await blogApi.list({ type: 'poem', tag: 'duelo' })
    expect(seen.url).toBe('/posts')
    expect(seen.params).toEqual({ type: 'poem', tag: 'duelo' })
  })

  it('returns the HTTP status with a created post (201 vs 202)', async () => {
    const { status } = await blogApi.create({
      type: 'reflection',
      title: 'x',
      body: { type: 'doc', content: [] },
    })
    expect(status).toBe(201)
    expect(seen.url).toBe('/posts')
  })

  it('adds and removes a reaction, unwrapping my_reactions', async () => {
    await blogApi.react('p1', 'candle')
    expect(seen.url).toBe('/posts/p1/reactions')

    await blogApi.unreact('p1', 'candle')
    expect(seen.method).toBe('delete')
    expect(seen.url).toBe('/posts/p1/reactions/candle')
  })

  it('responds to a consent request by post id', async () => {
    await blogApi.respondConsent('p9', true)
    expect(seen.url).toBe('/consent-requests/p9/respond')
    expect(seen.data).toBe(JSON.stringify({ granted: true }))
  })

  it('passes the search terms through', async () => {
    await blogApi.list({ q: 'cartas a mi padre' })
    expect(seen.params).toMatchObject({ q: 'cartas a mi padre' })
  })

  it('omits q entirely when there is nothing to search', async () => {
    await blogApi.list({ q: undefined, type: 'poem' })
    expect(seen.params.q).toBeUndefined()
  })
})
