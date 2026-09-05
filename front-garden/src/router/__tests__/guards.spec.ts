import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import type { RouteLocationNormalized } from 'vue-router'
import { authGuard } from '../guards'
import { useAuthStore } from '@/stores/auth'

function target(meta: RouteLocationNormalized['meta'], fullPath = '/x'): RouteLocationNormalized {
  return { meta, fullPath, name: 'x', path: fullPath } as RouteLocationNormalized
}

beforeEach(() => {
  setActivePinia(createPinia())
})

function primeAuth(state: { authed: boolean; verified?: boolean; doll?: boolean }) {
  const auth = useAuthStore()
  auth.ready = true
  auth.hydrate = vi.fn<() => Promise<void>>().mockResolvedValue(undefined)
  auth.user = state.authed
    ? ({
        email_verified: state.verified ?? true,
        has_doll_profile: state.doll ?? false,
      } as never)
    : null
  return auth
}

describe('authGuard', () => {
  it('sends a signed-out visitor from an auth route to login with a redirect', async () => {
    primeAuth({ authed: false })

    const result = await authGuard.call(
      undefined,
      target({ auth: true }, '/escritorio'),
      target({}),
      () => {},
    )

    expect(result).toEqual({ name: 'login', query: { redirect: '/escritorio' } })
  })

  it('bounces a signed-in user away from a guest route', async () => {
    primeAuth({ authed: true })

    const result = await authGuard.call(undefined, target({ guest: true }), target({}), () => {})

    expect(result).toEqual({ name: 'desk' })
  })

  it('routes an unverified user to the verification screen', async () => {
    primeAuth({ authed: true, verified: false })

    const result = await authGuard.call(
      undefined,
      target({ auth: true, verified: true }),
      target({}),
      () => {},
    )

    expect(result).toEqual({ name: 'verify-email' })
  })

  it('keeps a non-doll out of doll-only routes', async () => {
    primeAuth({ authed: true, doll: false })

    const result = await authGuard.call(
      undefined,
      target({ auth: true, role: 'doll' }),
      target({}),
      () => {},
    )

    expect(result).toEqual({ name: 'desk' })
  })

  it('lets a verified user through', async () => {
    primeAuth({ authed: true, verified: true })

    const result = await authGuard.call(
      undefined,
      target({ auth: true, verified: true }),
      target({}),
      () => {},
    )

    expect(result).toBe(true)
  })

  it('hydrates once when the store is not ready yet', async () => {
    const auth = primeAuth({ authed: false })
    auth.ready = false

    await authGuard.call(undefined, target({}), target({}), () => {})

    expect(auth.hydrate).toHaveBeenCalledOnce()
  })
})
