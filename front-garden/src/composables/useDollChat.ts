import { computed, onScopeDispose, ref, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { dollChatApi } from '@/api/dollChat'
import { qk } from '@/api/queryKeys'
import { getEcho, isRealtimeConfigured } from '@/lib/echo'
import type { CreateDollDraftInput, DollRequestStatus } from '@/types/api'

/** The chat channel only exists while the request is being worked on. */
export function isChannelOpen(status: DollRequestStatus | undefined): boolean {
  return status === 'in_progress' || status === 'awaiting_client'
}

export function useDollChatMessages(id: MaybeRefOrGetter<string | undefined>) {
  return useQuery({
    queryKey: computed(() => qk.dollRequests.messages(toValue(id) ?? 'none')),
    queryFn: () => dollChatApi.messages(toValue(id) as string),
    enabled: computed(() => Boolean(toValue(id))),
    // Without a socket this is the whole update mechanism, so keep it modest
    // but alive; with one, the socket invalidates and this is just a safety net.
    refetchInterval: isRealtimeConfigured() ? false : 15_000,
  })
}

export function useSendDollChatMessage(id: MaybeRefOrGetter<string | undefined>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: string) => dollChatApi.send(toValue(id) as string, body),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: qk.dollRequests.messages(toValue(id) as string) })
      // Sending flips in_progress ⇄ awaiting_client, so the request is stale too.
      void qc.invalidateQueries({ queryKey: qk.dollRequests.one(toValue(id) as string) })
    },
  })
}

export function useShareDollDraft(id: MaybeRefOrGetter<string | undefined>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateDollDraftInput) => dollChatApi.shareDraft(toValue(id) as string, input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: qk.dollRequests.messages(toValue(id) as string) })
    },
  })
}

export function useApproveDollDraft(id: MaybeRefOrGetter<string | undefined>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (draftId: string) => dollChatApi.approveDraft(toValue(id) as string, draftId),
    onSuccess: () => {
      // Approving completes the request and mints a letter: transcript, request
      // and the client's drawer of letters all move at once.
      void qc.invalidateQueries({ queryKey: qk.dollRequests.messages(toValue(id) as string) })
      void qc.invalidateQueries({ queryKey: qk.dollRequests.all })
      void qc.invalidateQueries({ queryKey: qk.letters.all })
    },
  })
}

/**
 * Subscribes to `private-doll-request.{id}` while the channel is open and
 * refetches the transcript on each event.
 *
 * The socket payload is deliberately thin (id + type, no body), so we treat an
 * event as "something changed" and re-read through the API, which re-checks
 * participation. Nothing confidential is trusted from the wire.
 */
export function useDollChatRealtime(
  id: MaybeRefOrGetter<string | undefined>,
  status: MaybeRefOrGetter<DollRequestStatus | undefined>,
) {
  const qc = useQueryClient()
  let joined: string | null = null

  /** Who is typing right now, by display name. Empty when nobody is. */
  const typingName = ref<string | null>(null)
  let typingTimer: ReturnType<typeof setTimeout> | null = null
  let lastWhisper = 0

  function clearTyping() {
    if (typingTimer) clearTimeout(typingTimer)
    typingTimer = null
    typingName.value = null
  }

  function leave() {
    if (joined) {
      getEcho()?.leave(`doll-request.${joined}`)
      joined = null
    }
    clearTyping()
  }

  watch(
    () => [toValue(id), toValue(status)] as const,
    ([requestId, requestStatus]) => {
      if (joined && joined !== requestId) leave()
      if (!requestId || !isChannelOpen(requestStatus) || joined) return

      const echo = getEcho()
      if (!echo) return // no key configured — the query polls instead

      const channel = echo.private(`doll-request.${requestId}`)

      channel.listen('.chat.message', () => {
        // Whoever was typing just sent it.
        clearTyping()
        void qc.invalidateQueries({ queryKey: qk.dollRequests.messages(requestId) })
        void qc.invalidateQueries({ queryKey: qk.dollRequests.one(requestId) })
      })

      // Typing is a whisper: client-to-client, never stored, never broadcast
      // by the server. It is presence-ish sugar, not conversation content —
      // nothing about it should survive the session (ADR-0005).
      channel.listenForWhisper('typing', (payload: { name?: string }) => {
        typingName.value = payload?.name ?? null
        if (typingTimer) clearTimeout(typingTimer)
        // No "stopped typing" event: it just lapses.
        typingTimer = setTimeout(() => {
          typingName.value = null
        }, 3000)
      })

      joined = requestId
    },
    { immediate: true },
  )

  /** Throttled to one whisper per second — this is a hint, not a keylogger. */
  function notifyTyping(name: string) {
    const requestId = toValue(id)
    if (!requestId || !joined) return

    const now = Date.now()
    if (now - lastWhisper < 1000) return
    lastWhisper = now

    getEcho()?.private(`doll-request.${requestId}`).whisper('typing', { name })
  }

  onScopeDispose(leave)

  return { isRealtime: isRealtimeConfigured(), typingName, notifyTyping }
}
