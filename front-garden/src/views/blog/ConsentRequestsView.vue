<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { useConsentRequests, useRespondConsent } from '@/composables/useBlog'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import PostBody from '@/components/blog/PostBody.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const { t } = useI18n()
const { messageFor } = useApiError()
const ui = useUiStore()

const query = useConsentRequests()
const respond = useRespondConsent()

function decide(postId: string, granted: boolean) {
  respond.mutate(
    { postId, granted },
    {
      onSuccess: () => ui.pushToast('success', granted ? t('blog.consent.granted') : t('blog.consent.denied')),
      onError: (e) => ui.pushToast('error', messageFor(e)),
    },
  )
}
</script>

<template>
  <AppLayout>
    <section class="mx-auto flex max-w-2xl flex-col gap-4">
      <RouterLink :to="{ name: 'blog' }" class="text-sm text-[var(--accent)] underline">← {{ t('blog.backToFeed') }}</RouterLink>
      <h1 class="text-2xl">{{ t('blog.consent.title') }}</h1>
      <p class="text-sm text-[var(--text-muted)]">{{ t('blog.consent.intro') }}</p>

      <SpinnerDots v-if="query.isPending.value" :label="t('common.loading')" />
      <AlertBox v-else-if="query.isError.value" kind="error">{{ messageFor(query.error.value) }}</AlertBox>
      <p v-else-if="!query.data.value?.length" class="text-sm text-[var(--text-muted)]">
        {{ t('blog.consent.empty') }}
      </p>

      <ul v-else class="flex flex-col gap-4">
        <li v-for="p in query.data.value" :key="p.id" class="rounded-md border border-[var(--border-soft)] p-4">
          <p class="text-xs text-[var(--text-muted)]">
            {{ t('blog.consent.publishedAs', { name: p.author.display_name }) }}
          </p>
          <h2 class="font-serif text-lg">{{ p.title }}</h2>
          <PostBody :body="p.body" class="mt-2 text-sm" />
          <p v-if="p.testimonial" class="mt-2 border-l-2 border-[var(--accent)] pl-3 text-sm italic text-[var(--text-muted)]">
            {{ p.testimonial }}
          </p>
          <div class="mt-3 flex gap-2">
            <BaseButton :loading="respond.isPending.value" @click="decide(p.id, true)">
              {{ t('blog.consent.allow') }}
            </BaseButton>
            <button type="button" class="text-sm text-[var(--color-wax-500)] underline" @click="decide(p.id, false)">
              {{ t('blog.consent.deny') }}
            </button>
          </div>
        </li>
      </ul>
    </section>
  </AppLayout>
</template>
