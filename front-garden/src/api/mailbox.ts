import { api, fetchResource } from './client'
import type { CursorPage, Letter, MailboxEnvelope, MailboxLetter } from '@/types/api'

export type MailboxFilter = 'unread' | 'read' | 'archived' | 'favorite'

export const mailboxApi = {
  list: (status?: MailboxFilter, cursor?: string) =>
    api
      .get<CursorPage<MailboxEnvelope>>('/mailbox', { params: { status, cursor } })
      .then((r) => r.data),

  get: (id: string) => fetchResource<MailboxEnvelope>(`/mailbox/${id}`),

  open: (id: string) =>
    api.post<{ data: MailboxLetter }>(`/mailbox/${id}/open`).then((r) => r.data.data),

  archive: (id: string, archived: boolean) =>
    api
      .post<{ data: MailboxEnvelope }>(`/mailbox/${id}/archive`, { archived })
      .then((r) => r.data.data),

  favorite: (id: string, favorite: boolean) =>
    api
      .post<{ data: MailboxEnvelope }>(`/mailbox/${id}/favorite`, { favorite })
      .then((r) => r.data.data),

  reply: (id: string) =>
    api.post<{ data: Letter }>(`/mailbox/${id}/reply`).then((r) => r.data.data),

  unreadCount: () => fetchResource<{ count: number }>('/mailbox/unread-count').then((r) => r.count),
}
