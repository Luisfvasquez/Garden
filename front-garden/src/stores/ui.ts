import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { ThemePreference } from '@/types/api'

const THEME_KEY = 'evergarden.theme'
let toastSeq = 0

export interface Toast {
  id: number
  kind: 'info' | 'success' | 'error'
  text: string
  timeout: number
}

function storedTheme(): ThemePreference {
  try {
    const v = localStorage.getItem(THEME_KEY)
    if (v === 'light' || v === 'dark' || v === 'system') return v
  } catch {
    /* storage unavailable */
  }
  return 'system'
}

function prefersDark(): boolean {
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false
}

export const useUiStore = defineStore('ui', () => {
  const theme = ref<ThemePreference>(storedTheme())
  const toasts = ref<Toast[]>([])

  function applyTheme(): void {
    const dark = theme.value === 'dark' || (theme.value === 'system' && prefersDark())
    document.documentElement.classList.toggle('dark', dark)
  }

  function setTheme(next: ThemePreference): void {
    theme.value = next
    try {
      localStorage.setItem(THEME_KEY, next)
    } catch {
      /* storage unavailable */
    }
    applyTheme()
  }

  function toggleTheme(): void {
    const dark = document.documentElement.classList.contains('dark')
    setTheme(dark ? 'light' : 'dark')
  }

  function dismissToast(id: number): void {
    toasts.value = toasts.value.filter((toast) => toast.id !== id)
  }

  function pushToast(kind: Toast['kind'], text: string, timeout = 5000): number {
    const id = ++toastSeq
    toasts.value.push({ id, kind, text, timeout })
    if (timeout > 0) window.setTimeout(() => dismissToast(id), timeout)
    return id
  }

  return { theme, toasts, applyTheme, setTheme, toggleTheme, pushToast, dismissToast }
})
