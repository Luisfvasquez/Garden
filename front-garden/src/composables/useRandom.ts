import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { randomApi } from '@/api/random'
import { qk } from '@/api/queryKeys'
import type { TiptapDoc, TransitTier } from '@/types/api'

export function useRandomQuota() {
  return useQuery({
    queryKey: qk.random.quota,
    queryFn: () => randomApi.quota(),
  })
}

export function useSendRandom() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({
      letterId,
      tier,
      message,
      key,
    }: {
      letterId: string
      tier: TransitTier
      message?: string | null
      key: string
    }) => randomApi.send(letterId, { tier, message_to_stranger: message ?? null }, key),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: qk.random.quota })
      void qc.invalidateQueries({ queryKey: qk.letters.all })
      void qc.invalidateQueries({ queryKey: qk.deliveries.all })
    },
  })
}

export function useReplyAnonymous() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ deliveryId, body, tier }: { deliveryId: string; body: TiptapDoc; tier?: TransitTier }) =>
      randomApi.replyAnonymous(deliveryId, { body, tier }),
    onSuccess: () => qc.invalidateQueries({ queryKey: qk.mailbox.all }),
  })
}

export function useOpenCorrespondence() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (deliveryId: string) => randomApi.openCorrespondence(deliveryId),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: qk.mailbox.all })
      void qc.invalidateQueries({ queryKey: qk.deliveries.all })
    },
  })
}
