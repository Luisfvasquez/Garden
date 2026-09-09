import { api } from './client'

export interface Device {
  id: string
  device_name: string
  last_used_at: string | null
  created_at: string | null
  current: boolean
}

export const devicesApi = {
  list: () => api.get<{ data: Device[] }>('/auth/devices').then((r) => r.data.data),
  revoke: (id: string) => api.delete(`/auth/devices/${id}`).then(() => undefined),
}
