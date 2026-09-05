<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import AppLayout from '@/layouts/AppLayout.vue'

const { t } = useI18n()
const auth = useAuthStore()
</script>

<template>
  <AppLayout>
    <section class="flex flex-col gap-4">
      <h1 class="text-2xl">{{ t('nav.settings') }}</h1>

      <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
        <dt class="text-[var(--text-muted)]">{{ t('common.name') }}</dt>
        <dd>{{ auth.user?.name }}</dd>
        <dt class="text-[var(--text-muted)]">{{ t('common.email') }}</dt>
        <dd>{{ auth.user?.email }}</dd>
        <dt class="text-[var(--text-muted)]">postal_handle</dt>
        <dd>
          <code>{{ auth.user?.postal_handle }}</code>
        </dd>
        <dt class="text-[var(--text-muted)]">timezone</dt>
        <dd>{{ auth.user?.timezone }}</dd>
        <dt class="text-[var(--text-muted)]">{{ t('common.language') }}</dt>
        <dd>{{ auth.user?.locale }}</dd>
      </dl>

      <!-- Fase 1+: edición de perfil, notificaciones, privacidad, bloqueos, dispositivos. -->
    </section>
  </AppLayout>
</template>
