import { api, fetchResource } from './client'
import type { CursorPage, Delivery, DeliveryStatus, TrackingEvent } from '@/types/api'

export const deliveriesApi = {
  list: (status?: DeliveryStatus, cursor?: string) =>
    api
      .get<CursorPage<Delivery>>('/deliveries', { params: { status, cursor } })
      .then((r) => r.data),

  get: (id: string) => fetchResource<Delivery>(`/deliveries/${id}`),

  tracking: (id: string) => fetchResource<TrackingEvent[]>(`/deliveries/${id}/tracking`),

  cancel: (id: string) =>
    api.post<{ data: Delivery }>(`/deliveries/${id}/cancel`).then((r) => r.data.data),
}
