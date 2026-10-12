import { onScopeDispose, ref, toValue, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import { realtimeConnection, subscribeRealtime } from './auctionEcho'
export function useUserRealtime(userSource) {
  const auth = userSource === undefined ? useAuthStore() : null
  const outbid = ref(null)
  let release = () => {}
  const stop = watch(
    () => (auth ? auth.user : toValue(userSource))?.id,
    (id) => {
      release()
      outbid.value = null
      if (!id) return
      release = subscribeRealtime('private', id, {
        AuctionOutbid: (payload) => {
          outbid.value = payload
        },
      })
    },
    { immediate: true },
  )
  onScopeDispose(() => {
    stop()
    release()
  })
  return { outbid, connection: realtimeConnection }
}
