import { api, fetchResource } from './client'
import type { Locale, Me, UserSettings } from '@/types/api'

export interface ProfileInput {
  name?: string
  pen_name?: string | null
  bio?: string | null
  country_code?: string | null
  timezone?: string
  locale?: Locale
}

export type SettingsInput = Partial<UserSettings>

export const accountApi = {
  updateProfile: (input: ProfileInput) =>
    api.patch<{ data: Me }>('/me', input).then((r) => r.data.data),

  getSettings: () => fetchResource<UserSettings>('/me/settings'),

  updateSettings: (input: SettingsInput) =>
    api.patch<{ data: UserSettings }>('/me/settings', input).then((r) => r.data.data),

  uploadAvatar: (file: File) => {
    const form = new FormData()
    form.append('avatar', file)
    return api.post<{ data: Me }>('/me/avatar', form).then((r) => r.data.data)
  },

  rotateHandle: () => api.post<{ data: Me }>('/me/postal-handle/rotate').then((r) => r.data.data),

  deactivate: () => api.post('/me/deactivate').then(() => undefined),

  destroy: () => api.delete<{ data: { deletes_at: string } }>('/me').then((r) => r.data.data),
}
