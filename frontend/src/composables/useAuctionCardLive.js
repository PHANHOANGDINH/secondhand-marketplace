import { computed, onScopeDispose, ref, toValue, watch } from 'vue'
import { getAuction } from '../api/auction.api'
import { subscribeRealtime } from './auctionEcho'

const open = (status) => ['LIVE', 'SCHEDULED'].includes(status)
const pick = ({ status, starts_at, ends_at, current_price, bid_count, updated_at }) =>
  Object.fromEntries(
    Object.entries({ status, starts_at, ends_at, current_price, bid_count, updated_at }).filter(
      ([, value]) => value !== undefined,
    ),
  )

// Keeps a list card's price, bid count and status current through the public auction channel.
// The list response stays the base: a newer list row wins over an older realtime patch.
export function useAuctionCardLive(source) {
  const patch = ref(null),
    bumped = ref(false)
  let release = () => {},
    generation = 0,
    bumpTimer
  const auction = computed(() => {
    const base = toValue(source)
    if (!patch.value) return base
    if (base?.updated_at && patch.value.updated_at && base.updated_at > patch.value.updated_at)
      return base
    return { ...base, ...patch.value }
  })
  function bump() {
    bumped.value = true
    clearTimeout(bumpTimer)
    bumpTimer = setTimeout(() => (bumped.value = false), 1200)
  }
  function apply(payload, bid = false) {
    const current = auction.value
    if (!current || String(payload?.auction_id || payload?.id) !== String(current.id)) return
    if (current.updated_at && payload.updated_at && payload.updated_at < current.updated_at) return
    if (bid && !open(current.status)) return
    if (bid && Number(payload.bid_count) <= Number(current.bid_count || 0)) return
    const next = { ...patch.value, ...pick(payload), ...(bid ? { status: 'LIVE' } : {}) }
    if (next.current_price !== undefined && next.current_price !== current.current_price) bump()
    patch.value = next
    if (!open(next.status ?? current.status)) {
      release()
      release = () => {}
    }
  }
  const stop = watch(
    () => toValue(source)?.id,
    (id) => {
      const g = ++generation
      release()
      release = () => {}
      patch.value = null
      if (!/^[1-9][0-9]*$/.test(String(id)) || !open(toValue(source)?.status)) return
      let first = true
      release = subscribeRealtime(
        'public',
        id,
        {
          AuctionBidPlaced: (payload) => apply(payload, true),
          AuctionStatusChanged: (payload) => apply(payload),
        },
        async () => {
          // The list row is fresh on the first subscription; later ones follow a reconnect.
          if (first) return void (first = false)
          try {
            const value = await getAuction(id)
            if (g === generation) apply(value)
          } catch {
            /* the list row stays as it was */
          }
        },
      )
    },
    { immediate: true },
  )
  onScopeDispose(() => {
    generation++
    stop()
    release()
    clearTimeout(bumpTimer)
  })
  return { auction, bumped }
}
