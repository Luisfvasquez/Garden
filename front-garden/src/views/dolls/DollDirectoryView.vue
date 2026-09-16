<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDollDirectory } from '@/composables/useDolls'
import { useApiError } from '@/composables/useApiError'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'

const { t } = useI18n()
const { messageFor } = useApiError()

const specialty = ref<string | undefined>(undefined)
const language = ref<string | undefined>(undefined)
const onlyAvailable = ref(false)

const dolls = useDollDirectory(specialty, language, () => (onlyAvailable.value ? true : undefined))
</script>

<template>
  <AppLayout>
    <section class="flex flex-col gap-4">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl">{{ t('dolls.directory.title') }}</h1>
        <RouterLink :to="{ name: 'doll-become' }" class="text-sm text-[var(--accent)] underline">
          {{ t('dolls.directory.becomeADoll') }}
        </RouterLink>
      </div>
      <p class="text-sm text-[var(--text-muted)]">{{ t('dolls.directory.intro') }}</p>

      <div class="flex flex-wrap gap-2">
        <input
          v-model="specialty"
          :placeholder="t('dolls.directory.specialtyPlaceholder')"
          class="rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1 text-sm"
        />
        <input
          v-model="language"
          :placeholder="t('dolls.directory.languagePlaceholder')"
          class="w-24 rounded border border-[var(--border-soft)] bg-[var(--surface-raised)] px-2 py-1 text-sm"
        />
        <label class="flex items-center gap-1 text-sm text-[var(--text-muted)]">
          <input v-model="onlyAvailable" type="checkbox" />
          {{ t('dolls.directory.onlyAvailable') }}
        </label>
      </div>

      <SpinnerDots v-if="dolls.isPending.value" :label="t('common.loading')" />
      <AlertBox v-else-if="dolls.isError.value" kind="error">{{ messageFor(dolls.error.value) }}</AlertBox>
      <p v-else-if="!dolls.data.value?.data.length" class="text-sm text-[var(--text-muted)]">
        {{ t('dolls.directory.empty') }}
      </p>

      <ul v-else class="grid gap-3 sm:grid-cols-2">
        <li v-for="d in dolls.data.value?.data" :key="d.id">
          <RouterLink
            :to="{ name: 'doll-profile', params: { id: d.doll.postal_handle } }"
            class="flex h-full flex-col gap-2 rounded-md border border-[var(--border-soft)] p-4 hover:bg-[var(--surface-sunken)]"
          >
            <div class="flex items-center justify-between">
              <span class="font-serif text-lg">{{ d.doll.display_name }}</span>
              <span
                class="rounded-full px-2 py-0.5 text-xs"
                :class="d.is_available ? 'bg-[var(--color-sage-500)]/20' : 'bg-[var(--border-soft)]'"
              >
                {{ d.is_available ? t('dolls.directory.available') : t('dolls.directory.busy') }}
              </span>
            </div>
            <p class="text-sm">{{ d.headline }}</p>
            <p class="text-xs text-[var(--text-muted)]">
              <span v-for="s in d.specialties" :key="s">#{{ s }} </span>
            </p>
            <p class="mt-auto text-xs text-[var(--text-muted)]">
              {{ t('dolls.directory.rating', { avg: d.rating_avg.toFixed(1), count: d.rating_count }) }}
              · {{ t(`dolls.rateType.${d.rate_type}`) }}
            </p>
          </RouterLink>
        </li>
      </ul>
    </section>
  </AppLayout>
</template>
