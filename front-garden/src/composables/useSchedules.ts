import { computed, toValue, type MaybeRefOrGetter } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { schedulesApi } from '@/api/schedules'
import { qk } from '@/api/queryKeys'
import type { CreateScheduleInput } from '@/types/api'

export function useSchedules() {
  return useQuery({
    queryKey: qk.schedules.list,
    queryFn: () => schedulesApi.list(),
  })
}

export function useSchedule(id: MaybeRefOrGetter<string | undefined>) {
  return useQuery({
    queryKey: computed(() => qk.schedules.one(toValue(id) ?? 'new')),
    queryFn: () => schedulesApi.get(toValue(id) as string),
    enabled: computed(() => Boolean(toValue(id))),
  })
}

export function useScheduleOccurrences(id: MaybeRefOrGetter<string | undefined>) {
  return useQuery({
    queryKey: computed(() => qk.schedules.occurrences(toValue(id) ?? 'new')),
    queryFn: () => schedulesApi.occurrences(toValue(id) as string),
    enabled: computed(() => Boolean(toValue(id))),
  })
}

export function useCreateSchedule() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateScheduleInput) => schedulesApi.create(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: qk.schedules.all }),
  })
}

export function useSchedulePause() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, resume }: { id: string; resume: boolean }) =>
      resume ? schedulesApi.resume(id) : schedulesApi.pause(id),
    onSuccess: (schedule) => {
      qc.setQueryData(qk.schedules.one(schedule.id), schedule)
      void qc.invalidateQueries({ queryKey: qk.schedules.all })
    },
  })
}

export function useDeleteSchedule() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => schedulesApi.remove(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: qk.schedules.all }),
  })
}

export function useAssignOccurrenceLetter(scheduleId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ date, letterId }: { date: string; letterId: string | null }) =>
      schedulesApi.assignLetter(toValue(scheduleId), date, letterId),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: qk.schedules.occurrences(toValue(scheduleId)) }),
  })
}
