import { api, fetchResource } from './client'
import type { CreateDollRequestInput, CursorPage, DollRequest, DollRequestStatus } from '@/types/api'

/** Auto Memory Dolls — solicitudes. Contract: docs/api/dolls.md. */
export const dollRequestsApi = {
  list: (params: { role?: 'client' | 'doll'; status?: DollRequestStatus; cursor?: string } = {}) =>
    api.get<CursorPage<DollRequest>>('/doll-requests', { params }).then((r) => r.data),

  get: (id: string) => fetchResource<DollRequest>(`/doll-requests/${id}`),

  create: (input: CreateDollRequestInput) =>
    api.post<{ data: DollRequest }>('/doll-requests', input).then((r) => r.data.data),

  accept: (id: string) => api.post<{ data: DollRequest }>(`/doll-requests/${id}/accept`).then((r) => r.data.data),

  reject: (id: string) => api.post<{ data: DollRequest }>(`/doll-requests/${id}/reject`).then((r) => r.data.data),

  start: (id: string) => api.post<{ data: DollRequest }>(`/doll-requests/${id}/start`).then((r) => r.data.data),

  cancel: (id: string) => api.post<{ data: DollRequest }>(`/doll-requests/${id}/cancel`).then((r) => r.data.data),
}
