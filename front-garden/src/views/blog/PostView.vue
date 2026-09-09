<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { usePost, usePostComments, useAddComment, useReact } from '@/composables/useBlog'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import { relativeTime } from '@/lib/datetime'
import type { ReactionType } from '@/types/api'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import PostBody from '@/components/blog/PostBody.vue'

const { t } = useI18n()
const route = useRoute()
const { messageFor } = useApiError()
const ui = useUiStore()

const slug = computed(() => route.params.slug as string)
const post = usePost(slug)
const postId = computed(() => post.data.value?.id ?? '')
const comments = usePostComments(postId)
const addComment = useAddComment(postId)
const react = useReact(slug)

const reactions: { type: ReactionType; icon: string }[] = [
  { type: 'heart', icon: '♥' },
  { type: 'tear', icon: '︵' },
  { type: 'flower', icon: '✿' },
  { type: 'candle', icon: '†' },
]

const commentText = ref('')
const commentAnon = ref(false)
const banner = ref('')

function toggle(type: ReactionType) {
  const on = !post.data.value?.my_reactions.includes(type)
  react.mutate({ postId: postId.value, type, on }, { onError: (e) => ui.pushToast('error', messageFor(e)) })
}

async function submitComment() {
  banner.value = ''
  try {
    await addComment.mutateAsync({ body: commentText.value.trim(), is_anonymous: commentAnon.value })
    commentText.value = ''
  } catch (e) {
    banner.value = messageFor(e)
  }
}
</script>

<template>
  <AppLayout>
    <section class="mx-auto flex max-w-2xl flex-col gap-5">
      <RouterLink :to="{ name: 'blog' }" class="text-sm text-[var(--accent)] underline">← {{ t('blog.backToFeed') }}</RouterLink>

      <SpinnerDots v-if="post.isPending.value" :label="t('common.loading')" />
      <AlertBox v-else-if="post.isError.value" kind="error">{{ messageFor(post.error.value) }}</AlertBox>

      <template v-else-if="post.data.value">
        <header>
          <p class="text-xs text-[var(--text-muted)]">
            {{ t(`blog.type.${post.data.value.type}`) }} · {{ post.data.value.author.display_name }} ·
            {{ relativeTime(post.data.value.published_at) }}
          </p>
          <h1 class="mt-1 font-serif text-2xl">{{ post.data.value.title }}</h1>
        </header>

        <AlertBox v-if="post.data.value.moderation_status === 'flagged'" kind="info">
          {{ t('blog.underReview') }}
        </AlertBox>
        <AlertBox v-if="post.data.value.consent_status === 'pending'" kind="info">
          {{ t('blog.awaitingConsent') }}
        </AlertBox>

        <PostBody :body="post.data.value.body" />

        <p v-if="post.data.value.testimonial" class="border-l-2 border-[var(--accent)] pl-3 text-sm italic text-[var(--text-muted)]">
          {{ post.data.value.testimonial }}
        </p>

        <div class="flex gap-2">
          <button
            v-for="r in reactions"
            :key="r.type"
            type="button"
            class="rounded-full border px-3 py-1 text-sm"
            :class="post.data.value.my_reactions.includes(r.type)
              ? 'border-[var(--accent)] text-[var(--accent)]'
              : 'border-[var(--border-soft)] text-[var(--text-muted)]'"
            @click="toggle(r.type)"
          >
            {{ r.icon }} {{ t(`blog.reaction.${r.type}`) }}
          </button>
        </div>

        <section class="flex flex-col gap-3">
          <h2 class="text-lg">{{ t('blog.comments') }}</h2>

          <form v-if="post.data.value.comments_enabled" class="flex flex-col gap-2" @submit.prevent="submitComment">
            <AlertBox v-if="banner" kind="error">{{ banner }}</AlertBox>
            <textarea
              v-model="commentText"
              rows="3"
              :placeholder="t('blog.commentPlaceholder')"
              class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5 text-sm"
            />
            <div class="flex items-center gap-3">
              <label class="flex items-center gap-1 text-xs text-[var(--text-muted)]">
                <input v-model="commentAnon" type="checkbox" /> {{ t('blog.anon') }}
              </label>
              <BaseButton type="submit" :loading="addComment.isPending.value" :disabled="!commentText.trim()">
                {{ t('blog.commentSend') }}
              </BaseButton>
            </div>
          </form>
          <p v-else class="text-sm text-[var(--text-muted)]">{{ t('blog.commentsDisabled') }}</p>

          <SpinnerDots v-if="comments.isPending.value" />
          <ul v-else class="flex flex-col gap-3">
            <li v-for="c in comments.data.value ?? []" :key="c.id" class="text-sm">
              <p class="text-[var(--text-muted)]">{{ c.author.display_name }} · {{ relativeTime(c.created_at) }}</p>
              <p class="whitespace-pre-line">{{ c.body }}</p>
              <ul v-if="c.replies?.length" class="mt-2 flex flex-col gap-2 border-l border-[var(--border-soft)] pl-3">
                <li v-for="r in c.replies" :key="r.id">
                  <p class="text-[var(--text-muted)]">{{ r.author.display_name }} · {{ relativeTime(r.created_at) }}</p>
                  <p class="whitespace-pre-line">{{ r.body }}</p>
                </li>
              </ul>
            </li>
          </ul>
        </section>
      </template>
    </section>
  </AppLayout>
</template>
