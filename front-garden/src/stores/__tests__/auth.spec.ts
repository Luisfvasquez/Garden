import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ApiError } from '@/api/client'
import { useAuthStore } from '../auth'
import type { Me } from '@/types/api'

const fakeMe = (overrides: Partial<Me> = {}): Me => ({
  id: 'u1',
  name: 'Violet',
  pen_name: null,
  email: 'violet@example.com',
  postal_handle: 'violet-e4f2',
  avatar_url: null,
  bio: null,
  role: 'client',
  status: 'active',
  email_verified: true,
  country_code: null,
  timezone: 'UTC',
  locale: 'es',
  accepts_random_letters: false,
  has_doll_profile: false,
  unread_mailbox_count: 0,
  created_at: '2026-01-01T00:00:00Z',
  ...overrides,
})

const mocks = vi.hoisted(() => {
  type AnyAsync = (...args: never[]) => Promise<unknown>
  return {
    me: vi.fn<AnyAsync>(),
    login: vi.fn<AnyAsync>(),
    logout: vi.fn<AnyAsync>(),
    register: vi.fn<AnyAsync>(),
    resendVerification: vi.fn<AnyAsync>(),
  }
})

vi.mock('@/api/auth', () => ({ authApi: mocks }))

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('auth store', () => {
  it('hydrate loads the current user and marks itself ready', async () => {
    mocks.me.mockResolvedValue(fakeMe())
    const auth = useAuthStore()

    await auth.hydrate()

    expect(auth.isAuthenticated).toBe(true)
    expect(auth.isVerified).toBe(true)
    expect(auth.ready).toBe(true)
  })

  it('hydrate treats a 401 as "signed out", not an error', async () => {
    mocks.me.mockRejectedValue(new ApiError(401, 'UNAUTHENTICATED', 'no'))
    const auth = useAuthStore()

    await auth.hydrate()

    expect(auth.user).toBeNull()
    expect(auth.ready).toBe(true)
  })

  it('login authenticates then refreshes the profile', async () => {
    mocks.login.mockResolvedValue(undefined)
    mocks.me.mockResolvedValue(fakeMe({ name: 'Refreshed' }))
    const auth = useAuthStore()

    await auth.login({ email: 'a@b.co', password: 'x' })

    expect(mocks.login).toHaveBeenCalledOnce()
    expect(auth.user?.name).toBe('Refreshed')
  })

  it('logout clears the user even if the request fails', async () => {
    mocks.logout.mockRejectedValue(new ApiError(500, 'SERVER_ERROR', 'boom'))
    const auth = useAuthStore()
    auth.user = fakeMe()

    await auth.logout()

    expect(auth.user).toBeNull()
  })

  it('derives isDoll from has_doll_profile', async () => {
    mocks.me.mockResolvedValue(fakeMe({ has_doll_profile: true }))
    const auth = useAuthStore()

    await auth.hydrate()

    expect(auth.isDoll).toBe(true)
  })
})
