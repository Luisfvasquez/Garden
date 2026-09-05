<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { authApi } from '@/api/auth'
import { useApiError } from '@/composables/useApiError'
import AuthLayout from '@/layouts/AuthLayout.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AlertBox from '@/components/ui/AlertBox.vue'

const { t } = useI18n()
const { messageFor } = useApiError()

const email = ref('')
const loading = ref(false)
const done = ref(false)
const banner = ref('')

async function submit() {
  loading.value = true
  banner.value = ''
  try {
    await authApi.forgotPassword(email.value)
    done.value = true
  } catch (error) {
    banner.value = messageFor(error)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout :title="t('auth.forgot.title')">
    <div v-if="done">
      <AlertBox kind="success">{{ t('auth.forgot.done') }}</AlertBox>
    </div>

    <form v-else class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <p class="text-sm text-[var(--text-muted)]">{{ t('auth.forgot.body') }}</p>
      <AlertBox v-if="banner" kind="error">{{ banner }}</AlertBox>
      <BaseInput
        v-model="email"
        :label="t('common.email')"
        type="email"
        autocomplete="email"
        required
      />
      <BaseButton type="submit" :loading="loading" block>{{ t('auth.forgot.submit') }}</BaseButton>
    </form>

    <template #footer>
      <RouterLink :to="{ name: 'login' }" class="text-[var(--accent)] underline underline-offset-4">
        {{ t('auth.forgot.backToLogin') }}
      </RouterLink>
    </template>
  </AuthLayout>
</template>
