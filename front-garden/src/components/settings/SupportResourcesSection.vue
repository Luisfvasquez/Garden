<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useSupportResources } from '@/composables/useSupportResources'
import SettingsCard from './SettingsCard.vue'
import SpinnerDots from '@/components/ui/SpinnerDots.vue'
import AlertBox from '@/components/ui/AlertBox.vue'

const { t } = useI18n()
const auth = useAuthStore()

const country = computed(() => auth.user?.country_code ?? null)
const query = useSupportResources(country)
</script>

<template>
  <SettingsCard
    :title="t('settings.support.title')"
    :description="t('settings.support.description')"
  >
    <SpinnerDots v-if="query.isPending.value" />

    <AlertBox v-else-if="query.isError.value" kind="error">
      {{ t('settings.support.error') }}
    </AlertBox>

    <p v-else-if="!query.data.value?.length" class="text-sm text-[var(--text-muted)]">
      {{ t('settings.support.empty') }}
    </p>

    <ul v-else class="flex flex-col divide-y divide-[var(--border-soft)]">
      <li v-for="r in query.data.value" :key="r.id" class="flex flex-col gap-1 py-3">
        <div class="flex items-baseline justify-between gap-2">
          <span class="font-medium">{{ r.name }}</span>
          <span v-if="r.is_global" class="text-xs text-[var(--text-muted)]">
            {{ t('settings.support.global') }}
          </span>
        </div>
        <p v-if="r.description" class="text-sm text-[var(--text-muted)]">{{ r.description }}</p>
        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
          <a
            v-if="r.phone"
            :href="`tel:${r.phone.replace(/\s+/g, '')}`"
            class="text-[var(--accent)] underline underline-offset-4"
          >
            {{ t('settings.support.callLabel', { phone: r.phone }) }}
          </a>
          <a
            v-if="r.url"
            :href="r.url"
            target="_blank"
            rel="noopener noreferrer"
            class="text-[var(--accent)] underline underline-offset-4"
          >
            {{ t('settings.support.siteLabel') }}
          </a>
          <span v-if="r.hours" class="text-[var(--text-muted)]">{{ r.hours }}</span>
        </div>
      </li>
    </ul>
  </SettingsCard>
</template>
