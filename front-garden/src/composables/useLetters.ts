import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, toValue, type MaybeRefOrGetter } from 'vue'
import { lettersApi, type LetterDraftInput, type SendLetterInput } from '@/api/letters'
import { qk } from '@/api/queryKeys'

export function useLetterList(status: MaybeRefOrGetter<'draft' | 'sent' | undefined>) {
  return useQuery({
    queryKey: computed(() => qk.letters.list(toValue(status))),
    queryFn: () => lettersApi.list(toValue(status)),
  })
}

export function useLetter(id: MaybeRefOrGetter<string | undefined>) {
  return useQuery({
    queryKey: computed(() => qk.letters.one(toValue(id) ?? 'new')),
    queryFn: () => lettersApi.get(toValue(id) as string),
    enabled: computed(() => Boolean(toValue(id))),
  })
}

export function useStyleCatalog() {
  return useQuery({
    queryKey: qk.letters.styles,
    queryFn: () => lettersApi.styles(),
    staleTime: 60 * 60 * 1000,
  })
}

export function useLetterPreview(id: MaybeRefOrGetter<string | undefined>) {
  return useQuery({
    queryKey: computed(() => [...qk.letters.all, 'preview', toValue(id) ?? 'new']),
    queryFn: () => lettersApi.preview(toValue(id) as string),
    enabled: computed(() => Boolean(toValue(id))),
  })
}

export function useCreateDraft() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: LetterDraftInput) => lettersApi.create(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: qk.letters.all }),
  })
}

export function useUpdateLetter() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, input }: { id: string; input: Partial<LetterDraftInput> }) =>
      lettersApi.update(id, input),
    onSuccess: (letter) => {
      qc.setQueryData(qk.letters.one(letter.id), letter)
      void qc.invalidateQueries({ queryKey: [...qk.letters.all, 'list'] })
    },
  })
}

export function useDeleteLetter() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => lettersApi.remove(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: qk.letters.all }),
  })
}

export function useSendLetter() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, input, key }: { id: string; input: SendLetterInput; key: string }) =>
      lettersApi.send(id, input, key),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: qk.letters.all })
      void qc.invalidateQueries({ queryKey: qk.deliveries.all })
    },
  })
}
