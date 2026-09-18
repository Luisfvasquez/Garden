import { api } from './client'
import type {
  CreateDollDraftInput,
  CursorPage,
  DollChatMessage,
  Letter,
} from '@/types/api'

/**
 * Doll chat and versioned drafts. Contract: docs/api/dolls.md § Chat.
 *
 * The transcript comes back oldest-first (unlike the mailbox): a conversation
 * is read forwards.
 */
export const dollChatApi = {
  messages: (requestId: string, params: { cursor?: string } = {}) =>
    api
      .get<CursorPage<DollChatMessage>>(`/doll-requests/${requestId}/messages`, { params })
      .then((r) => r.data),

  send: (requestId: string, body: string) =>
    api
      .post<{ data: DollChatMessage }>(`/doll-requests/${requestId}/messages`, { body })
      .then((r) => r.data.data),

  /** Only the Doll may call this. Version is assigned server-side. */
  shareDraft: (requestId: string, input: CreateDollDraftInput) =>
    api
      .post<{ data: DollChatMessage }>(`/doll-requests/${requestId}/drafts`, input)
      .then((r) => r.data.data),

  /**
   * Only the client may call this. Completes the request and returns the new
   * letter — owned by the client, credited to the Doll. It is an ordinary
   * draft: the client still sends it through the normal postal flow.
   */
  approveDraft: (requestId: string, draftId: string) =>
    api
      .post<{ data: Letter }>(`/doll-requests/${requestId}/drafts/${draftId}/approve`)
      .then((r) => r.data.data),
}
