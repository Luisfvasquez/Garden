/*
 * TEMPORAL. Se reemplaza por `src/types/api.d.ts`, generado desde
 * `../docs/api/openapi.json` (Scramble) — ver `npm run api:types` y
 * `docs/setup.md`. Mantener al mínimo lo que el front necesita para tipar.
 */

export type Locale = 'es' | 'en'
export type UserRole = 'client' | 'doll' | 'moderator' | 'admin'
export type UserStatus = 'active' | 'suspended' | 'deactivated' | 'deleted'
export type ThemePreference = 'light' | 'dark' | 'system'

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

export interface FeaturePayload {
  features: Record<string, boolean>
}

export interface SupportResource {
  id: string
  country_code: string | null
  topic: string | null
  name: string
  description: string | null
  phone: string | null
  sms: string | null
  url: string | null
  hours: string | null
  languages: string[]
  is_global: boolean
}

// --- Letters -----------------------------------------------------------------

/** Tiptap document — opaque JSON; the backend sanitises it. */
export type TiptapDoc = import('@tiptap/core').JSONContent

export interface LetterStyle {
  paper?: string
  texture?: string
  font?: string
  ink?: string
  stamp?: string
  border?: string
  flourish?: boolean
  seal?: { type?: 'wax'; color?: string; sigil?: string }
}

export type LetterKind = 'direct' | 'random' | 'unaddressed' | 'doll_draft'
export type ModerationStatus = 'pending' | 'approved' | 'flagged' | 'rejected'

export interface LetterAttachment {
  id: string
  type: 'image' | 'audio' | 'pressed_flower'
  url: string
  original_name: string
  mime_type: string
  size_bytes: number
  metadata: Record<string, unknown>
  created_at: string
}

export interface Letter {
  id: string
  title: string | null
  body: TiptapDoc
  style: LetterStyle
  kind: LetterKind
  word_count: number
  reading_time_minutes: number
  is_locked: boolean
  moderation_status: ModerationStatus
  in_reply_to_delivery_id: string | null
  attachments?: LetterAttachment[]
  deliveries_count?: number
  created_at: string
  updated_at: string
}

type StyleEntry = { key: string; name: string; locked: boolean } & Record<string, unknown>
export interface StyleCatalog {
  papers: StyleEntry[]
  fonts: StyleEntry[]
  inks: StyleEntry[]
  seals: StyleEntry[]
  sigils: StyleEntry[]
  stamps: StyleEntry[]
  borders: StyleEntry[]
}

// --- Deliveries / mailbox --------------------------------------------------

export type DeliveryStatus = 'queued' | 'in_transit' | 'delivered' | 'read' | 'cancelled' | 'failed'
export type TransitTier = 'express' | 'standard' | 'slow'

export interface RecipientSummary {
  display_name: string
  postal_handle: string | null
}

export interface Delivery {
  id: string
  letter_id: string
  status: DeliveryStatus
  tier: TransitTier
  is_anonymous: boolean
  scheduled_for: string
  dispatched_at: string | null
  estimated_delivery_at: string | null
  delivered_at: string | null
  read_at: string | null
  can_cancel: boolean
  recipient: RecipientSummary | null
}

export interface TrackingEvent {
  event: string
  occurred_at: string
  label: string
  metadata?: { office?: string }
}

export interface SenderSummary {
  display_name: string
  postal_handle: string | null
  avatar_url: string | null
}

export interface MailboxEnvelope {
  id: string
  status: 'delivered' | 'read'
  is_opened: boolean
  is_archived: boolean
  is_favorite: boolean
  is_anonymous: boolean
  envelope: { paper: string | null; seal: LetterStyle['seal'] | null; stamp: string | null }
  sender: SenderSummary
  title: string | null
  has_attachments: boolean
  in_reply_to_delivery_id: string | null
  delivered_at: string | null
}

export interface MailboxLetter {
  id: string
  status: 'delivered' | 'read'
  is_archived: boolean
  is_favorite: boolean
  is_anonymous: boolean
  sender: SenderSummary
  title: string | null
  body: TiptapDoc
  style: LetterStyle
  word_count: number
  reading_time_minutes: number
  attachments: LetterAttachment[]
  in_reply_to_delivery_id: string | null
  delivered_at: string | null
  read_at: string | null
  is_random?: boolean
  can_reply_anonymously?: boolean
  correspondence?: {
    recipient_accepted: boolean
    sender_accepted: boolean
    opened: boolean
  } | null
}

// --- Bottle at sea (random letters) ------------------------------------------

export interface RandomQuota {
  daily_limit: number
  daily_used: number
  daily_remaining: number
  weekly_limit: number
  weekly_used: number
  weekly_remaining: number
  eligible: boolean
  reasons: string[]
}

export interface RandomSendResult {
  id: string
  status: DeliveryStatus | 'held'
  estimated_delivery_at: string | null
  recipient: null
  quota_remaining_today: number
  held_for_review: boolean
}

// --- Blog ------------------------------------------------------------------

export type PostType = 'shared_letter' | 'unaddressed_letter' | 'poem' | 'reflection'
export type ReactionType = 'heart' | 'tear' | 'flower' | 'candle'
export type ConsentStatus = 'not_required' | 'pending' | 'granted' | 'denied'

export interface PostAuthor {
  display_name: string
  postal_handle: string | null
}

export interface Post {
  id: string
  type: PostType
  slug: string
  title: string
  body: TiptapDoc
  testimonial: string | null
  tags: { slug: string; label: string }[]
  is_anonymous: boolean
  author: PostAuthor
  comments_enabled: boolean
  published_at: string | null
  created_at: string
  my_reactions: ReactionType[]
  moderation_status?: string
  consent_status?: ConsentStatus
  consent_denied_until?: string
}

export interface PostComment {
  id: string
  post_id: string
  parent_id: string | null
  body: string
  is_anonymous: boolean
  author: PostAuthor
  replies?: PostComment[]
  created_at: string
}

export interface BlogTag {
  slug: string
  label: string
  usage_count: number
}

export interface CreatePostInput {
  type: PostType
  letter_delivery_id?: string
  title: string
  body: TiptapDoc
  testimonial?: string | null
  tags?: string[]
  is_anonymous?: boolean
  comments_enabled?: boolean
  visibility?: 'public' | 'unlisted'
}

// --- Auto Memory Dolls -------------------------------------------------------

export type DollRateType = 'free' | 'per_letter' | 'hourly'

export interface DollSummary {
  postal_handle: string
  display_name: string
  avatar_url: string | null
}

export interface DollProfile {
  id: string
  doll: DollSummary
  headline: string
  bio: string | null
  specialties: string[]
  languages: string[]
  tone_tags: string[]
  rate_type: DollRateType
  rate_amount: number | null
  currency: string | null
  is_available: boolean
  rating_avg: number
  rating_count: number
  completed_requests_count: number
  response_time_avg_minutes: number | null
  portfolio: unknown[]
  verified_at?: string | null
  max_concurrent_requests?: number
}

export interface DollProfileInput {
  headline: string
  bio?: string | null
  specialties?: string[]
  languages?: string[]
  tone_tags?: string[]
  rate_type?: DollRateType
  rate_amount?: number | null
  currency?: string | null
}

// --- Notifications / blocks ---------------------------------------------------

export interface AppNotification {
  id: string
  type: string
  data: Record<string, unknown>
  read_at: string | null
  created_at: string
}

export interface Block {
  id: string
  reason: string | null
  created_at: string
  user: {
    id: string
    postal_handle: string | null
    display_name: string
    avatar_url: string | null
  }
}

// --- Schedules (recurring letters) --------------------------------------

export type RecurrenceType = 'once' | 'yearly' | 'monthly' | 'weekly' | 'custom_dates'
export type LeapDayPolicy = 'feb_28' | 'mar_01'
export type ScheduleStatus = 'active' | 'paused' | 'completed'
export type OccurrenceStatus =
  | 'empty'
  | 'pending'
  | 'queued'
  | 'in_transit'
  | 'delivered'
  | 'cancelled'

export interface Schedule {
  id: string
  name: string
  recipient: { postal_handle: string; display_name: string } | null
  recurrence_type: RecurrenceType
  anchor_date: string
  local_time: string
  timezone: string
  occurrences_total: number | null
  custom_dates: string[]
  leap_day_policy: LeapDayPolicy
  trigger_type: 'date'
  letter_id: string | null
  tier: TransitTier
  is_anonymous: boolean
  status: ScheduleStatus
  created_at: string
  updated_at: string
}

export interface Occurrence {
  date: string
  local_time: string
  runs_at: string
  letter: { id: string; title: string | null } | null
  delivery_id: string | null
  status: OccurrenceStatus
}

export interface OccurrenceTimeline {
  data: Occurrence[]
  meta: { total: number; filled: number; delivered: number }
}

export interface CreateScheduleInput {
  name: string
  recipient: { postal_handle: string }
  recurrence_type: RecurrenceType
  anchor_date: string
  local_time: string
  timezone: string
  occurrences_total?: number | null
  custom_dates?: string[]
  letter_id?: string | null
  leap_day_policy?: LeapDayPolicy
  tier?: TransitTier
  is_anonymous?: boolean
}

// --- Envelopes -------------------------------------------------------------

export interface Resource<T> {
  data: T
}

export interface CursorPage<T> {
  data: T[]
  meta: { per_page: number; next_cursor: string | null; has_more: boolean }
}

export interface ApiErrorBody {
  message: string
  error_code: string
  errors?: Record<string, string[]>
  meta?: { request_id?: string; [key: string]: unknown }
}
