import { api, fetchResource } from './client'
import type { Locale, Me, UserSettings } from '@/types/api'

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
  birth_date: string
  timezone: string
  locale: Locale
  accepts_terms: boolean
}

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

export interface ResetPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export const authApi = {
  me: () => fetchResource<Me>('/me'),

  register: (payload: RegisterPayload) =>
    api.post<{ data: Me }>('/auth/register', payload).then((r) => r.data.data),

  login: (payload: LoginPayload) => api.post('/auth/login', payload).then(() => undefined),

  logout: () => api.post('/auth/logout').then(() => undefined),

  forgotPassword: (email: string) =>
    api.post('/auth/forgot-password', { email }).then(() => undefined),

  resetPassword: (payload: ResetPayload) =>
    api.post('/auth/reset-password', payload).then(() => undefined),

  verifyEmail: (path: string, query: Record<string, string>) =>
    api.post(path, null, { params: query }).then(() => undefined),

  resendVerification: () => api.post('/auth/email/resend').then(() => undefined),

  settings: () => fetchResource<UserSettings>('/me/settings'),
}
