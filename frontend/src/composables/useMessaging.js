import { localizeValidationMessage } from '../utils/validationLabels'
import { computed, inject, nextTick, onMounted, onUnmounted, provide, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import * as api from '../api/messaging.api'
import { domainError } from '../api/domain.api'

export const messagingKey = Symbol('marketplace-messaging')
export const useMessaging = () => inject(messagingKey, null)
const merge = (old, rows) => [...new Map([...old, ...rows].map((row) => [row.id, row])).values()]
const messageError = (error) => error.response?.data?.errors?.body?.[0] ? localizeValidationMessage(error.response.data.errors.body[0], 'body') : domainError(error)

export function provideMessaging() {
  const auth = useAuthStore(),
    route = useRoute()
  const opened = ref(false),
    active = ref(null),
    conversations = ref([]),
    messages = ref([])
  const unreadTotal = ref(0),
    listBusy = ref(false),
    historyBusy = ref(false),
    sending = ref(false)
  const listError = ref(''),
    error = ref(''),
    draft = ref(''),
    hasOlder = ref(false),
    hasMore = ref(false)
  let timer,
    mounted = false,
    generation = 0,
    selection = 0,
    listRequest = 0,
    page = 1,
    readId = '0',
    updating = false,
    again = false,
    live = false,
    release = () => {},
    stopLive = () => {},
    historyRequest
  const available = () => mounted && auth.isAuthenticated && !document.hidden
  const current = (g, s) =>
    g === generation && (s === undefined || s === selection) && auth.isAuthenticated
  const unreadLabel = computed(() => (unreadTotal.value > 99 ? '99+' : String(unreadTotal.value)))

  async function refreshList(more = false) {
    if (!auth.isAuthenticated) return
    const g = generation,
      request = ++listRequest,
      nextPage = more ? page + 1 : 1
    listBusy.value = true
    listError.value = ''
    try {
      const result = await api.listConversations({ page: nextPage, per_page: 20 })
      if (!current(g) || request !== listRequest) return
      conversations.value = more
        ? merge(conversations.value, result.data)
        : merge(
            result.data,
            conversations.value.filter(
              (row) => !result.data.some((latest) => latest.id === row.id),
            ),
          )
      unreadTotal.value = result.unread_total
      if (active.value)
        active.value = conversations.value.find((row) => row.id === active.value.id) || active.value
      if (more || page === 1) {
        page = nextPage
        hasMore.value = !!result.links?.next
      }
    } catch (failure) {
      if (current(g) && request === listRequest) listError.value = domainError(failure)
    } finally {
      if (current(g) && request === listRequest) listBusy.value = false
    }
  }

  async function markVisible(g, s) {
    if (!available() || !opened.value || !current(g, s) || !messages.value.length) return
    const id = messages.value.at(-1).id
    if (BigInt(id) <= BigInt(readId)) return
    const result = await api.markConversationRead(active.value.id, id)
    if (!current(g, s)) return
    readId = result.last_read_message_id
    const previous = active.value.unread_count || 0
    active.value.unread_count = result.unread_count
    unreadTotal.value = Math.max(0, unreadTotal.value - previous + result.unread_count)
  }

  async function refreshMessages(mode = 'poll') {
    if (!active.value) return
    if (updating) {
      // A realtime signal during a fetch must not be lost: fetch again once this one settles.
      if (mode === 'poll') again = true
      return historyRequest
    }
    const g = generation,
      s = selection,
      id = active.value.id
    if (mode !== 'poll') historyBusy.value = true
    updating = true
    error.value = ''
    historyRequest = (async () => {
      try {
        const params = { per_page: 30 }
        if (mode === 'older' && messages.value.length) params.before_id = messages.value[0].id
        if (mode === 'poll' && messages.value.length) params.after_id = messages.value.at(-1).id
        const result = await api.listMessages(id, params)
        if (!current(g, s)) return
        messages.value = merge(messages.value, result.data).sort((a, b) =>
          BigInt(a.id) < BigInt(b.id) ? -1 : BigInt(a.id) > BigInt(b.id) ? 1 : 0,
        )
        if (mode !== 'poll' || !params.after_id) hasOlder.value = !!result.links?.next
        await nextTick()
        await markVisible(g, s)
      } catch (failure) {
        if (current(g, s)) error.value = domainError(failure)
      } finally {
        if (current(g, s)) {
          historyBusy.value = false
          updating = false
          if (again) {
            again = false
            void refreshMessages()
          }
        }
      }
    })()
    return historyRequest
  }

  async function selectConversation(conversation) {
    selection++
    active.value = conversation
    messages.value = []
    draft.value = ''
    error.value = ''
    readId = '0'
    updating = again = false
    sending.value = false
    hasOlder.value = false
    opened.value = true
    await refreshMessages('initial')
  }

  async function send() {
    const body = draft.value.trim()
    if (
      !body ||
      Array.from(body).length > 1000 ||
      sending.value ||
      !active.value ||
      historyBusy.value
    )
      return
    const g = generation,
      s = selection,
      id = active.value.id
    sending.value = true
    error.value = ''
    try {
      const result = await api.sendMessage(id, body)
      if (!current(g, s)) return
      draft.value = ''
      // Fetch in order instead of inserting a sent message past unseen incoming messages.
      await refreshMessages()
      if (!messages.value.some((row) => row.id === result.id)) await refreshMessages()
      await refreshList()
    } catch (failure) {
      if (current(g, s)) error.value = messageError(failure)
    } finally {
      if (current(g, s)) sending.value = false
    }
  }

  async function start(productId) {
    const g = generation
    const conversation = await api.startConversation(productId)
    if (!current(g)) return
    conversations.value = merge([conversation], conversations.value)
    await selectConversation(conversation)
    await refreshList()
  }

  function stop() {
    clearTimeout(timer)
    timer = undefined
  }
  // Polling is only the fallback while the private realtime channel is not subscribed.
  function schedule() {
    stop()
    if (available() && !live) timer = setTimeout(tick, 4000)
  }
  async function tick() {
    if (!available()) return
    await refreshList()
    if (opened.value && active.value && available()) await refreshMessages()
    schedule()
  }
  function visibility() {
    if (document.hidden) stop()
    else void tick()
  }
  function back() {
    selection++
    active.value = null
    messages.value = []
    draft.value = ''
    historyBusy.value = false
    sending.value = false
    updating = again = false
  }
  function close() {
    opened.value = false
    selection++
    historyBusy.value = false
    sending.value = false
    updating = again = false
  }
  function received(payload) {
    if (!mounted || !auth.isAuthenticated) return
    const id = String(payload?.conversation_id || ''),
      messageId = String(payload?.message?.id || '')
    // This tab already holds it (its own send); send() refreshes the list itself.
    if (active.value?.id === id && messages.value.some((row) => row.id === messageId)) return
    void refreshList()
    if (opened.value && active.value?.id === id && available()) void refreshMessages()
  }
  async function subscribe(id, g) {
    release()
    stopLive()
    live = false
    if (!id) return
    const { realtimeConnection, subscribeRealtime } = await import('./auctionEcho')
    if (!current(g) || !mounted) return
    release = subscribeRealtime(
      'private',
      id,
      {
        MessageSent: (payload) => {
          if (current(g)) received(payload)
        },
        ConversationRead: () => {
          if (current(g) && mounted) void refreshList()
        },
      },
      () => {
        if (!current(g) || !mounted) return
        live = true
        stop()
        // Catch up on anything sent while the socket was down.
        if (available()) void tick()
      },
    )
    stopLive = watch(realtimeConnection, (state) => {
      if (state === 'connected' || !live) return
      live = false
      schedule()
    })
  }
  async function open() {
    opened.value = true
    await refreshList()
    if (active.value) await refreshMessages()
  }

  watch(
    () => auth.user?.id,
    () => {
      generation++
      selection++
      listRequest++
      stop()
      opened.value = false
      active.value = null
      messages.value = []
      conversations.value = []
      unreadTotal.value = 0
      draft.value = ''
      listError.value = ''
      error.value = ''
      listBusy.value = false
      historyBusy.value = false
      sending.value = false
      updating = false
      page = 1
      hasMore.value = false
      again = false
      if (available()) void tick()
      void subscribe(auth.user?.id, generation)
    },
  )
  watch(() => route.fullPath, close)
  onMounted(() => {
    mounted = true
    document.addEventListener('visibilitychange', visibility)
    if (available()) void tick()
    void subscribe(auth.user?.id, generation)
  })
  onUnmounted(() => {
    mounted = false
    generation++
    selection++
    stop()
    release()
    stopLive()
    release = stopLive = () => {}
    live = false
    document.removeEventListener('visibilitychange', visibility)
  })
  const state = {
    auth,
    opened,
    active,
    conversations,
    messages,
    unreadTotal,
    unreadLabel,
    listBusy,
    historyBusy,
    sending,
    listError,
    error,
    draft,
    hasOlder,
    hasMore,
    refreshList,
    refreshMessages,
    selectConversation,
    send,
    start,
    close,
    open,
    back,
  }
  provide(messagingKey, state)
  return state
}
