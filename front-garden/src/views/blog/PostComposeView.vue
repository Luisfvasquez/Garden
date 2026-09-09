<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCreatePost } from '@/composables/useBlog'
import { useMailboxList } from '@/composables/useMailbox'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import type { CreatePostInput, PostType } from '@/types/api'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'

const { t } = useI18n()
const router = useRouter()
const { messageFor } = useApiError()
const ui = useUiStore()
const create = useCreatePost()

const types: PostType[] = ['reflection', 'poem', 'unaddressed_letter', 'shared_letter']
const form = reactive({
  type: 'reflection' as PostType,
  title: '',
  text: '',
  testimonial: '',
  tags: '',
  letter_delivery_id: '',
  is_anonymous: false,
  comments_enabled: true,
})
const banner = ref('')

const isShared = computed(() => form.type === 'shared_letter')
// shared_letter picks a received letter from the mailbox.
const mailbox = useMailboxList(() => undefined)

async function submit() {
  banner.value = ''
  const payload: CreatePostInput = {
    type: form.type,
    title: form.title.trim(),
    body: {
      type: 'doc',
      content: form.text
        .split(/\n{2,}/)
        .map((p) => ({ type: 'paragraph', content: [{ type: 'text', text: p.trim() }] })),
    },
    testimonial: form.testimonial.trim() || null,
    tags: form.tags.split(',').map((s) => s.trim()).filter(Boolean).slice(0, 3),
    is_anonymous: form.is_anonymous,
    comments_enabled: form.comments_enabled,
  }
  if (isShared.value) payload.letter_delivery_id = form.letter_delivery_id

  try {
    const { data, status } = await create.mutateAsync(payload)
    ui.pushToast('success', status === 202 ? t('blog.pending') : t('blog.published'))
    router.push({ name: 'blog-post', params: { slug: data.slug } })
  } catch (e) {
    banner.value = messageFor(e)
  }
}
</script>

<template>
  <AppLayout>
    <section class="mx-auto flex max-w-xl flex-col gap-3">
      <h1 class="text-2xl">{{ t('blog.compose') }}</h1>
      <AlertBox v-if="banner" kind="error">{{ banner }}</AlertBox>

      <label class="flex flex-col gap-1 text-sm">
        <span class="font-medium">{{ t('blog.form.type') }}</span>
        <select v-model="form.type" class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5">
          <option v-for="ty in types" :key="ty" :value="ty">{{ t(`blog.type.${ty}`) }}</option>
        </select>
      </label>

      <label v-if="isShared" class="flex flex-col gap-1 text-sm">
        <span class="font-medium">{{ t('blog.form.sourceLetter') }}</span>
        <select v-model="form.letter_delivery_id" class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5">
          <option value="">{{ t('blog.form.sourceLetterPlaceholder') }}</option>
          <option v-for="m in mailbox.data.value?.data ?? []" :key="m.id" :value="m.id">
            {{ m.title || t('blog.form.untitledLetter') }} — {{ m.sender.display_name }}
          </option>
        </select>
        <span class="text-xs text-[var(--text-muted)]">{{ t('blog.form.consentNote') }}</span>
      </label>

      <BaseInput v-model="form.title" :label="t('blog.form.title')" required />

      <label class="flex flex-col gap-1 text-sm">
        <span class="font-medium">{{ t('blog.form.body') }}</span>
        <textarea
          v-model="form.text"
          rows="8"
          class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5 font-serif"
        />
      </label>

      <label v-if="isShared" class="flex flex-col gap-1 text-sm">
        <span class="font-medium">{{ t('blog.form.testimonial') }}</span>
        <textarea
          v-model="form.testimonial"
          rows="3"
          class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1.5"
        />
      </label>

      <BaseInput v-model="form.tags" :label="t('blog.form.tags')" :hint="t('blog.form.tagsHint')" />

      <BaseCheckbox v-model="form.is_anonymous" :label="t('blog.form.anonymous')" />
      <BaseCheckbox v-model="form.comments_enabled" :label="t('blog.form.commentsEnabled')" />

      <BaseButton :loading="create.isPending.value" @click="submit">{{ t('blog.form.submit') }}</BaseButton>
    </section>
  </AppLayout>
</template>
