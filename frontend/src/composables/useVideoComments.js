import { localizeValidationMessage } from '../utils/validationLabels'
import { computed, onScopeDispose, ref, toValue, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import { listVideoComments, createVideoComment, hideVideoComment } from '../api/videoComment.api'
import { domainError } from '../api/domain.api'
import { realtimeConnection, subscribeRealtime } from './auctionEcho'

export function useVideoComments(videoId) {
  const auth = useAuthStore()
  const rows = ref([]),
    loading = ref(false),
    sending = ref(false),
    error = ref(''),
    actionError = ref('')
  const olderBusy = ref(false),
    hasOlder = ref(false),
    canModerate = ref(false),
    change = ref(0)
  const managing = ref(null)
  let generation = 0,
    request = 0,
    release = () => {},
    cursor,
    revision = 0
  const hidden = new Set(),
    live = new Map()
  const sort = (a, b) => (BigInt(a.id) < BigInt(b.id) ? -1 : BigInt(a.id) > BigInt(b.id) ? 1 : 0)
  function merge(values) {
    rows.value = [
      ...new Map(
        values.filter((v) => !hidden.has(String(v.id))).map((v) => [String(v.id), v]),
      ).values(),
    ].sort(sort)
  }
  function insert(payload) {
    if (String(payload.video_id) !== String(toValue(videoId))) return
    const comment = payload.comment
    if (!comment || !/^[1-9][0-9]*$/.test(String(comment.id)) || hidden.has(String(comment.id)))
      return
    live.set(String(comment.id), { comment, revision: ++revision })
    const exists = rows.value.some((row) => row.id === comment.id)
    merge([...rows.value, comment])
    if (!exists) change.value++
  }
  function remove(payload) {
    if (String(payload.video_id) !== String(toValue(videoId))) return
    hidden.add(String(payload.comment_id))
    live.delete(String(payload.comment_id))
    merge(rows.value)
  }
  async function refresh() {
    const id = toValue(videoId),
      g = generation,
      seq = ++request,
      start = revision
    if (!id) return
    loading.value = !rows.value.length
    error.value = ''
    try {
      const result = await listVideoComments(id, { per_page: 20 })
      if (g !== generation || seq !== request) return
      canModerate.value = !!result.permissions?.can_moderate
      const newer = [...live.values()]
        .filter((item) => item.revision > start)
        .map((item) => item.comment)
      const known = new Set(rows.value.map((row) => String(row.id)))
      merge([...result.data, ...newer])
      if (rows.value.some((row) => !known.has(String(row.id)))) change.value++
      cursor = result.data.at(-1)?.id
      hasOlder.value = result.data.length === 20
    } catch (failure) {
      if (g === generation && seq === request) error.value = domainError(failure)
    } finally {
      if (g === generation && seq === request) loading.value = false
    }
  }
  async function older() {
    if (olderBusy.value || !hasOlder.value || !cursor) return
    const id = toValue(videoId),
      g = generation,
      before = cursor
    olderBusy.value = true
    error.value = ''
    try {
      const result = await listVideoComments(id, { per_page: 20, before_id: before })
      if (g !== generation) return
      merge([...result.data, ...rows.value])
      cursor = result.data.at(-1)?.id
      hasOlder.value = result.data.length === 20
    } catch (failure) {
      if (g === generation) error.value = domainError(failure)
    } finally {
      if (g === generation) olderBusy.value = false
    }
  }
  async function send(body) {
    if (sending.value || !auth.isAuthenticated) return false
    const id = toValue(videoId),
      g = generation
    sending.value = true
    actionError.value = ''
    try {
      const comment = await createVideoComment(id, body.trim())
      if (g !== generation) return false
      insert({ video_id: id, comment })
      return true
    } catch (failure) {
      if (g === generation)
        actionError.value = failure.response?.data?.errors?.body?.[0] ? localizeValidationMessage(failure.response.data.errors.body[0], 'body') : domainError(failure)
      return false
    } finally {
      if (g === generation) sending.value = false
    }
  }
  async function hide(id) {
    if (managing.value) return
    const g = generation,
      video = toValue(videoId)
    managing.value = id
    actionError.value = ''
    try {
      await hideVideoComment(id)
      if (g === generation) remove({ video_id: video, comment_id: id })
    } catch (failure) {
      if (g === generation) actionError.value = domainError(failure)
    } finally {
      if (g === generation) managing.value = null
    }
  }
  const comments = computed(() =>
    rows.value.map((row) => ({
      ...row,
      can_manage:
        !!auth.isAuthenticated &&
        (canModerate.value || String(row.author?.id) === String(auth.user?.id) || !!row.can_manage),
    })),
  )
  const stop = watch(
    () => toValue(videoId),
    (id) => {
      generation++
      request++
      release()
      rows.value = []
      hidden.clear()
      live.clear()
      revision = 0
      cursor = undefined
      loading.value = sending.value = olderBusy.value = hasOlder.value = canModerate.value = false
      managing.value = null
      error.value = actionError.value = ''
      if (!id) return
      const g = generation
      release = subscribeRealtime(
        'video',
        id,
        {
          VideoCommentCreated: (payload) => {
            if (g === generation) insert(payload)
          },
          VideoCommentHidden: (payload) => {
            if (g === generation) remove(payload)
          },
        },
        () => {
          if (g === generation) refresh()
        },
      )
      refresh()
    },
    { immediate: true },
  )
  watch(
    () => auth.user?.id,
    () => {
      canModerate.value = false
      rows.value = rows.value.map((row) => ({ ...row, can_manage: false }))
      refresh()
    },
  )
  onScopeDispose(() => {
    generation++
    request++
    stop()
    release()
  })
  return {
    comments,
    loading,
    sending,
    error,
    actionError,
    olderBusy,
    hasOlder,
    managing,
    change,
    connection: realtimeConnection,
    refresh,
    older,
    send,
    hide,
  }
}
