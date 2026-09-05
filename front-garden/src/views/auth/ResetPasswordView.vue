<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { authApi } from '@/api/auth'
import { useUiStore } from '@/stores/ui'
import { useApiError } from '@/composables/useApiError'
import AuthLayout from '@/layouts/AuthLayout.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AlertBox from '@/components/ui/AlertBox.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const { messageFor, fieldErrors } = useApiError()

const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))
const email = computed(() => (typeof route.query.email === 'string' ? route.query.email : ''))

const form = reactive({ password: '', password_confirmation: '' })
const errors = ref<Record<string, string>>({})
const banner = ref('')
const loading = ref(false)

async function submit() {
  loading.value = true
  banner.value = ''
  errors.value = {}
  try {
    await authApi.resetPassword({
      token: token.value,
      email: email.value,
      password: form.password,
      password_confirmation: form.password_confirmation,
    })
    ui.pushToast('success', t('auth.reset.done'))
    await router.push({ name: 'login' })
  } catch (error) {
    errors.value = fieldErrors(error)
    banner.value = messageFor(error)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout :title="t('auth.reset.title')">
    <AlertBox v-if="!token || !email" kind="error">{{ t('auth.reset.invalidToken') }}</AlertBox>

    <form v-else class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <AlertBox v-if="banner" kind="error">{{ banner }}</AlertBox>
      <BaseInput :model-value="email" :label="t('common.email')" type="email" disabled />
      <BaseInput
        v-model="form.password"
        :label="t('common.password')"
        type="password"
        autocomplete="new-password"
        required
        :error="errors.password"
      />
      <BaseInput
        v-model="form.password_confirmation"
        :label="t('common.passwordConfirm')"
        type="password"
        autocomplete="new-password"
        required
        :error="errors.password_confirmation"
      />
      <BaseButton type="submit" :loading="loading" block>{{ t('auth.reset.submit') }}</BaseButton>
    </form>

    <template #footer>
      <RouterLink :to="{ name: 'login' }" class="text-[var(--accent)] underline underline-offset-4">
        {{ t('auth.forgot.backToLogin') }}
      </RouterLink>
    </template>
  </AuthLayout>
</template>
