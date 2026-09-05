<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue'
import { RouterView } from 'vue-router'
import { useUiStore } from '@/stores/ui'
import ToastHost from '@/components/ui/ToastHost.vue'

const ui = useUiStore()
const media = window.matchMedia?.('(prefers-color-scheme: dark)')

function onSystemThemeChange() {
  if (ui.theme === 'system') ui.applyTheme()
}

onMounted(() => {
  ui.applyTheme()
  media?.addEventListener('change', onSystemThemeChange)
})

onBeforeUnmount(() => media?.removeEventListener('change', onSystemThemeChange))
</script>

<template>
  <RouterView />
  <ToastHost />
</template>
