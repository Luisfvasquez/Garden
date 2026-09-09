import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi, type LoginPayload, type RegisterPayload } from '@/api/auth'
import { isApiError } from '@/api/client'
import type { Me } from '@/types/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<Me | null>(null)
  /** True once the first `hydrate()` has settled, so guards can wait for it. */
  const ready = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const isVerified = computed(() => user.value?.email_verified === true)
  const isDoll = computed(() => user.value?.has_doll_profile === true)

  /** Load the current session. A 401 just means "not signed in". */
  async function hydrate(): Promise<void> {
    try {
      user.value = await authApi.me()
    } catch (error) {
      if (isApiError(error) && error.status === 401) user.value = null
      else if (import.meta.env.DEV) console.warn('auth.hydrate failed', error)
    } finally {
      ready.value = true
    }
  }

  async function refresh(): Promise<void> {
    user.value = await authApi.me()
  }

  /** Adopt a fresh `me` payload returned by a profile/avatar mutation. */
  function setUser(me: Me): void {
    user.value = me
  }

  async function login(payload: LoginPayload): Promise<void> {
    await authApi.login(payload)
    await refresh()
  }

  async function register(payload: RegisterPayload): Promise<Me> {
    const created = await authApi.register(payload)
    // Registration does not open a session; the user still has to sign in.
    return created
  }

  async function logout(): Promise<void> {
    // The local session is gone regardless of what the server says.
    try {
      await authApi.logout()
    } catch (error) {
      if (import.meta.env.DEV) console.warn('auth.logout request failed', error)
    } finally {
      user.value = null
    }
  }

  async function resendVerification(): Promise<void> {
    await authApi.resendVerification()
  }

  /** Called by the 401 interceptor hook. */
  function clear(): void {
    user.value = null
    ready.value = true
  }

  return {
    user,
    ready,
    isAuthenticated,
    isVerified,
    isDoll,
    hydrate,
    refresh,
    setUser,
    login,
    register,
    logout,
    resendVerification,
    clear,
  }
})
