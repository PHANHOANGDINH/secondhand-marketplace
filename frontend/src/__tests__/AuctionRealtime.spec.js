import { effectScope, nextTick, ref } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
const mock = vi.hoisted(() => ({ get: vi.fn(), subscriptions: [] }))
vi.mock('../api/auction.api', () => ({ getAuction: mock.get }))
vi.mock('../composables/auctionEcho', () => ({
  realtimeConnection: { value: 'connected' },
  subscribeRealtime: (kind, id, handlers, ready) => {
    const release = vi.fn()
    mock.subscriptions.push({ kind, id, handlers, ready, release })
    return release
  },
}))
import { useAuctionRealtime } from '../composables/useAuctionRealtime'
import { useUserRealtime } from '../composables/useUserRealtime'
import { createAuctionClock } from '../composables/auctionTimeSync'
const base = () => ({
  id: '7',
  status: 'LIVE',
  current_price: '100.00',
  bid_count: 0,
  updated_at: '2026-10-07T10:00:00.000000Z',
  server_time: '2026-10-07T10:00:00Z',
})
const bid = () => ({
  auction_id: '7',
  current_price: '110.00',
  minimum_next_bid: '120.00',
  bid_count: 1,
  latest_bid: { bidder_label: 'Buyer #2' },
  updated_at: '2026-10-07T10:00:01.000000Z',
  server_time: '2026-10-07T10:00:01Z',
})
it('an early bid retains newer price while late REST fills required product context', async () => {
  let resolve
  mock.get.mockImplementationOnce(
    () =>
      new Promise((done) => {
        resolve = done
      }),
  )
  const state = scope.run(() => useAuctionRealtime(7))
  mock.subscriptions[0].handlers.AuctionBidPlaced(bid())
  resolve({ ...base(), product: { slug: 'camera' }, ends_at: '2026-10-07T11:00:00Z' })
  await flushPromises()
  expect(state.auction.value.current_price).toBe('110.00')
  expect(state.auction.value.product.slug).toBe('camera')
  expect(state.auction.value.ends_at).toBe('2026-10-07T11:00:00Z')
})
let scope
beforeEach(() => {
  scope = effectScope()
  mock.subscriptions.length = 0
  mock.get.mockReset().mockResolvedValue(base())
})
afterEach(() => scope.stop())
it('loads REST state then applies safe realtime price/count/status snapshots', async () => {
  const state = scope.run(() => useAuctionRealtime(ref('7')))
  await flushPromises()
  mock.subscriptions[0].handlers.AuctionBidPlaced(bid())
  expect(state.auction.value.current_price).toBe('110.00')
  expect(state.auction.value.minimum_next_bid).toBe('120.00')
  expect(state.latestBid.value.bidder_label).toBe('Buyer #2')
  mock.subscriptions[0].handlers.AuctionStatusChanged({
    ...bid(),
    status: 'ENDED',
    updated_at: '2026-10-07T10:00:02.000000Z',
  })
  expect(state.auction.value.status).toBe('ENDED')
  expect(mock.get).toHaveBeenCalledOnce()
})
it('ignores foreign, duplicate, old and post-terminal bid events', async () => {
  const state = scope.run(() => useAuctionRealtime(7))
  await flushPromises()
  const handlers = mock.subscriptions[0].handlers
  handlers.AuctionBidPlaced({ ...bid(), auction_id: '8' })
  expect(state.auction.value.bid_count).toBe(0)
  handlers.AuctionBidPlaced(bid())
  handlers.AuctionBidPlaced({ ...bid(), current_price: '999.00' })
  expect(state.auction.value.current_price).toBe('110.00')
  handlers.AuctionBidPlaced({ ...bid(), bid_count: 2, updated_at: base().updated_at })
  expect(state.auction.value.bid_count).toBe(1)
  handlers.AuctionStatusChanged({ ...bid(), status: 'CANCELLED' })
  handlers.AuctionBidPlaced({ ...bid(), bid_count: 2, updated_at: '2026-10-07T11:00:00Z' })
  expect(state.auction.value.status).toBe('CANCELLED')
})
it('refreshes REST on reconnect without polling and preserves newer websocket state', async () => {
  const state = scope.run(() => useAuctionRealtime(7))
  await flushPromises()
  mock.subscriptions[0].handlers.AuctionBidPlaced(bid())
  await mock.subscriptions[0].ready()
  expect(mock.get).toHaveBeenCalledTimes(2)
  expect(state.auction.value.current_price).toBe('110.00')
  mock.get.mockResolvedValue({
    ...base(),
    current_price: '130.00',
    bid_count: 3,
    updated_at: '2026-10-07T10:00:03Z',
  })
  await mock.subscriptions[0].ready()
  expect(state.auction.value.current_price).toBe('130.00')
})
it('releases old route subscriptions and discards in-flight responses', async () => {
  let resolve
  mock.get.mockImplementationOnce(
    () =>
      new Promise((done) => {
        resolve = done
      }),
  )
  const id = ref('7'),
    state = scope.run(() => useAuctionRealtime(id))
  const first = mock.subscriptions[0]
  id.value = '8'
  mock.get.mockResolvedValue({ ...base(), id: '8' })
  await nextTick()
  await flushPromises()
  expect(first.release).toHaveBeenCalledOnce()
  resolve(base())
  await flushPromises()
  expect(state.auction.value.id).toBe('8')
  scope.stop()
  expect(mock.subscriptions[1].release).toHaveBeenCalledOnce()
})
it('does not accept a pending REST response after unmount', async () => {
  let resolve
  mock.get.mockImplementationOnce(
    () =>
      new Promise((done) => {
        resolve = done
      }),
  )
  const state = scope.run(() => useAuctionRealtime(7))
  scope.stop()
  resolve(base())
  await flushPromises()
  expect(state.auction.value).toBe(null)
})
it('shows unavailable state for revoked public visibility and clears prior data', async () => {
  const state = scope.run(() => useAuctionRealtime(7))
  await flushPromises()
  mock.get.mockRejectedValue({ response: { status: 404, data: { message: 'Unavailable' } } })
  await state.refresh()
  expect(state.auction.value).toBe(null)
  expect(state.error.value).toBeTruthy()
  expect(state.loading.value).toBe(false)
})
it('invalid auction IDs do not request or subscribe', () => {
  scope.run(() => useAuctionRealtime('0'))
  expect(mock.get).not.toHaveBeenCalled()
  expect(mock.subscriptions).toHaveLength(0)
})
it('private outbid subscription follows the signed-in user and cleans up on logout', async () => {
  const user = ref({ id: '9' }),
    state = scope.run(() => useUserRealtime(user))
  const first = mock.subscriptions[0]
  expect(first.kind).toBe('private')
  first.handlers.AuctionOutbid({ auction_id: '7', new_current_price: '110.00' })
  expect(state.outbid.value.new_current_price).toBe('110.00')
  user.value = null
  await nextTick()
  expect(first.release).toHaveBeenCalledOnce()
  expect(state.outbid.value).toBe(null)
  user.value = { id: '10' }
  await nextTick()
  expect(mock.subscriptions[1].id).toBe('10')
  scope.stop()
  expect(mock.subscriptions[1].release).toHaveBeenCalledOnce()
})
it('server clock corrects client skew and ignores subsequent wall-clock jumps', () => {
  let wall = 0,
    monotonic = 10
  const clock = createAuctionClock({ clientNow: () => wall, monotonicNow: () => monotonic })
  expect(clock.sync('2026-10-07T10:00:00Z')).toBe(true)
  expect(clock.offsetMs).toBe(Date.parse('2026-10-07T10:00:00Z'))
  wall = 99999999999999
  monotonic += 3000
  expect(clock.remainingMs('2026-10-07T10:00:05Z')).toBe(2000)
  expect(clock.sync('invalid')).toBe(false)
  expect(clock.remainingMs('invalid')).toBe(0)
  monotonic += 4000
  expect(clock.remainingMs('2026-10-07T10:00:05Z')).toBe(0)
})
