import { api, fetchResource } from './client'
import type { CursorPage, DollProfile, DollProfileInput } from '@/types/api'

/** Auto Memory Dolls — contract: docs/api/dolls.md. */
export const dollsApi = {
  list: (params: { specialty?: string; language?: string; available?: boolean; cursor?: string } = {}) =>
    api
      .get<CursorPage<DollProfile>>('/dolls', {
        params: { ...params, available: params.available === undefined ? undefined : Number(params.available) },
      })
      .then((r) => r.data),

  get: (userId: string) => fetchResource<DollProfile>(`/dolls/${userId}`),

  myProfile: () => fetchResource<DollProfile>('/me/doll-profile'),

  requestRole: (input: DollProfileInput) =>
    api.post<{ data: DollProfile }>('/me/doll-profile', input).then((r) => r.data.data),

  updateProfile: (input: Partial<DollProfileInput>) =>
    api.patch<{ data: DollProfile }>('/me/doll-profile', input).then((r) => r.data.data),

  setAvailability: (isAvailable: boolean) =>
    api
      .post<{ data: DollProfile }>('/me/doll-profile/availability', { is_available: isAvailable })
      .then((r) => r.data.data),
}
