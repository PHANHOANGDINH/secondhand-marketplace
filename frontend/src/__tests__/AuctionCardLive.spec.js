import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import AuctionCard from '../components/auction/AuctionCard.vue'
import * as auctionApi from '../api/auction.api'
import * as productApi from '../api/product.api'

const mock = vi.hoisted(() => ({ subscriptions: [] }))
vi.mock('../composables/auctionEcho', () => ({
  subscribeRealtime: (kind, id, handlers, ready) => {
    const release = vi.fn()
    mock.subscriptions.push({ kind, id, handlers, ready, release })
    return release
  },
}))
const auction = (patch = {}) => ({
  id: '7',
  status: 'LIVE',
  product: { id: '3', name: 'Máy ảnh', slug: 'may-anh' },
  current_price: '100000.00',
  bid_count: 1,
  starts_at: '2026-10-30T01:00:00.000000Z',
  ends_at: '2026-10-30T09:00:00.000000Z',
  updated_at: '2026-10-30T02:00:00.000000Z',
  ...patch,
})
const bid = (patch = {}) => ({
  auction_id: '7',
  current_price: '150000.00',
  minimum_next_bid: '160000.00',
  bid_count: 2,
  latest_bid: { id: '20', bidder_label: 'Người mua #4', amount: '150000.00' },
  updated_at: '2026-10-30T02:05:00.000000Z',
  server_time: '2026-10-30T02:05:00.000000Z',
  ...patch,
})
const wrappers = []
function render(props = auction()) {
  const wrapper = mount(AuctionCard, {
    props: { auction: props },
    global: { stubs: { RouterLink: RouterLinkStub } },
  })
  wrappers.push(wrapper)
  return wrapper
}
const price = (wrapper) => wrapper.get('.auction-card-price').text().replace(/\s/g, '')
beforeEach(() => {
  mock.subscriptions.length = 0
  vi.spyOn(productApi, 'getProduct').mockResolvedValue({ primary_image: null, shop: null })
  vi.spyOn(auctionApi, 'getAuction').mockResolvedValue(auction())
})
afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  vi.restoreAllMocks()
})

it('subscribes open auctions to their public channel and skips finished ones', () => {
  render()
  render(auction({ id: '8', status: 'SCHEDULED' }))
  render(auction({ id: '9', status: 'ENDED' }))
  expect(mock.subscriptions.map(({ kind, id }) => [kind, id])).toEqual([
    ['public', '7'],
    ['public', '8'],
  ])
})
it('shows a pushed bid price and count, and ignores stale or out-of-order bids', async () => {
  const wrapper = render()
  const { handlers } = mock.subscriptions[0]
  handlers.AuctionBidPlaced(bid())
  await flushPromises()
  expect(price(wrapper)).toContain('150.000')
  expect(wrapper.text()).toContain('2 lượt ra giá')
  expect(wrapper.get('.auction-card-price').classes()).toContain('is-bumped')
  handlers.AuctionBidPlaced(bid({ current_price: '120000.00', bid_count: 2 }))
  handlers.AuctionBidPlaced(bid({ auction_id: '99', current_price: '999000.00', bid_count: 9 }))
  handlers.AuctionBidPlaced(
    bid({ current_price: '90000.00', bid_count: 5, updated_at: '2026-10-30T01:00:00.000000Z' }),
  )
  await flushPromises()
  expect(price(wrapper)).toContain('150.000')
  expect(wrapper.text()).toContain('2 lượt ra giá')
})
it('applies a status change and releases the channel once the auction ends', async () => {
  const wrapper = render()
  const { handlers, release } = mock.subscriptions[0]
  handlers.AuctionStatusChanged({
    auction_id: '7',
    status: 'ENDED',
    starts_at: '2026-10-30T01:00:00.000000Z',
    ends_at: '2026-10-30T02:10:00.000000Z',
    current_price: '100000.00',
    updated_at: '2026-10-30T02:10:00.000000Z',
  })
  await flushPromises()
  expect(wrapper.text()).toContain('Đã kết thúc')
  expect(release).toHaveBeenCalled()
  handlers.AuctionBidPlaced(bid({ bid_count: 3, updated_at: '2026-10-30T02:11:00.000000Z' }))
  await flushPromises()
  expect(price(wrapper)).toContain('100.000')
})
it('refetches only after a reconnect, not on the first subscription', async () => {
  const wrapper = render()
  const { ready } = mock.subscriptions[0]
  await ready()
  expect(auctionApi.getAuction).not.toHaveBeenCalled()
  auctionApi.getAuction.mockResolvedValue(
    auction({
      current_price: '180000.00',
      bid_count: 4,
      updated_at: '2026-10-30T02:20:00.000000Z',
    }),
  )
  await ready()
  await flushPromises()
  expect(auctionApi.getAuction).toHaveBeenCalledWith('7')
  expect(price(wrapper)).toContain('180.000')
  expect(wrapper.text()).toContain('4 lượt ra giá')
})
it('a newer list row wins over an older realtime patch and unmount releases the channel', async () => {
  const wrapper = render()
  const { handlers, release } = mock.subscriptions[0]
  handlers.AuctionBidPlaced(bid())
  await wrapper.setProps({
    auction: auction({
      current_price: '200000.00',
      bid_count: 3,
      updated_at: '2026-10-30T02:30:00.000000Z',
    }),
  })
  expect(price(wrapper)).toContain('200.000')
  wrapper.unmount()
  wrappers.splice(0)
  expect(release).toHaveBeenCalled()
})
