import { ref } from 'vue'
import { pushApi } from '@/api/push'

/** urlBase64 → Uint8Array, as required by PushManager.subscribe. */
function urlBase64ToUint8Array(base64: string): Uint8Array<ArrayBuffer> {
  const padding = '='.repeat((4 - (base64.length % 4)) % 4)
  const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'))
  const out = new Uint8Array(new ArrayBuffer(raw.length))
  for (let i = 0; i < raw.length; i += 1) out[i] = raw.charCodeAt(i)
  return out
}

export function usePush() {
  const supported = typeof navigator !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window
  const busy = ref(false)
  const error = ref<string | null>(null)
  const subscribed = ref(false)
  const lastSubscriptionId = ref<string | null>(null)

  async function refresh() {
    if (!supported) return
    const reg = await navigator.serviceWorker.ready
    subscribed.value = Boolean(await reg.pushManager.getSubscription())
  }

  async function enable() {
    if (!supported) return
    busy.value = true
    error.value = null
    try {
      const vapid = await pushApi.vapidKey()
      if (!vapid.enabled || !vapid.public_key) {
        error.value = 'push_not_configured'
        return
      }

      const permission = await Notification.requestPermission()
      if (permission !== 'granted') {
        error.value = 'permission_denied'
        return
      }

      const reg = await navigator.serviceWorker.ready
      const sub =
        (await reg.pushManager.getSubscription()) ??
        (await reg.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(vapid.public_key),
        }))

      const json = sub.toJSON()
      const result = await pushApi.subscribe({
        platform: 'web',
        endpoint: sub.endpoint,
        public_key: json.keys?.p256dh ?? '',
        auth_token: json.keys?.auth ?? '',
        device_name: navigator.userAgent.slice(0, 100),
      })
      lastSubscriptionId.value = result.id
      subscribed.value = true
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'unknown'
    } finally {
      busy.value = false
    }
  }

  async function disable() {
    if (!supported) return
    busy.value = true
    try {
      const reg = await navigator.serviceWorker.ready
      const sub = await reg.pushManager.getSubscription()
      if (sub) await sub.unsubscribe()
      if (lastSubscriptionId.value) await pushApi.unsubscribe(lastSubscriptionId.value)
      subscribed.value = false
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'unknown'
    } finally {
      busy.value = false
    }
  }

  return { supported, busy, error, subscribed, refresh, enable, disable }
}
