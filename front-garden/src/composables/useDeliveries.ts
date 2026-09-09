import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, toValue, type MaybeRefOrGetter } from 'vue'
import { deliveriesApi } from '@/api/deliveries'
import { qk } from '@/api/queryKeys'
import type { DeliveryStatus } from '@/types/api'

export function useDeliveryList(status: MaybeRefOrGetter<DeliveryStatus | undefined>) {
  return useQuery({
    queryKey: computed(() => qk.deliveries.list(toValue(status))),
    queryFn: () => deliveriesApi.list(toValue(status)),
  })
}

export function useDelivery(id: MaybeRefOrGetter<string | undefined>) {
  return useQuery({
    queryKey: computed(() => qk.deliveries.one(toValue(id) ?? 'new')),
    queryFn: () => deliveriesApi.get(toValue(id) as string),
    enabled: computed(() => Boolean(toValue(id))),
  })
}

export function useTracking(id: MaybeRefOrGetter<string | undefined>) {
  return useQuery({
    queryKey: computed(() => qk.deliveries.tracking(toValue(id) ?? 'new')),
    queryFn: () => deliveriesApi.tracking(toValue(id) as string),
    enabled: computed(() => Boolean(toValue(id))),
    refetchInterval: 60_000,
  })
}

export function useCancelDelivery() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => deliveriesApi.cancel(id),
    onSuccess: (delivery) => {
      qc.setQueryData(qk.deliveries.one(delivery.id), delivery)
      void qc.invalidateQueries({ queryKey: [...qk.deliveries.all, 'list'] })
    },
  })
}
