<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useBlogFeed, useBlogTags } from '@/composables/useBlog'
import { useApiError } from '@/composables/useApiError'
import { relativeTime } from '@/lib/datetime'
import type { PostType } from '@/types/api'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const { t } = useI18n()
const { messageFor } = useApiError()

const types: (PostType | undefined)[] = [undefined, 'reflection', 'poem', 'unaddressed_letter', 'shared_letter']
const activeType = ref<PostType | undefined>(undefined)
const activeTag = ref<string | undefined>(undefined)

// `search` es lo que se escribe; `query` es lo que se consulta. Debounce de
// 350 ms: cada pulsación es una consulta full-text, no un filtro en memoria.
const search = ref('')
const query = ref('')
let debounce: ReturnType<typeof setTimeout> | undefined

watch(search, (value) => {
  clearTimeout(debounce)
  debounce = setTimeout(() => {
    query.value = value.trim()
  }, 350)
})

onUnmounted(() => clearTimeout(debounce))

const feed = useBlogFeed(activeType, activeTag, query)
const tags = useBlogTags()

function clearSearch() {
  search.value = ''
  query.value = ''
}
</script>

<template>
  <AppLayout>
    <section class="flex flex-col gap-4">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl">{{ t('blog.title') }}</h1>
        <div class="flex gap-2">
          <RouterLink :to="{ name: 'blog-consent' }" class="text-sm text-[var(--accent)] underline">
            {{ t('blog.consentRequests') }}
          </RouterLink>
          <RouterLink :to="{ name: 'blog-compose' }">
            <BaseButton>{{ t('blog.compose') }}</BaseButton>
          </RouterLink>
        </div>
      </div>

      <form class="flex gap-2" role="search" @submit.prevent="query = search.trim()">
        <label class="flex-1">
          <span class="sr-only">{{ t('blog.search.label') }}</span>
          <input
            v-model="search"
            type="search"
            :placeholder="t('blog.search.placeholder')"
            class="w-full rounded border border-[var(--border-soft)] bg-[var(--surface)] px-3 py-2 text-sm"
          />
        </label>
        <button
          v-if="query"
          type="button"
          class="rounded border border-[var(--border-soft)] px-3 py-1.5 text-sm"
          @click="clearSearch"
        >
          {{ t('blog.search.clear') }}
        </button>
      </form>

      <p v-if="query" class="text-sm text-[var(--text-muted)]">
        {{ t('blog.search.hint') }}
      </p>

      <div class="flex flex-wrap gap-2">
        <button
          v-for="ty in types"
          :key="ty ?? 'all'"
          type="button"
          class="rounded-full border px-3 py-1 text-sm"
          :class="activeType === ty ? 'border-[var(--accent)] text-[var(--accent)]' : 'border-[var(--border-soft)] text-[var(--text-muted)]'"
          @click="activeType = ty"
        >
          {{ t(`blog.type.${ty ?? 'all'}`) }}
        </button>
      </div>

      <div v-if="tags.data.value?.length" class="flex flex-wrap gap-2 text-xs">
        <button
          type="button"
          class="underline"
          :class="activeTag ? 'text-[var(--text-muted)]' : 'text-[var(--accent)]'"
          @click="activeTag = undefined"
        >
          {{ t('blog.allTags') }}
        </button>
        <button
          v-for="tg in tags.data.value"
          :key="tg.slug"
          type="button"
          class="underline"
          :class="activeTag === tg.slug ? 'text-[var(--accent)]' : 'text-[var(--text-muted)]'"
          @click="activeTag = tg.slug"
        >
          #{{ tg.label }}
        </button>
      </div>

      <SpinnerDots v-if="feed.isPending.value" :label="t('common.loading')" />
      <AlertBox v-else-if="feed.isError.value" kind="error">{{ messageFor(feed.error.value) }}</AlertBox>
      <p v-else-if="!feed.data.value?.data.length" class="text-sm text-[var(--text-muted)]">
        {{ t('blog.empty') }}
      </p>

      <ul v-else class="flex flex-col gap-3">
        <li v-for="p in feed.data.value?.data" :key="p.id">
          <RouterLink
            :to="{ name: 'blog-post', params: { slug: p.slug } }"
            class="block rounded-md border border-[var(--border-soft)] p-4 hover:bg-[var(--surface-sunken)]"
          >
            <p class="text-xs text-[var(--text-muted)]">
              {{ t(`blog.type.${p.type}`) }} · {{ p.author.display_name }} · {{ relativeTime(p.published_at) }}
            </p>
            <h2 class="font-serif text-lg">{{ p.title }}</h2>
            <p v-if="p.testimonial" class="mt-1 text-sm text-[var(--text-muted)] italic">
              {{ p.testimonial }}
            </p>
            <p v-if="p.tags.length" class="mt-2 text-xs text-[var(--text-muted)]">
              <span v-for="tg in p.tags" :key="tg.slug">#{{ tg.label }} </span>
            </p>
          </RouterLink>
        </li>
      </ul>
    </section>
  </AppLayout>
</template>
