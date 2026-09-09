import { api } from './client'
import type { FeaturePayload } from '@/types/api'

/** GET /features — module flags the client uses to hide unfinished UI. */
export const featuresApi = {
  map: () => api.get<{ data: FeaturePayload }>('/features').then((r) => r.data.data.features),
}
