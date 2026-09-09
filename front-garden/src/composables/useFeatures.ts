import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { featuresApi } from '@/api/features'
import { qk } from '@/api/queryKeys'

export function useFeatures() {
  return useQuery({
    queryKey: qk.features,
    queryFn: () => featuresApi.map(),
    staleTime: 1000 * 60 * 5,
  })
}

/** Reactive boolean for one flag. Defaults to false until the map loads. */
export function useFeature(key: string) {
  const query = useFeatures()
  return computed(() => query.data.value?.[key] ?? false)
}
