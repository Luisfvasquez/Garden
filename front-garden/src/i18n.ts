import { createI18n } from 'vue-i18n'
import type { Locale } from '@/types/api'
import es from '@/locales/es.json'
import en from '@/locales/en.json'

export const SUPPORTED_LOCALES: Locale[] = ['es', 'en']
const STORAGE_KEY = 'evergarden.locale'

function initialLocale(): Locale {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved === 'es' || saved === 'en') return saved
  } catch {
    /* storage unavailable */
  }
  const nav = navigator.language.slice(0, 2)
  return nav === 'en' ? 'en' : 'es'
}

export const i18n = createI18n({
  legacy: false,
  locale: initialLocale(),
  fallbackLocale: 'es',
  messages: { es, en },
})

export function setLocale(locale: Locale): void {
  i18n.global.locale.value = locale
  document.documentElement.lang = locale
  try {
    localStorage.setItem(STORAGE_KEY, locale)
  } catch {
    /* storage unavailable */
  }
}

export function currentLocale(): Locale {
  return i18n.global.locale.value as Locale
}
