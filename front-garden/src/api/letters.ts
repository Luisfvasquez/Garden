import { api, fetchResource } from './client'
import type {
  CursorPage,
  Delivery,
  Letter,
  StyleCatalog,
  TiptapDoc,
  TransitTier,
} from '@/types/api'

export interface LetterDraftInput {
  title?: string | null
  body: TiptapDoc
  style?: Letter['style']
  in_reply_to_delivery_id?: string | null
}

export interface SendLetterInput {
  recipients: { postal_handle: string }[]
  delivery: { tier: TransitTier; arrive_at: string | null; arrive_timezone?: string | null }
  is_anonymous: boolean
  reveal_sender_at: string | null
  allow_read_receipt: boolean
}

export const lettersApi = {
  list: (status?: 'draft' | 'sent', cursor?: string) =>
    api.get<CursorPage<Letter>>('/letters', { params: { status, cursor } }).then((r) => r.data),

  get: (id: string) => fetchResource<Letter>(`/letters/${id}`),

  create: (input: LetterDraftInput) =>
    api.post<{ data: Letter }>('/letters', input).then((r) => r.data.data),

  update: (id: string, input: Partial<LetterDraftInput>) =>
    api.patch<{ data: Letter }>(`/letters/${id}`, input).then((r) => r.data.data),

  remove: (id: string) => api.delete(`/letters/${id}`).then(() => undefined),

  preview: (id: string) =>
    api
      .get<{ data: { letter: Letter; resolved_style: Record<string, unknown> } }>(
        `/letters/${id}/preview`,
      )
      .then((r) => r.data.data),

  styles: () => fetchResource<StyleCatalog>('/letters/styles'),

  send: (id: string, input: SendLetterInput, idempotencyKey: string) =>
    api
      .post<{ data: Delivery[] }>(`/letters/${id}/send`, input, {
        headers: { 'Idempotency-Key': idempotencyKey },
      })
      .then((r) => r.data.data),
}
