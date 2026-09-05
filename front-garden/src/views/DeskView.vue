<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertBox from '@/components/ui/AlertBox.vue'

const { t } = useI18n()
const auth = useAuthStore()
</script>

<template>
  <AppLayout>
    <section class="flex flex-col gap-4">
      <h1 class="text-2xl">{{ t('nav.desk') }}</h1>

      <AlertBox v-if="auth.user && !auth.isVerified" kind="info">
        {{ t('auth.verify.pending') }}
        <RouterLink
          :to="{ name: 'verify-email' }"
          class="ml-1 text-[var(--accent)] underline underline-offset-4"
        >
          {{ t('auth.verify.title') }}
        </RouterLink>
      </AlertBox>

      <p class="text-[var(--text-muted)]">
        {{ auth.user?.name }} · <code>{{ auth.user?.postal_handle }}</code>
      </p>

      <!-- Fase 1: borradores, en tránsito y buzón viven aquí. -->
    </section>
  </AppLayout>
</template>
