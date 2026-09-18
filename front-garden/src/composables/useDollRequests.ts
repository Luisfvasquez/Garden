import { computed, toValue, type MaybeRefOrGetter } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { dollRequestsApi } from '@/api/dollRequests'
import { qk } from '@/api/queryKeys'
import type { CreateDollRequestInput, DollRequest, DollRequestStatus } from '@/types/api'

export function useDollRequests(
  role: MaybeRefOrGetter<'client' | 'doll' | undefined>,
  status: MaybeRefOrGetter<DollRequestStatus | undefined>,
) {
  return useQuery({
    queryKey: computed(() => qk.dollRequests.list(toValue(role), toValue(status))),
    queryFn: () => dollRequestsApi.list({ role: toValue(role), status: toValue(status) }),
  })
}

export function useDollRequest(id: MaybeRefOrGetter<string | undefined>) {
  return useQuery({
    queryKey: computed(() => qk.dollRequests.one(toValue(id) ?? 'new')),
    queryFn: () => dollRequestsApi.get(toValue(id) as string),
    enabled: computed(() => Boolean(toValue(id))),
  })
}

export function useCreateDollRequest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateDollRequestInput) => dollRequestsApi.create(input),
    onSuccess: (request) => {
      qc.setQueryData(qk.dollRequests.one(request.id), request)
      void qc.invalidateQueries({ queryKey: qk.dollRequests.all })
    },
  })
}

function useDollRequestTransition(mutationFn: (id: string) => Promise<DollRequest>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn,
    onSuccess: (request) => {
      qc.setQueryData(qk.dollRequests.one(request.id), request)
      void qc.invalidateQueries({ queryKey: qk.dollRequests.all })
    },
  })
}

export function useAcceptDollRequest() {
  return useDollRequestTransition(dollRequestsApi.accept)
}

export function useRejectDollRequest() {
  return useDollRequestTransition(dollRequestsApi.reject)
}

export function useStartDollRequest() {
  return useDollRequestTransition(dollRequestsApi.start)
}

export function useCancelDollRequest() {
  return useDollRequestTransition(dollRequestsApi.cancel)
}

export function useRateDollRequest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, rating, comment }: { id: string; rating: number; comment?: string | null }) =>
      dollRequestsApi.rate(id, rating, comment),
    onSuccess: (request) => {
      qc.setQueryData(qk.dollRequests.one(request.id), request)
      void qc.invalidateQueries({ queryKey: qk.dollRequests.all })
      // The Doll's public average is recomputed hourly by the backend job, so
      // the directory won't move yet — but the profile page should stop
      // offering to rate this request again.
      void qc.invalidateQueries({ queryKey: qk.dolls.all })
    },
  })
}
