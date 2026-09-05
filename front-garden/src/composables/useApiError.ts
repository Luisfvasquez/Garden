import { useI18n } from 'vue-i18n'
import { isApiError } from '@/api/client'

/**
 * Turns any thrown value into a user-facing string, keyed by `error_code`
 * (never by the server's `message`). Falls back through:
 * validation → specific code → generic.
 */
export function useApiError() {
  const { t, te } = useI18n()

  function messageFor(error: unknown): string {
    if (!isApiError(error)) return t('errors.generic')

    if (error.code === 'NETWORK') return t('errors.network')
    if (error.status === 422 && Object.keys(error.fields).length > 0) {
      return t('errors.validation')
    }
    if (te(`errors.${error.code}`)) return t(`errors.${error.code}`)
    return t('errors.generic')
  }

  /** Field-level errors from a 422, ready to bind to inputs. */
  function fieldErrors(error: unknown): Record<string, string> {
    if (!isApiError(error)) return {}
    return Object.fromEntries(
      Object.entries(error.fields).map(([key, messages]) => [key, messages[0] ?? '']),
    )
  }

  return { messageFor, fieldErrors }
}
