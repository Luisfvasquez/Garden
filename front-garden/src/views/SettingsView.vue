<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import AppLayout from '@/layouts/AppLayout.vue'
import ProfileSection from '@/components/settings/ProfileSection.vue'
import NotificationsSection from '@/components/settings/NotificationsSection.vue'
import PrivacySection from '@/components/settings/PrivacySection.vue'
import BlocksSection from '@/components/settings/BlocksSection.vue'
import DevicesSection from '@/components/settings/DevicesSection.vue'
import AccountSection from '@/components/settings/AccountSection.vue'
import SupportResourcesSection from '@/components/settings/SupportResourcesSection.vue'
import PushSection from '@/components/settings/PushSection.vue'

const { t } = useI18n()
</script>

<template>
  <AppLayout>
    <div class="mx-auto flex max-w-xl flex-col gap-5">
      <h1 class="text-2xl">{{ t('nav.settings') }}</h1>
      <ProfileSection />
      <NotificationsSection />
      <PushSection />
      <PrivacySection />
      <BlocksSection />
      <DevicesSection />
      <SupportResourcesSection />
      <AccountSection />
    </div>
  </AppLayout>
</template>
