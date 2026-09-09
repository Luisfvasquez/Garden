import { api, fetchResource } from './client'
import type {
  CreateScheduleInput,
  CursorPage,
  OccurrenceTimeline,
  Schedule,
} from '@/types/api'

/** Recurring / future letters — contract: docs/api/programaciones.md. */
export const schedulesApi = {
  list: (cursor?: string) =>
    api.get<CursorPage<Schedule>>('/schedules', { params: { cursor } }).then((r) => r.data),

  get: (id: string) => fetchResource<Schedule>(`/schedules/${id}`),

  create: (input: CreateScheduleInput) =>
    api.post<{ data: Schedule }>('/schedules', input).then((r) => r.data.data),

  update: (id: string, input: Partial<Pick<Schedule, 'name' | 'letter_id' | 'occurrences_total' | 'tier' | 'is_anonymous' | 'leap_day_policy'>>) =>
    api.patch<{ data: Schedule }>(`/schedules/${id}`, input).then((r) => r.data.data),

  remove: (id: string) => api.delete(`/schedules/${id}`).then(() => undefined),

  pause: (id: string) =>
    api.post<{ data: Schedule }>(`/schedules/${id}/pause`).then((r) => r.data.data),

  resume: (id: string) =>
    api.post<{ data: Schedule }>(`/schedules/${id}/resume`).then((r) => r.data.data),

  occurrences: (id: string) =>
    api.get<OccurrenceTimeline>(`/schedules/${id}/occurrences`).then((r) => r.data),

  assignLetter: (id: string, date: string, letterId: string | null) =>
    api
      .put<{ data: { occurrence_date: string; letter_id: string | null } }>(
        `/schedules/${id}/occurrences/${date}/letter`,
        { letter_id: letterId },
      )
      .then((r) => r.data.data),
}
