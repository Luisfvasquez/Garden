<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useUpdateProfile, useUploadAvatar } from '@/composables/useAccount'
import { useApiError } from '@/composables/useApiError'
import { useUiStore } from '@/stores/ui'
import SettingsCard from './SettingsCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AlertBox from '@/components/ui/AlertBox.vue'

const { t } = useI18n()
const auth = useAuthStore()
const ui = useUiStore()
const { messageFor, fieldErrors } = useApiError()
const updateProfile = useUpdateProfile()
const uploadAvatar = useUploadAvatar()

const form = reactive({
  name: '',
  pen_name: '',
  bio: '',
  country_code: '',
  timezone: '',
})
const errors = ref<Record<string, string>>({})
const banner = ref('')

watch(
  () => auth.user,
  (u) => {
    if (!u) return
    form.name = u.name
    form.pen_name = u.pen_name ?? ''
    form.bio = u.bio ?? ''
    form.country_code = u.country_code ?? ''
    form.timezone = u.timezone
  },
  { immediate: true },
)

async function save() {
  banner.value = ''
  errors.value = {}
  try {
    await updateProfile.mutateAsync({
      name: form.name,
      pen_name: form.pen_name || null,
      bio: form.bio || null,
      country_code: form.country_code || null,
      timezone: form.timezone,
    })
    ui.pushToast('success', t('settings.saved'))
  } catch (error) {
    errors.value = fieldErrors(error)
    banner.value = messageFor(error)
  }
}

async function onAvatar(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  try {
    await uploadAvatar.mutateAsync(file)
    ui.pushToast('success', t('settings.avatarUpdated'))
  } catch (error) {
    ui.pushToast('error', messageFor(error))
  }
}
</script>

<template>
  <SettingsCard :title="t('settings.profile.title')">
    <AlertBox v-if="banner" kind="error">{{ banner }}</AlertBox>

    <div class="flex items-center gap-4">
      <img
        v-if="auth.user?.avatar_url"
        :src="auth.user.avatar_url"
        alt=""
        class="size-14 rounded-full object-cover"
      />
      <div
        v-else
        class="grid size-14 place-items-center rounded-full bg-[var(--surface-sunken)] text-lg"
        aria-hidden="true"
      >
        {{ auth.user?.name?.[0] ?? '·' }}
      </div>
      <label class="cursor-pointer text-sm text-[var(--accent)] underline underline-offset-4">
        {{
          uploadAvatar.isPending.value ? t('common.loading') : t('settings.profile.changeAvatar')
        }}
        <input type="file" accept="image/*" class="sr-only" @change="onAvatar" />
      </label>
    </div>

    <BaseInput v-model="form.name" :label="t('common.name')" :error="errors.name" required />
    <BaseInput
      v-model="form.pen_name"
      :label="t('settings.profile.penName')"
      :hint="t('settings.profile.penNameHint')"
      :error="errors.pen_name"
    />
    <BaseInput v-model="form.bio" :label="t('settings.profile.bio')" :error="errors.bio" />
    <div class="grid grid-cols-2 gap-3">
      <BaseInput
        v-model="form.country_code"
        :label="t('settings.profile.country')"
        :hint="t('settings.profile.countryHint')"
        :error="errors.country_code"
      />
      <BaseInput
        v-model="form.timezone"
        :label="t('settings.profile.timezone')"
        :error="errors.timezone"
      />
    </div>

    <BaseButton :loading="updateProfile.isPending.value" @click="save">
      {{ t('settings.save') }}
    </BaseButton>
  </SettingsCard>
</template>
