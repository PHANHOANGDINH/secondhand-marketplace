import { computed, ref, watch } from 'vue'

// Light or dark across the site. The visitor picks "system" (default), "light" or "dark"; the choice is
// kept in localStorage. index.html runs the same rule before the app mounts, so the first paint is right.
export const THEME_KEY = 'passdi:theme'
export const THEME_CHOICES = ['system', 'light', 'dark']
const query =
  typeof window !== 'undefined' && typeof window.matchMedia === 'function'
    ? window.matchMedia('(prefers-color-scheme: dark)')
    : null

export const resolveTheme = (preference, systemDark) =>
  preference === 'dark' || (preference !== 'light' && systemDark) ? 'dark' : 'light'

function readPreference() {
  try {
    const value = window.localStorage.getItem(THEME_KEY)
    return THEME_CHOICES.includes(value) ? value : 'system'
  } catch {
    return 'system'
  }
}

/** Puts the theme on <html>: our tokens follow data-theme, Bootstrap follows data-bs-theme. */
export function applyTheme(theme) {
  if (typeof document === 'undefined') return
  document.documentElement.setAttribute('data-theme', theme)
  document.documentElement.setAttribute('data-bs-theme', theme)
}

const preference = ref(typeof window === 'undefined' ? 'system' : readPreference())
const systemDark = ref(!!query?.matches)
query?.addEventListener?.('change', (event) => {
  systemDark.value = event.matches
})
const theme = computed(() => resolveTheme(preference.value, systemDark.value))
watch(theme, applyTheme, { immediate: true })

export function setThemePreference(value) {
  if (!THEME_CHOICES.includes(value)) return
  preference.value = value
  try {
    if (value === 'system') window.localStorage.removeItem(THEME_KEY)
    else window.localStorage.setItem(THEME_KEY, value)
  } catch {
    // Storage may be blocked (private window); the choice still applies until the page is closed.
  }
}

export function useTheme() {
  return {
    preference,
    theme,
    isDark: computed(() => theme.value === 'dark'),
    setPreference: setThemePreference,
    /** The header button: flips what is shown now and remembers it as an explicit choice. */
    toggle: () => setThemePreference(theme.value === 'dark' ? 'light' : 'dark'),
  }
}

/** Reads a token from the document, e.g. cssVar('--primary'); used by canvas charts. */
export function cssVar(name, fallback = '') {
  if (typeof document === 'undefined') return fallback
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback
}
