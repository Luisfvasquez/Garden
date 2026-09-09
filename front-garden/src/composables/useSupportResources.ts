import { computed, unref, type MaybeRef } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { supportApi } from '@/api/support'
import { qk } from '@/api/queryKeys'

/**
 * Crisis / support helplines for a country (plus the global fallback). Public
 * endpoint — works logged out. Always reachable from settings, and surfaced
 * when the moderation filter detects a risk signal.
 */
export function useSupportResources(country: MaybeRef<string | null | undefined>, topic?: MaybeRef<string | undefined>) {
  const countryCode = computed(() => unref(country) ?? null)
  const topicValue = computed(() => unref(topic))

  return useQuery({
    queryKey: computed(() => qk.support.resources(countryCode.value, topicValue.value)),
    queryFn: () => supportApi.list({ country_code: countryCode.value, topic: topicValue.value }),
    staleTime: 1000 * 60 * 60,
  })
}
