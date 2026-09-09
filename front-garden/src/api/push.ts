import { api } from './client'

export interface VapidKeyResponse {
  public_key: string | null
  enabled: boolean
}

export interface PushSubscribeInput {
  platform: 'web'
  endpoint: string
  public_key: string
  auth_token: string
  device_name?: string
}

/** Web Push registration — contract: docs/api/comunidad-notificaciones.md. */
export const pushApi = {
  vapidKey: () => api.get<{ data: VapidKeyResponse }>('/push/vapid-public-key').then((r) => r.data.data),

  subscribe: (input: PushSubscribeInput) =>
    api.post<{ data: { id: string; platform: string } }>('/push-subscriptions', input).then((r) => r.data.data),

  unsubscribe: (id: string) => api.delete(`/push-subscriptions/${id}`).then(() => undefined),
}
