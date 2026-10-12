import { computed, inject, onMounted, onUnmounted, provide, ref, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import * as api from '../api/notification.api'
import { domainError } from '../api/domain.api'
export const notificationsKey = Symbol('marketplace-notifications')
export const useNotifications = () => inject(notificationsKey, null)
export function safeNotificationTarget(value) {
  return typeof value === 'string' &&
    /^\/(?:reports|auctions\/[1-9]\d*(?:\/checkout)?|seller\/reports|auctions\/[1-9]\d*|orders\/[1-9]\d*|seller\/orders\/[1-9]\d*)$/.test(
      value,
    )
    ? value
    : '/notifications'
}
export function provideNotifications() {
  const auth = useAuthStore(),
    rows = ref([]),
    unread = ref(0),
    loading = ref(false),
    writing = ref(false),
    error = ref(''),
    version = ref(0)
  let generation = 0,
    sequence = 0,
    revision = 0,
    countSequence = 0,
    disposed = false,
    release = () => {}
  const current = (g) => !disposed && generation === g && auth.isAuthenticated
  const sort = (items) =>
    items
      .sort((a, b) => b.created_at.localeCompare(a.created_at) || b.id.localeCompare(a.id))
      .slice(0, 20)
  async function load() {
    if (!auth.isAuthenticated || disposed) return
    const g = generation,
      s = ++sequence,
      r = revision
    loading.value = true
    error.value = ''
    try {
      const result = await api.listNotifications({ per_page: 20 })
      if (!current(g) || s !== sequence) return
      rows.value =
        r === revision
          ? result.data
          : sort([...new Map([...result.data, ...rows.value].map((row) => [row.id, row])).values()])
      if (r === revision) unread.value = result.unread_count
    } catch (e) {
      if (current(g) && s === sequence) error.value = domainError(e)
    } finally {
      if (current(g) && s === sequence) loading.value = false
    }
  }
  async function receive(row) {
    if (!auth.isAuthenticated || !row?.id || !row?.created_at) return
    const g = generation
    if (!rows.value.some((old) => old.id === row.id)) {
      rows.value = sort([row, ...rows.value])
      if (!row.read_at) unread.value++
      revision++
      version.value++
    }
    await load()
    const r = revision,
      c = ++countSequence
    try {
      const result = await api.getNotificationCount()
      if (current(g) && r === revision && c === countSequence) unread.value = result.unread_count
    } catch {
      /* The next database refresh restores the authoritative count. */
    }
  }
  async function read(row) {
    if (writing.value) return false
    if (row.read_at) return true
    const g = generation
    writing.value = true
    error.value = ''
    try {
      const result = await api.markNotificationRead(row.id)
      if (!current(g)) return false
      row.read_at = result.read_at
      rows.value = rows.value.map((old) => (old.id === result.id ? result : old))
      revision++
      version.value++
      await load()
      return true
    } catch (e) {
      if (current(g)) error.value = domainError(e)
      return false
    } finally {
      if (current(g)) writing.value = false
    }
  }
  async function readAll() {
    if (writing.value) return
    const g = generation
    writing.value = true
    error.value = ''
    try {
      await api.markAllNotificationsRead()
      if (!current(g)) return
      revision++
      version.value++
      await load()
    } catch (e) {
      if (current(g)) error.value = domainError(e)
    } finally {
      if (current(g)) writing.value = false
    }
  }
  const stop = watch(
    () => auth.user?.id,
    async (id) => {
      const g = ++generation
      sequence++
      release()
      release = () => {}
      rows.value = []
      unread.value = 0
      loading.value = writing.value = false
      error.value = ''
      version.value++
      if (!id) return
      load()
      const { subscribeRealtime } = await import('./auctionEcho')
      if (!current(g)) return
      release = subscribeRealtime(
        'private',
        id,
        {
          NotificationCreated: (row) => {
            if (current(g)) receive(row)
          },
        },
        () => {
          if (current(g)) {
            version.value++
            load()
          }
        },
      )
    },
    { immediate: true },
  )
  const refreshVisible = () => {
    if (!document.hidden) {
      version.value++
      load()
    }
  }
  onMounted(() => {
    window.addEventListener('focus', refreshVisible)
    window.addEventListener('online', refreshVisible)
    document.addEventListener('visibilitychange', refreshVisible)
  })
  onUnmounted(() => {
    disposed = true
    generation++
    stop()
    release()
    window.removeEventListener('focus', refreshVisible)
    window.removeEventListener('online', refreshVisible)
    document.removeEventListener('visibilitychange', refreshVisible)
  })
  const context = {
    rows,
    unread,
    loading,
    writing,
    error,
    version,
    load,
    read,
    readAll,
    unreadLabel: computed(() => (unread.value > 99 ? '99+' : String(unread.value))),
  }
  provide(notificationsKey, context)
  return context
}
