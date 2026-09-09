/* Web Push handlers, merged into the generated service worker (vite.config.ts).
   Payload shape comes from the backend: { title, body, data: { url } }. */

self.addEventListener('push', (event) => {
  let payload = {}
  try {
    payload = event.data ? event.data.json() : {}
  } catch {
    payload = { title: 'Evergarden', body: event.data ? event.data.text() : '' }
  }

  const title = payload.title || 'Evergarden'
  const options = {
    body: payload.body || '',
    icon: '/icons/192.png',
    badge: '/icons/192.png',
    data: { url: (payload.data && payload.data.url) || '/escritorio' },
    tag: payload.data && payload.data.tag,
  }

  event.waitUntil(self.registration.showNotification(title, options))
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  const url = (event.notification.data && event.notification.data.url) || '/escritorio'

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      for (const client of clients) {
        if ('focus' in client) {
          client.navigate(url)
          return client.focus()
        }
      }
      return self.clients.openWindow(url)
    }),
  )
})
