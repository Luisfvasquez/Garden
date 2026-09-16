import { computed, toValue, type MaybeRefOrGetter } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { dollsApi } from '@/api/dolls'
import { qk } from '@/api/queryKeys'
import type { DollProfileInput } from '@/types/api'

export function useDollDirectory(
  specialty: MaybeRefOrGetter<string | undefined>,
  language: MaybeRefOrGetter<string | undefined>,
  available: MaybeRefOrGetter<boolean | undefined>,
) {
  return useQuery({
    queryKey: computed(() => qk.dolls.directory(toValue(specialty), toValue(language), toValue(available))),
    queryFn: () =>
      dollsApi.list({ specialty: toValue(specialty), language: toValue(language), available: toValue(available) }),
  })
}

export function useDollProfile(userId: MaybeRefOrGetter<string | undefined>) {
  return useQuery({
    queryKey: computed(() => qk.dolls.one(toValue(userId) ?? 'new')),
    queryFn: () => dollsApi.get(toValue(userId) as string),
    enabled: computed(() => Boolean(toValue(userId))),
  })
}

/** My own Doll profile — 404 means "haven't requested the role yet", not an error. */
export function useMyDollProfile() {
  return useQuery({
    queryKey: qk.dolls.myProfile,
    queryFn: () => dollsApi.myProfile(),
    retry: false,
  })
}

export function useRequestDollRole() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: DollProfileInput) => dollsApi.requestRole(input),
    onSuccess: (profile) => qc.setQueryData(qk.dolls.myProfile, profile),
  })
}

export function useUpdateDollProfile() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: Partial<DollProfileInput>) => dollsApi.updateProfile(input),
    onSuccess: (profile) => qc.setQueryData(qk.dolls.myProfile, profile),
  })
}

export function useSetDollAvailability() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (isAvailable: boolean) => dollsApi.setAvailability(isAvailable),
    onSuccess: (profile) => qc.setQueryData(qk.dolls.myProfile, profile),
  })
}
