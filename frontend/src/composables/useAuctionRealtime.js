import { onScopeDispose, ref, toValue, watch } from 'vue'
import { getAuction } from '../api/auction.api'
import { domainError } from '../api/domain.api'
import { realtimeConnection, subscribeRealtime } from './auctionEcho'
import { createAuctionClock } from './auctionTimeSync'

export function useAuctionRealtime(auctionId) {
  const auction = ref(null),
    latestBid = ref(null),
    error = ref(''),
    loading = ref(false),
    snapshotVersion = ref(0)
  const clock = createAuctionClock()
  let generation = 0,
    request = 0,
    release = () => {}
  function apply(payload, bid = false) {
    if (String(payload.auction_id || payload.id) !== String(toValue(auctionId))) return
    const current = auction.value
    if (current?.updated_at && payload.updated_at && payload.updated_at < current.updated_at) {
      // A bid can arrive before the initial REST response. Retain its newer state,
      // while filling immutable product/timing context needed by the Arena.
      if (!bid && payload.id) auction.value = { ...payload, ...current }
      return
    }
    if (bid && ['ENDED', 'CANCELLED'].includes(current?.status)) return
    if (bid && Number(payload.bid_count) <= Number(current?.bid_count || 0)) return
    clock.sync(payload.server_time)
    auction.value = {
      ...current,
      ...payload,
      id: String(payload.auction_id || payload.id),
      ...(bid ? { status: 'LIVE' } : {}),
    }
    if (bid) latestBid.value = payload.latest_bid
  }
  async function refresh() {
    const id = toValue(auctionId),
      g = generation,
      sequence = ++request
    if (!/^[1-9][0-9]*$/.test(String(id))) return
    loading.value = true
    try {
      const value = await getAuction(id)
      if (g !== generation || sequence !== request) return
      apply(value)
      snapshotVersion.value++
      error.value = ''
    } catch (failure) {
      if (g !== generation || sequence !== request) return
      error.value = domainError(failure)
      if ([403, 404].includes(failure.response?.status)) {
        auction.value = null
        latestBid.value = null
      }
    } finally {
      if (g === generation && sequence === request) loading.value = false
    }
  }
  const stop = watch(
    () => toValue(auctionId),
    (id) => {
      generation++
      request++
      release()
      auction.value = latestBid.value = null
      error.value = ''
      loading.value = false
      if (!/^[1-9][0-9]*$/.test(String(id))) return
      release = subscribeRealtime(
        'public',
        id,
        {
          AuctionBidPlaced: (payload) => apply(payload, true),
          AuctionStatusChanged: (payload) => apply(payload),
        },
        refresh,
      )
      refresh()
    },
    { immediate: true },
  )
  onScopeDispose(() => {
    generation++
    request++
    stop()
    release()
  })
  return {
    auction,
    latestBid,
    error,
    loading,
    snapshotVersion,
    connection: realtimeConnection,
    clock,
    refresh,
  }
}
