import type { components } from './openapi'
import type {
  Block,
  Delivery,
  DollChatMessage,
  DollProfile,
  DollRequest,
  DollRequestParty,
  Letter,
  LetterAttachment,
  MailboxEnvelope,
  MailboxLetter,
  Me,
  Post,
  PostComment,
  PublicUser,
  Schedule,
  SupportResource,
  UserSettings,
} from './api'

/**
 * Conformance layer between the hand-written app types (`./api`) and the
 * OpenAPI document the backend generates (`./openapi`, from
 * `docs/api/openapi.json` via `npm run api:types`).
 *
 * WHY THIS EXISTS INSTEAD OF JUST USING THE GENERATED TYPES
 *
 * The generated types are structurally correct but deliberately weaker than
 * what the app wants. Scramble reads `$this->status->value` and can only say
 * `string`; it cannot know the value is one of eight literals. The app depends
 * on those unions — for exhaustive `switch`es, for `t(\`dolls.status.${s}\`)`
 * i18n keys, and to catch typos at compile time. Swapping `DollRequestStatus`
 * for `string` would be a downgrade, not progress.
 *
 * So `./api` stays hand-curated and narrower, and this file makes sure it can
 * no longer drift *silently*: every assertion below fails `npm run typecheck`
 * if the backend adds, removes or renames a field. The error names the
 * offending key.
 *
 * When one fails: regenerate (`npm run api:types`), then update `./api` to
 * match. Do not edit `./openapi.d.ts` — it is generated.
 *
 * Nothing here is emitted at runtime; it is types and one `true` per check.
 */

type Schema<K extends keyof components['schemas']> = components['schemas'][K]

/**
 * `true` when both sides have exactly the same keys. Otherwise it resolves to
 * an object type that is NOT assignable from `true`, so the compiler reports
 * the mismatch and names the keys involved.
 */
type SameKeys<Front, Backend> = [Exclude<keyof Front, keyof Backend>] extends [never]
  ? [Exclude<keyof Backend, keyof Front>] extends [never]
    ? true
    : { MISSING_IN_FRONT: Exclude<keyof Backend, keyof Front> }
  : { NOT_IN_BACKEND: Exclude<keyof Front, keyof Backend> }

/* eslint-disable @typescript-eslint/no-unused-vars */

// --- Cuenta y ajustes --------------------------------------------------------
const _me: SameKeys<Me, Schema<'MeResource'>> = true
const _publicUser: SameKeys<PublicUser, Schema<'PublicUserResource'>> = true
const _settings: SameKeys<UserSettings, Schema<'UserSettingsResource'>> = true
const _support: SameKeys<SupportResource, Schema<'SupportResourceResource'>> = true
const _block: SameKeys<Block, Schema<'BlockResource'>> = true

// --- Correo ------------------------------------------------------------------
const _letter: SameKeys<Letter, Schema<'LetterResource'>> = true
const _attachment: SameKeys<LetterAttachment, Schema<'LetterAttachmentResource'>> = true
const _delivery: SameKeys<Delivery, Schema<'DeliveryResource'>> = true
const _envelope: SameKeys<MailboxEnvelope, Schema<'MailboxEnvelopeResource'>> = true
const _mailboxLetter: SameKeys<MailboxLetter, Schema<'MailboxLetterResource'>> = true
const _schedule: SameKeys<Schedule, Schema<'ScheduleResource'>> = true

// --- Blog --------------------------------------------------------------------
const _post: SameKeys<Post, Schema<'PostResource'>> = true
const _comment: SameKeys<PostComment, Schema<'CommentResource'>> = true

// --- Dolls -------------------------------------------------------------------
const _dollProfile: SameKeys<DollProfile, Schema<'DollProfileResource'>> = true
const _dollRequest: SameKeys<DollRequest, Schema<'DollRequestResource'>> = true
const _dollChatMessage: SameKeys<DollChatMessage, Schema<'DollChatMessageResource'>> = true
const _party: SameKeys<DollRequestParty, Schema<'PartyResource'>> = true

/* eslint-enable @typescript-eslint/no-unused-vars */

export {}
