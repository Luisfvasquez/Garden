import { api } from './client'
import type { RandomQuota, RandomSendResult, TiptapDoc, TransitTier } from '@/types/api'

/** "Bottle at sea" — contract: docs/api/botella-al-mar.md. */
export const randomApi = {
  quota: () => api.get<{ data: RandomQuota }>('/random/quota').then((r) => r.data.data),

  send: (letterId: string, input: { tier: TransitTier; message_to_stranger?: string | null }, key: string) =>
    api
      .post<{ data: RandomSendResult }>(`/letters/${letterId}/send-random`, input, {
        headers: { 'Idempotency-Key': key },
      })
      .then((r) => r.data.data),

  replyAnonymous: (deliveryId: string, input: { body: TiptapDoc; tier?: TransitTier }) =>
    api
      .post<{ data: RandomSendResult }>(`/mailbox/${deliveryId}/reply-anonymous`, input)
      .then((r) => r.data.data),

  openCorrespondence: (deliveryId: string) =>
    api
      .post<{ data: { sender_accepted: boolean; recipient_accepted: boolean; opened: boolean } }>(
        `/mailbox/${deliveryId}/open-correspondence`,
      )
      .then((r) => r.data.data),
}
