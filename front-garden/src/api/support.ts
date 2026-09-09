import { api } from './client'
import type { SupportResource } from '@/types/api'

/** Public crisis / support helplines (docs/api/comunidad-notificaciones.md). */
export const supportApi = {
  list: (params: { country_code?: string | null; topic?: string } = {}) =>
    api
      .get<{ data: SupportResource[] }>('/support-resources', {
        params: {
          country_code: params.country_code || undefined,
          topic: params.topic || undefined,
        },
      })
      .then((r) => r.data.data),
}
