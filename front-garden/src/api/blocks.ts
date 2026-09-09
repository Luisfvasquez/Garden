import { api } from './client'
import type { Block } from '@/types/api'

export const blocksApi = {
  list: () => api.get<{ data: Block[] }>('/blocks').then((r) => r.data.data),

  block: (input: { postal_handle?: string; user_id?: string; reason?: string | null }) =>
    api.post<{ data: Block }>('/blocks', input).then((r) => r.data.data),

  unblock: (userId: string) => api.delete(`/blocks/${userId}`).then(() => undefined),
}
