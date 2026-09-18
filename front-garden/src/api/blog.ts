import { api, fetchResource } from './client'
import type {
  BlogTag,
  CreatePostInput,
  CursorPage,
  Post,
  PostComment,
  ReactionType,
} from '@/types/api'

/** Community blog — contract: docs/api/blog.md. */
export const blogApi = {
  list: (params: { type?: string; tag?: string; sort?: string; q?: string; cursor?: string } = {}) =>
    api.get<CursorPage<Post>>('/posts', { params }).then((r) => r.data),

  get: (slug: string) => fetchResource<Post>(`/posts/${slug}`),

  create: (input: CreatePostInput) =>
    api.post<{ data: Post }>('/posts', input).then((r) => ({ data: r.data.data, status: r.status })),

  update: (id: string, input: Partial<Pick<Post, 'title' | 'testimonial' | 'comments_enabled'>> & { tags?: string[] }) =>
    api.patch<{ data: Post }>(`/posts/${id}`, input).then((r) => r.data.data),

  remove: (id: string) => api.delete(`/posts/${id}`).then(() => undefined),

  comments: (postId: string) =>
    api.get<{ data: PostComment[] }>(`/posts/${postId}/comments`).then((r) => r.data.data),

  comment: (postId: string, input: { body: string; parent_id?: string; is_anonymous?: boolean }) =>
    api.post<{ data: PostComment }>(`/posts/${postId}/comments`, input).then((r) => r.data.data),

  deleteComment: (id: string) => api.delete(`/comments/${id}`).then(() => undefined),

  react: (postId: string, type: ReactionType) =>
    api.post<{ data: { my_reactions: ReactionType[] } }>(`/posts/${postId}/reactions`, { type }).then((r) => r.data.data.my_reactions),

  unreact: (postId: string, type: ReactionType) =>
    api.delete<{ data: { my_reactions: ReactionType[] } }>(`/posts/${postId}/reactions/${type}`).then((r) => r.data.data.my_reactions),

  tags: () => api.get<{ data: BlogTag[] }>('/tags').then((r) => r.data.data),

  consentRequests: () => api.get<{ data: Post[] }>('/consent-requests').then((r) => r.data.data),

  respondConsent: (postId: string, granted: boolean) =>
    api
      .post<{ data: { id: string; consent_status: string; published: boolean } }>(
        `/consent-requests/${postId}/respond`,
        { granted },
      )
      .then((r) => r.data.data),
}
