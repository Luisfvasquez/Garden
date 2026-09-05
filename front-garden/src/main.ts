import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { i18n } from './i18n'
import { clientHooks } from './api/client'
import { useAuthStore } from './stores/auth'
import { useUiStore } from './stores/ui'
import './assets/main.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(i18n)

// Wire the HTTP client's cross-cutting reactions without importing the app into it.
const auth = useAuthStore(pinia)
const ui = useUiStore(pinia)
const t = i18n.global.t

clientHooks.onUnauthenticated = () => {
  auth.clear()
  const current = router.currentRoute.value
  if (current.meta.auth) {
    void router.push({ name: 'login', query: { redirect: current.fullPath } })
  }
}

clientHooks.onRateLimited = (retryAfter) => {
  const suffix = retryAfter ? ` (${retryAfter}s)` : ''
  ui.pushToast('error', t('errors.RATE_LIMITED') + suffix)
}

clientHooks.onUpgradeRequired = () => {
  void router.push({ name: 'upgrade' })
}

app.mount('#app')
