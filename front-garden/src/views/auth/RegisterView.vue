<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useApiError } from '@/composables/useApiError'
import { currentLocale } from '@/i18n'
import AuthLayout from '@/layouts/AuthLayout.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AlertBox from '@/components/ui/AlertBox.vue'

const { t } = useI18n()
const auth = useAuthStore()
const { messageFor, fieldErrors } = useApiError()

const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  birth_date: '',
  accepts_terms: false,
})
const errors = ref<Record<string, string>>({})
const banner = ref('')
const loading = ref(false)
const done = ref(false)

async function submit() {
  loading.value = true
  banner.value = ''
  errors.value = {}
  try {
    await auth.register({ ...form, timezone, locale: currentLocale() })
    done.value = true
  } catch (error) {
    errors.value = fieldErrors(error)
    banner.value = messageFor(error)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout :title="t('auth.register.title')">
    <div v-if="done" class="flex flex-col gap-4">
      <AlertBox kind="success">
        <p class="font-medium">{{ t('auth.register.done.title') }}</p>
        <p>{{ t('auth.register.done.body', { email: form.email }) }}</p>
      </AlertBox>
      <RouterLink
        :to="{ name: 'login' }"
        class="text-center text-sm text-[var(--accent)] underline underline-offset-4"
      >
        {{ t('auth.register.signIn') }}
      </RouterLink>
    </div>

    <form v-else class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <AlertBox v-if="banner" kind="error">{{ banner }}</AlertBox>

      <BaseInput
        v-model="form.name"
        :label="t('common.name')"
        autocomplete="name"
        required
        :error="errors.name"
      />
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
      <BaseInput
        v-model="form.birth_date"
        :label="t('common.birthDate')"
        type="date"
        required
        :hint="t('auth.register.minAgeNote')"
        :error="errors.birth_date"
      />
      <BaseCheckbox
        v-model="form.accepts_terms"
        :label="t('auth.register.acceptTerms')"
        :error="errors.accepts_terms"
      />

      <BaseButton type="submit" :loading="loading" block>
        {{ t('auth.register.submit') }}
      </BaseButton>
    </form>

    <template v-if="!done" #footer>
      {{ t('auth.register.haveAccount') }}
      <RouterLink :to="{ name: 'login' }" class="text-[var(--accent)] underline underline-offset-4">
        {{ t('auth.register.signIn') }}
      </RouterLink>
    </template>
  </AuthLayout>
</template>
