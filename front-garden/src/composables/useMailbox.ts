import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, toValue, type MaybeRefOrGetter } from 'vue'
import { mailboxApi, type MailboxFilter } from '@/api/mailbox'
import { qk } from '@/api/queryKeys'

export function useMailboxList(status: MaybeRefOrGetter<MailboxFilter | undefined>) {
  return useQuery({
    queryKey: computed(() => qk.mailbox.list(toValue(status))),
    queryFn: () => mailboxApi.list(toValue(status)),
  })
}

export function useMailboxEnvelope(id: MaybeRefOrGetter<string | undefined>) {
  return useQuery({
    queryKey: computed(() => qk.mailbox.one(toValue(id) ?? 'new')),
    queryFn: () => mailboxApi.get(toValue(id) as string),
    enabled: computed(() => Boolean(toValue(id))),
  })
}

export function useUnreadCount() {
  return useQuery({
    queryKey: qk.mailbox.unreadCount,
    queryFn: () => mailboxApi.unreadCount(),
    staleTime: 15_000,
  })
}

export function useOpenLetter() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => mailboxApi.open(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: qk.mailbox.all })
    },
  })
}

export function useArchiveLetter() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, archived }: { id: string; archived: boolean }) =>
      mailboxApi.archive(id, archived),
    onSuccess: () => qc.invalidateQueries({ queryKey: qk.mailbox.all }),
  })
}

export function useFavoriteLetter() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, favorite }: { id: string; favorite: boolean }) =>
      mailboxApi.favorite(id, favorite),
    onSuccess: () => qc.invalidateQueries({ queryKey: qk.mailbox.all }),
  })
}

export function useReplyToLetter() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => mailboxApi.reply(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: qk.mailbox.all }),
  })
}
