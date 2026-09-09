<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRegisterSW } from 'virtual:pwa-register/vue'

const { t } = useI18n()
const { needRefresh, offlineReady, updateServiceWorker } = useRegisterSW()

function dismiss() {
  needRefresh.value = false
  offlineReady.value = false
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="needRefresh || offlineReady"
      class="fixed inset-x-0 bottom-0 z-50 flex justify-center p-4"
      role="status"
    >
      <div
        class="flex items-center gap-3 rounded-md border border-[var(--border-soft)] bg-[var(--surface-raised)] px-4 py-2 text-sm shadow-lg"
      >
        <span>{{ needRefresh ? t('pwa.updateReady') : t('pwa.offlineReady') }}</span>
        <button
          v-if="needRefresh"
          type="button"
          class="font-medium text-[var(--accent)] underline underline-offset-4"
          @click="updateServiceWorker(true)"
        >
          {{ t('pwa.reload') }}
        </button>
        <button
          type="button"
          class="text-[var(--text-muted)] hover:text-[var(--text)]"
          :aria-label="t('common.close')"
          @click="dismiss"
        >
          ×
        </button>
      </div>
    </div>
  </Teleport>
</template>
