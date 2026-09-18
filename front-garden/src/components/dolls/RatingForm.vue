<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import type { DollRequest } from '@/types/api'

/**
 * Post-service rating. Shown to the client on a completed request, once.
 *
 * Deliberately not a modal and not nagging: the request sits there rated or
 * unrated, and "sin valorar" is a perfectly fine end state. Pestering people
 * for stars is how rating systems stop meaning anything.
 */
const props = defineProps<{ request: DollRequest; busy?: boolean }>()
const emit = defineEmits<{ rate: [payload: { rating: number; comment: string | null }] }>()

const { t } = useI18n()

const rating = ref(0)
const hovered = ref(0)
const comment = ref('')

const stars = [1, 2, 3, 4, 5]

function submit() {
  if (rating.value < 1) return
  emit('rate', { rating: rating.value, comment: comment.value.trim() || null })
}
</script>

<template>
  <section class="rounded-lg border border-[var(--border-soft)] bg-[var(--surface)] p-4">
    <!-- Already rated: show it back, no way to change it. -->
    <template v-if="props.request.rated_at">
      <h2 class="mb-2 font-serif text-lg">{{ t('dolls.rating.yourRating') }}</h2>
      <p class="text-[var(--accent)]" :aria-label="t('dolls.rating.stars', { n: props.request.client_rating })">
        <span v-for="s in stars" :key="s" aria-hidden="true">{{ s <= (props.request.client_rating ?? 0) ? '★' : '☆' }}</span>
      </p>
      <p v-if="props.request.client_rating_comment" class="mt-2 text-sm italic text-[var(--text-muted)]">
        “{{ props.request.client_rating_comment }}”
      </p>
    </template>

    <form v-else class="flex flex-col gap-3" @submit.prevent="submit">
      <div>
        <h2 class="font-serif text-lg">{{ t('dolls.rating.title') }}</h2>
        <p class="text-sm text-[var(--text-muted)]">{{ t('dolls.rating.hint') }}</p>
      </div>

      <div class="flex gap-1" @mouseleave="hovered = 0">
        <button
          v-for="s in stars"
          :key="s"
          type="button"
          class="text-2xl leading-none text-[var(--accent)]"
          :aria-label="t('dolls.rating.stars', { n: s })"
          :aria-pressed="rating === s"
          @mouseenter="hovered = s"
          @focus="hovered = s"
          @click="rating = s"
        >
          {{ s <= (hovered || rating) ? '★' : '☆' }}
        </button>
      </div>

      <label class="flex flex-col gap-1">
        <span class="text-sm">{{ t('dolls.rating.comment') }}</span>
        <textarea
          v-model="comment"
          rows="3"
          maxlength="1000"
          class="w-full rounded border border-[var(--border-soft)] bg-[var(--surface-sunken)] px-3 py-2 text-sm"
        />
      </label>

      <div>
        <BaseButton type="submit" :loading="busy" :disabled="rating < 1">
          {{ t('dolls.rating.submit') }}
        </BaseButton>
      </div>
    </form>
  </section>
</template>
