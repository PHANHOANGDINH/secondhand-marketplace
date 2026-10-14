import { computed, ref, watch } from 'vue'
import { useAuthStore } from '../stores/auth'

const PREFIX = 'passlai:first-time'
export const firstTimeKeys = ['welcome', 'quick-listing', 'auction-room', 'shop-created', 'area']
const storageKey = (userId, key) => `${PREFIX}:${userId}:${key}`

// Storage can be missing, full or blocked. Any failure counts as "already seen",
// so a broken browser never shows the same guide again and again.
function hasSeen(userId, key) {
  if (!userId) return true
  try {
    return window.localStorage.getItem(storageKey(userId, key)) === '1'
  } catch {
    return true
  }
}
function remember(userId, key) {
  if (!userId) return
  try {
    window.localStorage.setItem(storageKey(userId, key), '1')
  } catch {
    /* the guide is simply closed for this page view */
  }
}

/** Forget every guide of one user ("Xem lại hướng dẫn"). Returns false when storage is unusable. */
export function resetFirstTime(userId) {
  if (!userId) return false
  try {
    for (const key of firstTimeKeys) window.localStorage.removeItem(storageKey(userId, key))
    return true
  } catch {
    return false
  }
}

/**
 * Per-user "first time" flag kept in localStorage.
 * `pending` is true until the signed-in user has seen or skipped the guide named `key`.
 */
export function useFirstTime(key) {
  const auth = useAuthStore()
  const seen = ref(true)
  const refresh = () => {
    seen.value = hasSeen(auth.user?.id, key)
  }
  watch(() => auth.user?.id, refresh, { immediate: true })
  function markSeen() {
    remember(auth.user?.id, key)
    seen.value = true
  }
  return { pending: computed(() => !seen.value), markSeen, refresh }
}
