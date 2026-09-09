import { fetchResource } from './client'
import type { PublicUser } from '@/types/api'

export const usersApi = {
  byHandle: (postalHandle: string) => fetchResource<PublicUser>(`/users/${postalHandle}`),
}
