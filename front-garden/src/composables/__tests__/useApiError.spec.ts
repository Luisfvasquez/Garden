import { describe, expect, it } from 'vitest'
import { createI18n } from 'vue-i18n'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { ApiError } from '@/api/client'
import { useApiError } from '../useApiError'
import es from '@/locales/es.json'

function run<T>(fn: (helpers: ReturnType<typeof useApiError>) => T): T {
  let out!: T
  const i18n = createI18n({ legacy: false, locale: 'es', messages: { es } })
  const Harness = defineComponent({
    setup() {
      out = fn(useApiError())
      return () => h('div')
    },
  })
  mount(Harness, { global: { plugins: [i18n] } })
  return out
}

describe('useApiError', () => {
  it('maps a known error_code to its localized string', () => {
    const msg = run(({ messageFor }) =>
      messageFor(new ApiError(422, 'INVALID_CREDENTIALS', 'server text')),
    )
    expect(msg).toBe(es.errors.INVALID_CREDENTIALS)
  })

  it('prefers the validation message when a 422 carries field errors', () => {
    const msg = run(({ messageFor }) =>
      messageFor(new ApiError(422, 'VALIDATION_FAILED', 'x', { email: ['bad'] })),
    )
    expect(msg).toBe(es.errors.validation)
  })

  it('falls back to generic for an unknown code', () => {
    const msg = run(({ messageFor }) => messageFor(new ApiError(500, 'WAT', 'x')))
    expect(msg).toBe(es.errors.generic)
  })

  it('reports a network error distinctly', () => {
    const msg = run(({ messageFor }) => messageFor(new ApiError(0, 'NETWORK', 'x')))
    expect(msg).toBe(es.errors.network)
  })

  it('extracts field errors as a flat map', () => {
    const fields = run(({ fieldErrors }) =>
      fieldErrors(new ApiError(422, 'VALIDATION_FAILED', 'x', { email: ['taken'], name: ['req'] })),
    )
    expect(fields).toEqual({ email: 'taken', name: 'req' })
  })
})
