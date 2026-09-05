import axios, {
  AxiosError,
  type AxiosInstance,
  type AxiosResponse,
  type InternalAxiosRequestConfig,
} from 'axios'
import type { ApiErrorBody } from '@/types/api'

/**
 * Normalised transport error. UI reacts to `code` (a stable `error_code`),
 * never to `message` — docs/api/_convenciones.md.
 */
export class ApiError extends Error {
  constructor(
    readonly status: number,
    readonly code: string,
    message: string,
    readonly fields: Record<string, string[]> = {},
    readonly meta: Record<string, unknown> = {},
    readonly retryAfter: number | null = null,
  ) {
    super(message)
    this.name = 'ApiError'
  }

  /** First message for a given field, if the server returned one. */
  field(name: string): string | undefined {
    return this.fields[name]?.[0]
  }

  static fromAxios(error: AxiosError<ApiErrorBody>): ApiError {
    const res = error.response
    if (!res) {
      return new ApiError(0, 'NETWORK', error.message || 'network error')
    }
    const body = res.data ?? ({} as ApiErrorBody)
    const retryAfter = Number(res.headers?.['retry-after'])
    return new ApiError(
      res.status,
      body.error_code ?? `HTTP_${res.status}`,
      body.message ?? error.message,
      body.errors ?? {},
      body.meta ?? {},
      Number.isFinite(retryAfter) ? retryAfter : null,
    )
  }
}

export function isApiError(value: unknown): value is ApiError {
  return value instanceof ApiError
}

const MUTATING = new Set(['post', 'put', 'patch', 'delete'])

function hasCsrfCookie(): boolean {
  return document.cookie.split('; ').some((c) => c.startsWith('XSRF-TOKEN='))
}

let csrfInFlight: Promise<void> | null = null

/** Fetch the Sanctum CSRF cookie once; concurrent callers share the request. */
export function ensureCsrfCookie(force = false): Promise<void> {
  if (!force && hasCsrfCookie()) return Promise.resolve()
  csrfInFlight ??= axios
    .get('/sanctum/csrf-cookie', { withCredentials: true })
    .then(() => undefined)
    .finally(() => {
      csrfInFlight = null
    })
  return csrfInFlight
}

export const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
    'X-App-Version': import.meta.env.VITE_APP_VERSION ?? '0.0.0',
  },
})

api.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
  if (MUTATING.has((config.method ?? 'get').toLowerCase())) {
    await ensureCsrfCookie()
  }
  return config
})

type RetriableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean }

/** Assigned by main.ts so the interceptor can react without importing the app. */
export const clientHooks: {
  onUnauthenticated: () => void
  onRateLimited: (retryAfter: number | null) => void
  onUpgradeRequired: () => void
} = {
  onUnauthenticated: () => {},
  onRateLimited: () => {},
  onUpgradeRequired: () => {},
}

api.interceptors.response.use(
  (response: AxiosResponse) => response,
  async (error: AxiosError<ApiErrorBody>) => {
    const config = error.config as RetriableConfig | undefined
    const status = error.response?.status

    if (status === 419 && config && !config._csrfRetried) {
      config._csrfRetried = true
      await ensureCsrfCookie(true)
      return api(config)
    }

    const apiError = ApiError.fromAxios(error)

    if (status === 401) clientHooks.onUnauthenticated()
    else if (status === 429) clientHooks.onRateLimited(apiError.retryAfter)
    else if (status === 426) clientHooks.onUpgradeRequired()

    return Promise.reject(apiError)
  },
)

/** Unwrap a `{ data: T }` envelope. */
export async function fetchResource<T>(url: string): Promise<T> {
  const { data } = await api.get<{ data: T }>(url)
  return data.data
}
