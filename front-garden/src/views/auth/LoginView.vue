<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useApiError } from '@/composables/useApiError'
import AuthLayout from '@/layouts/AuthLayout.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AlertBox from '@/components/ui/AlertBox.vue'

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { messageFor, fieldErrors } = useApiError()

const form = reactive({ email: '', password: '', remember: true })
const errors = ref<Record<string, string>>({})
const banner = ref('')
const loading = ref(false)

async function submit() {
  loading.value = true
  banner.value = ''
  errors.value = {}
  try {
    await auth.login({ ...form })
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : null
    await router.push(redirect ?? { name: 'desk' })
  } catch (error) {
    errors.value = fieldErrors(error)
    banner.value = messageFor(error)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout :title="t('auth.login.title')">
    <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <AlertBox v-if="banner" kind="error">{{ banner }}</AlertBox>

      <BaseInput
        v-model="form.email"
        :label="t('common.email')"
        type="email"
        autocomplete="email"
        required
        :error="errors.email"
      />
      <BaseInput
        v-model="form.password"
        :label="t('common.password')"
        type="password"
        autocomplete="current-password"
        required
        :error="errors.password"
      />
      <BaseCheckbox v-model="form.remember" :label="t('auth.login.remember')" />

      <BaseButton type="submit" :loading="loading" block>{{ t('auth.login.submit') }}</BaseButton>

      <RouterLink
        :to="{ name: 'forgot-password' }"
        class="text-center text-sm text-[var(--accent)] underline underline-offset-4"
      >
        {{ t('auth.login.forgot') }}
      </RouterLink>
    </form>

    <template #footer>
      {{ t('auth.login.noAccount') }}
      <RouterLink
        :to="{ name: 'register' }"
        class="text-[var(--accent)] underline underline-offset-4"
      >
        {{ t('auth.login.createAccount') }}
      </RouterLink>
    </template>
  </AuthLayout>
</template>
