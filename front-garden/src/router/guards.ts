import type { NavigationGuardWithThis } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

/**
 * Route meta contract:
 *  - `guest`    → only when signed out (signed-in users bounce to the desk)
 *  - `auth`     → requires a session
 *  - `verified` → requires a verified email (everything that sends letters)
 *  - `role`     → requires a derived capability from `GET /me`
 */
declare module 'vue-router' {
  interface RouteMeta {
    guest?: boolean
    auth?: boolean
    verified?: boolean
    role?: 'doll'
  }
}

export const authGuard: NavigationGuardWithThis<undefined> = async (to) => {
  const auth = useAuthStore()

  if (!auth.ready) await auth.hydrate()

  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'desk' }
  }

  if (to.meta.auth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.verified && auth.isAuthenticated && !auth.isVerified) {
    return { name: 'verify-email' }
  }

  if (to.meta.role === 'doll' && !auth.isDoll) {
    return { name: 'desk' }
  }

  return true
}
