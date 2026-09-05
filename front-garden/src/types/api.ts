/*
 * TEMPORAL. Se reemplaza por `src/types/api.d.ts`, generado desde
 * `../docs/api/openapi.json` (Scramble) — ver `npm run api:types` y
 * `docs/setup.md`. Mantener este archivo al mínimo: solo lo que Fase 0
 * necesita para tipar la sesión. No añadir modelos de dominio aquí.
 */

export type Locale = 'es' | 'en'
export type UserRole = 'client' | 'doll' | 'moderator' | 'admin'
export type UserStatus = 'active' | 'suspended' | 'deactivated' | 'deleted'
export type ThemePreference = 'light' | 'dark' | 'system'

/** `GET /api/v1/me` — `data`. */
export interface Me {
  id: string
  name: string
  pen_name: string | null
  email: string
  postal_handle: string
  avatar_url: string | null
  bio: string | null
  role: UserRole
  status: UserStatus
  email_verified: boolean
  country_code: string | null
  timezone: string
  locale: Locale
  accepts_random_letters: boolean
  has_doll_profile: boolean
  unread_mailbox_count: number
  created_at: string
}

/** `GET /api/v1/users/{postal_handle}` — `data`. */
export interface PublicUser {
  postal_handle: string
  display_name: string
  avatar_url: string | null
  bio: string | null
  country_code: string | null
  member_since: string
  accepts_random_letters: boolean
  is_blocked_by_me: boolean
}

/** `GET/PATCH /api/v1/me/settings` — `data`. */
export interface UserSettings {
  notify_email: boolean
  notify_push: boolean
  notify_on_arrival: boolean
  notify_on_dispatch_confirm: boolean
  notify_on_doll_message: boolean
  notify_on_blog_comment: boolean
  share_read_receipts: boolean
  quiet_hours_start: string | null
  quiet_hours_end: string | null
  theme: ThemePreference
  preferred_paper_style: string | null
  show_transit_countdown: boolean
  accepts_random_letters: boolean
  random_letters_daily_cap: number
}

/** `GET /api/v1/features` — `data`. */
export interface FeaturePayload {
  features: Record<string, boolean>
}

/** Single-resource envelope. */
export interface Resource<T> {
  data: T
}

/** Error envelope — docs/api/_convenciones.md. */
export interface ApiErrorBody {
  message: string
  error_code: string
  errors?: Record<string, string[]>
  meta?: { request_id?: string; [key: string]: unknown }
}
