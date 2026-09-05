<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { authApi } from '@/api/auth'
import { isApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useApiError } from '@/composables/useApiError'
import AuthLayout from '@/layouts/AuthLayout.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AlertBox from '@/components/ui/AlertBox.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { messageFor } = useApiError()

type Phase = 'pending' | 'checking' | 'success' | 'error'
const phase = ref<Phase>('pending')
const banner = ref('')
const resending = ref(false)
const resent = ref(false)

function q(key: string): string | null {
  const v = route.query[key]
  return typeof v === 'string' ? v : null
}

onMounted(async () => {
  const id = q('id')
  const hash = q('hash')
  const signature = q('signature')
  const expires = q('expires')
  if (!id || !hash || !signature) return

  phase.value = 'checking'
  try {
    await authApi.verifyEmail(`/auth/email/verify/${id}/${hash}`, {
      ...(expires ? { expires } : {}),
      signature,
    })
    if (auth.isAuthenticated) await auth.refresh()
    phase.value = 'success'
  } catch (error) {
    phase.value = 'error'
    banner.value =
      isApiError(error) && error.code === 'EMAIL_ALREADY_VERIFIED'
        ? t('auth.verify.alreadyVerified')
        : isApiError(error) && error.code === 'INVALID_VERIFICATION_LINK'
          ? t('auth.verify.invalidLink')
          : messageFor(error)
  }
})

async function resend() {
  resending.value = true
  banner.value = ''
  try {
    await auth.resendVerification()
    resent.value = true
  } catch (error) {
    banner.value = messageFor(error)
  } finally {
    resending.value = false
  }
}

function goToDesk() {
  router.push({ name: 'desk' })
}
</script>

<template>
  <AuthLayout :title="t('auth.verify.title')">
    <div class="flex flex-col gap-4">
      <p v-if="phase === 'checking'" class="text-sm text-[var(--text-muted)]">
        {{ t('auth.verify.checking') }}
      </p>

      <template v-else-if="phase === 'success'">
        <AlertBox kind="success">{{ t('auth.verify.success') }}</AlertBox>
        <BaseButton block @click="goToDesk">{{ t('auth.verify.goToDesk') }}</BaseButton>
      </template>

      <template v-else-if="phase === 'error'">
        <AlertBox kind="error">{{ banner }}</AlertBox>
        <BaseButton v-if="auth.isAuthenticated" :loading="resending" block @click="resend">
          {{ t('auth.verify.resend') }}
        </BaseButton>
      </template>

      <template v-else>
        <p class="text-sm text-[var(--text-muted)]">{{ t('auth.verify.pending') }}</p>
        <AlertBox v-if="resent" kind="success">{{ t('auth.verify.resent') }}</AlertBox>
        <AlertBox v-else-if="banner" kind="error">{{ banner }}</AlertBox>
        <BaseButton v-if="auth.isAuthenticated" :loading="resending" block @click="resend">
          {{ t('auth.verify.resend') }}
        </BaseButton>
      </template>
    </div>

    <template #footer>
      <RouterLink :to="{ name: 'login' }" class="text-[var(--accent)] underline underline-offset-4">
        {{ t('auth.forgot.backToLogin') }}
      </RouterLink>
    </template>
  </AuthLayout>
</template>
