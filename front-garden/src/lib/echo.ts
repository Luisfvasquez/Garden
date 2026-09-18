import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

/**
 * Reverb / Echo. ADR-0005: the ONLY real-time channel in the product is the
 * Doll chat. There is no presence, no global socket, no direct messaging — if
 * you are reaching for this file for anything else, read the ADR first.
 *
 * The connection is created lazily, the first time a chat is actually opened:
 * most sessions never touch a Doll request and shouldn't pay for a WebSocket.
 */

type EchoClient = Echo<'reverb'>

let echo: EchoClient | null = null

function config() {
  return {
    key: import.meta.env.VITE_REVERB_KEY as string | undefined,
    host: (import.meta.env.VITE_REVERB_HOST as string) || 'localhost',
    port: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    scheme: (import.meta.env.VITE_REVERB_SCHEME as string) || 'http',
  }
}

/** False when no key is configured — the chat then falls back to polling. */
export function isRealtimeConfigured(): boolean {
  return Boolean(config().key)
}

export function getEcho(): EchoClient | null {
  if (echo) return echo

  const { key, host, port, scheme } = config()
  if (!key) return null

  // laravel-echo looks for Pusher on window.
  ;(window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher

  echo = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    // The handshake goes through Sanctum like every other call: same cookie,
    // same CSRF header (bootstrap/app.php pins it to `auth:sanctum`).
    authEndpoint: '/broadcasting/auth',
    withCredentials: true,
  })

  return echo
}

/** Drops the socket entirely. Called on logout so the next user starts clean. */
export function disconnectEcho(): void {
  echo?.disconnect()
  echo = null
}
