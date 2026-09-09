import { computed, toValue, type MaybeRefOrGetter } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { blogApi } from '@/api/blog'
import { qk } from '@/api/queryKeys'
import type { CreatePostInput, ReactionType } from '@/types/api'

export function useBlogFeed(type: MaybeRefOrGetter<string | undefined>, tag: MaybeRefOrGetter<string | undefined>) {
  return useQuery({
    queryKey: computed(() => qk.blog.feed(toValue(type), toValue(tag))),
    queryFn: () => blogApi.list({ type: toValue(type), tag: toValue(tag) }),
  })
}

export function usePost(slug: MaybeRefOrGetter<string | undefined>) {
  return useQuery({
    queryKey: computed(() => qk.blog.post(toValue(slug) ?? 'new')),
    queryFn: () => blogApi.get(toValue(slug) as string),
    enabled: computed(() => Boolean(toValue(slug))),
  })
}

export function usePostComments(postId: MaybeRefOrGetter<string | undefined>) {
  return useQuery({
    queryKey: computed(() => qk.blog.comments(toValue(postId) ?? 'new')),
    queryFn: () => blogApi.comments(toValue(postId) as string),
    enabled: computed(() => Boolean(toValue(postId))),
  })
}

export function useBlogTags() {
  return useQuery({ queryKey: qk.blog.tags, queryFn: () => blogApi.tags(), staleTime: 5 * 60_000 })
}

export function useCreatePost() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: CreatePostInput) => blogApi.create(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: qk.blog.all }),
  })
}

export function useAddComment(postId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: { body: string; parent_id?: string; is_anonymous?: boolean }) =>
      blogApi.comment(toValue(postId), input),
    onSuccess: () => qc.invalidateQueries({ queryKey: qk.blog.comments(toValue(postId)) }),
  })
}

export function useDeleteComment(postId: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => blogApi.deleteComment(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: qk.blog.comments(toValue(postId)) }),
  })
}

export function useReact(slug: MaybeRefOrGetter<string>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ postId, type, on }: { postId: string; type: ReactionType; on: boolean }) =>
      on ? blogApi.react(postId, type) : blogApi.unreact(postId, type),
    onSuccess: (mine) => {
      qc.setQueryData(qk.blog.post(toValue(slug)), (old: unknown) =>
        old && typeof old === 'object' ? { ...old, my_reactions: mine } : old,
      )
    },
  })
}

export function useConsentRequests() {
  return useQuery({ queryKey: qk.blog.consentRequests, queryFn: () => blogApi.consentRequests() })
}

export function useRespondConsent() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ postId, granted }: { postId: string; granted: boolean }) =>
      blogApi.respondConsent(postId, granted),
    onSuccess: () => qc.invalidateQueries({ queryKey: qk.blog.all }),
  })
}
