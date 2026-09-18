<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDollProfile } from '@/composables/useDolls'
import { useApiError } from '@/composables/useApiError'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import SafetyActions from '@/components/safety/SafetyActions.vue'

const { t } = useI18n()
const route = useRoute()
const { messageFor } = useApiError()

const handle = computed(() => route.params.id as string)
const doll = useDollProfile(handle)
</script>

<template>
  <AppLayout>
    <section class="mx-auto flex max-w-2xl flex-col gap-4">
      <RouterLink :to="{ name: 'dolls' }" class="text-sm text-[var(--accent)] underline">
        ← {{ t('dolls.profile.backToDirectory') }}
      </RouterLink>

      <SpinnerDots v-if="doll.isPending.value" :label="t('common.loading')" />
      <AlertBox v-else-if="doll.isError.value" kind="error">{{ messageFor(doll.error.value) }}</AlertBox>

      <template v-else-if="doll.data.value">
        <header class="flex items-center justify-between">
          <div>
            <h1 class="font-serif text-2xl">{{ doll.data.value.doll.display_name }}</h1>
            <p class="text-sm text-[var(--text-muted)]">{{ doll.data.value.headline }}</p>
          </div>
          <span
            class="rounded-full px-2 py-1 text-xs"
            :class="doll.data.value.is_available ? 'bg-[var(--color-sage-500)]/20' : 'bg-[var(--border-soft)]'"
          >
            {{ doll.data.value.is_available ? t('dolls.directory.available') : t('dolls.directory.busy') }}
          </span>
        </header>

        <p class="text-sm leading-relaxed whitespace-pre-line">{{ doll.data.value.bio }}</p>

        <div class="flex flex-wrap gap-4 text-sm text-[var(--text-muted)]">
          <span>{{ t('dolls.profile.specialties') }}: {{ doll.data.value.specialties.join(', ') || '—' }}</span>
          <span>{{ t('dolls.profile.languages') }}: {{ doll.data.value.languages.join(', ') || '—' }}</span>
          <span>{{ t('dolls.profile.tone') }}: {{ doll.data.value.tone_tags.join(', ') || '—' }}</span>
        </div>

        <div class="flex flex-wrap gap-4 text-sm">
          <span>{{ t(`dolls.rateType.${doll.data.value.rate_type}`) }}</span>
          <span v-if="doll.data.value.rating_avg !== null">
            {{
              t('dolls.directory.rating', {
                avg: doll.data.value.rating_avg.toFixed(1),
                count: doll.data.value.rating_count,
              })
            }}
          </span>
          <span v-else class="text-[var(--text-muted)]">{{ t('dolls.rating.notEnough') }}</span>
          <span>{{ t('dolls.profile.completed', { count: doll.data.value.completed_requests_count }) }}</span>
        </div>

        <RouterLink :to="{ name: 'doll-request-new', query: { handle } }">
          <BaseButton :disabled="!doll.data.value.is_available">
            {{ t('dolls.profile.requestHelp') }}
          </BaseButton>
        </RouterLink>

        <!-- Una persona se reporta por handle: el cliente nunca tiene su uuid. -->
        <SafetyActions
          class="border-t border-[var(--border-soft)] pt-3"
          reportable-type="user"
          :postal-handle="doll.data.value.doll.postal_handle"
        />
      </template>
    </section>
  </AppLayout>
</template>
