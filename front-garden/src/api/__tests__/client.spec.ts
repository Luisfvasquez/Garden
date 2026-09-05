import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { AxiosError, AxiosHeaders, type AxiosAdapter } from 'axios'
import { api, ApiError, clientHooks, isApiError } from '../client'

/** Minimal adapter: routes by URL to a canned success or failure. */
function adapter(handlers: Record<string, () => unknown>): AxiosAdapter {
  return async (config) => {
    const key = `${(config.method ?? 'get').toUpperCase()} ${config.url}`
    const handler = handlers[key] ?? handlers[config.url ?? '']
    if (!handler) throw new Error(`no handler for ${key}`)
    const result = handler()
    if (result instanceof Error) throw result
    return {
      data: result,
      status: 200,
      statusText: 'OK',
      headers: {},
      config,
    }
  }
}

function axiosFailure(status: number, data: unknown, headers: Record<string, string> = {}) {
  const config = { headers: new AxiosHeaders() }
  return new AxiosError('Request failed', String(status), config as never, null, {
    data,
    status,
    statusText: 'ERR',
    headers,
    config: config as never,
  })
}

beforeEach(() => {
  clientHooks.onUnauthenticated = vi.fn<() => void>()
  clientHooks.onRateLimited = vi.fn<(retryAfter: number | null) => void>()
  clientHooks.onUpgradeRequired = vi.fn<() => void>()
  document.cookie = 'XSRF-TOKEN=test' // skip the CSRF pre-flight
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('api client', () => {
  it('sends Accept and X-App-Version headers', async () => {
    let seen: unknown
    api.defaults.adapter = async (config) => {
      seen = config.headers
      return { data: { ok: true }, status: 200, statusText: 'OK', headers: {}, config }
    }

    await api.get('/health')

    expect((seen as Record<string, string>).Accept).toBe('application/json')
    expect((seen as Record<string, string>)['X-App-Version']).toBeDefined()
  })

  it('normalises an error body into ApiError keyed by error_code', async () => {
    api.defaults.adapter = adapter({
      'POST /auth/login': () =>
        axiosFailure(422, {
          message: 'Bad',
          error_code: 'INVALID_CREDENTIALS',
          errors: { email: ['taken'] },
        }),
    })

    const error = await api.post('/auth/login', {}).catch((e) => e)

    expect(isApiError(error)).toBe(true)
    expect(error).toMatchObject({ status: 422, code: 'INVALID_CREDENTIALS' })
    expect((error as ApiError).field('email')).toBe('taken')
  })

  it('flags a network failure as NETWORK', async () => {
    api.defaults.adapter = adapter({
      'GET /health': () => new AxiosError('Network Error', 'ERR_NETWORK'),
    })

    const error = await api.get('/health').catch((e) => e)

    expect((error as ApiError).code).toBe('NETWORK')
  })

  it('invokes the unauthenticated hook on 401', async () => {
    api.defaults.adapter = adapter({
      'GET /me': () => axiosFailure(401, { message: 'no', error_code: 'UNAUTHENTICATED' }),
    })

    await api.get('/me').catch(() => {})

    expect(clientHooks.onUnauthenticated).toHaveBeenCalledOnce()
  })

  it('invokes the rate-limit hook with Retry-After on 429', async () => {
    api.defaults.adapter = adapter({
      'GET /me': () =>
        axiosFailure(429, { message: 'slow', error_code: 'RATE_LIMITED' }, { 'retry-after': '30' }),
    })

    await api.get('/me').catch(() => {})

    expect(clientHooks.onRateLimited).toHaveBeenCalledWith(30)
  })
})
