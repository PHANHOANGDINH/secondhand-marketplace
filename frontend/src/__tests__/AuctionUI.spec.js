import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { effectScope, ref } from 'vue'
import { beforeEach, afterEach, it, expect, vi } from 'vitest'
import { useAuthStore } from '../stores/auth'
import * as api from '../api/auction.api'
import * as products from '../api/product.api'
import * as shops from '../api/shop.api'
import { quickBid, parseBidInput } from '../composables/auctionPresentation'
import { useAuctionCountdown } from '../composables/useAuctionCountdown'
import Arena from '../views/AuctionArenaView.vue'
import Discovery from '../views/AuctionsView.vue'
import Seller from '../views/SellerAuctionsView.vue'
import Toasts from '../components/auction/OutbidToasts.vue'
const mock = vi.hoisted(() => ({ subscriptions: [] }))
vi.mock('../composables/auctionEcho', () => ({
  realtimeConnection: { value: 'connected' },
  subscribeRealtime: (kind, id, handlers, ready) => {
    const release = vi.fn()
    mock.subscriptions.push({ kind, id, handlers, ready, release })
    return release
  },
}))
const time = '2026-10-08T00:00:00.000000Z'
const auction = () => ({
  id: '7',
  seller_id: '99',
  product_id: '3',
  product: { id: '3', name: 'Máy ảnh', slug: 'camera' },
  status: 'LIVE',
  start_price: '100000.00',
  current_price: '100000.00',
  minimum_next_bid: '100000.00',
  bid_increment: '10000.00',
  bid_count: 0,
  starts_at: '2026-10-07T23:00:00Z',
  ends_at: '2026-10-08T01:00:00Z',
  server_time: time,
  updated_at: time,
})
const product = () => ({
  id: '3',
  slug: 'camera',
  name: 'Máy ảnh',
  condition: 'GOOD',
  description: '<script>plain text</script>',
  stock_quantity: 1,
  status: 'ACTIVE',
  images: [],
  shop: { name: 'Gian hàng', slug: 'shop' },
})
const page = (data) => ({ data, meta: { current_page: 1, last_page: 1 } })
const wrappers = []
beforeEach(() => {
  vi.restoreAllMocks()
  vi.setSystemTime(new Date(time))
  mock.subscriptions.length = 0
  vi.spyOn(api, 'getAuction').mockResolvedValue(auction())
  vi.spyOn(api, 'getSellerAuction').mockResolvedValue(auction())
  vi.spyOn(api, 'listAuctionBids').mockResolvedValue(page([]))
  vi.spyOn(api, 'placeAuctionBid').mockResolvedValue({ id: '1' })
  vi.spyOn(api, 'listAuctions').mockResolvedValue(page([auction()]))
  vi.spyOn(api, 'listSellerAuctions').mockImplementation(async (params) =>
    page(params?.status ? [] : [auction()]),
  )
  vi.spyOn(api, 'createAuction').mockResolvedValue(auction())
  vi.spyOn(api, 'cancelAuction').mockResolvedValue({ ...auction(), status: 'CANCELLED' })
  vi.spyOn(products, 'getProduct').mockResolvedValue(product())
  vi.spyOn(products, 'getSellerProduct').mockResolvedValue(product())
  vi.spyOn(products, 'listSellerProducts').mockResolvedValue(page([product()]))
  vi.spyOn(products, 'listProducts').mockResolvedValue(page([product()]))
  vi.spyOn(shops, 'getSellerShop').mockResolvedValue({ id: '2', status: 'ACTIVE', name: 'Shop' })
})
afterEach(() => {
  wrappers.splice(0).forEach((w) => w.unmount())
  vi.useRealTimers()
})
async function setup(
  component = Arena,
  { user = { id: '1', role: 'USER' }, props = {}, path = '/auctions/7' } = {},
) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = user
  auth.initialized = true
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', name: 'login', component: { template: '<div />' } },
      { path: '/auctions/:id', component: { template: '<div />' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } },
    ],
  })
  await router.push(path)
  const wrapper = mount(component, {
    props,
    attachTo: document.body,
    global: { plugins: [pinia, router] },
  })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, auth, router }
}
it('uses exact decimal/BigInt amounts including grouped Vietnamese currency', () => {
  expect(parseBidInput('1.500.000,25')).toBe('1500000.25')
  expect(parseBidInput('150000.00')).toBe('150000.00')
  expect(parseBidInput('-1')).toBe(null)
  expect(parseBidInput('1e6')).toBe(null)
  expect(quickBid('999999999999.99', 50000)).toBe('1000000049999.99')
})
it('renders product context as plain text and never animates the initial price', async () => {
  const { wrapper } = await setup()
  expect(wrapper.text()).toContain('<script>plain text</script>')
  expect(wrapper.find('script').exists()).toBe(false)
  expect(wrapper.get('.auction-price').classes()).not.toContain('is-changed')
})
it('quick chips calculate from the latest realtime minimum', async () => {
  const { wrapper } = await setup()
  const sub = mock.subscriptions.find((s) => s.kind === 'public')
  sub.handlers.AuctionBidPlaced({
    auction_id: '7',
    current_price: '200000.00',
    minimum_next_bid: '210000.00',
    bid_count: 1,
    latest_bid: { id: '1', amount: '200000.00', bidder_label: 'Người mua #2', created_at: time },
    updated_at: '2026-10-08T00:00:01.000000Z',
    server_time: time,
  })
  await flushPromises()
  await wrapper.findAll('.auction-chips button')[0].trigger('click')
  expect(wrapper.get('#auction-bid-amount').element.value).toBe('260000.00')
  expect(wrapper.get('.auction-price').classes()).toContain('is-changed')
  expect(wrapper.get('.auction-bid-log').text()).toContain('Người mua #2')
  expect(wrapper.findAll('.auction-bid-log li')).toHaveLength(1)
})
it('submits a canonical amount and prevents duplicate submissions', async () => {
  let resolve
  api.placeAuctionBid.mockImplementationOnce(
    () =>
      new Promise((done) => {
        resolve = done
      }),
  )
  const { wrapper } = await setup()
  await wrapper.get('#auction-bid-amount').setValue('150.000')
  await wrapper.get('form.auction-bid-form').trigger('submit')
  await wrapper.get('form.auction-bid-form').trigger('submit')
  expect(api.placeAuctionBid).toHaveBeenCalledTimes(1)
  expect(api.placeAuctionBid).toHaveBeenCalledWith('7', '150000')
  expect(wrapper.get('.auction-bid-button').attributes('disabled')).toBeDefined()
  resolve({ id: '1' })
  await flushPromises()
  expect(wrapper.text()).toContain('Đã ghi nhận giá của bạn.')
})
it('shows backend rejection, refreshes truth and focuses the invalid amount', async () => {
  api.placeAuctionBid.mockRejectedValueOnce({
    response: { status: 422, data: { errors: { amount: ['Giá tối thiểu đã thay đổi.'] } } },
  })
  const { wrapper } = await setup()
  await wrapper.get('#auction-bid-amount').setValue('1')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.text()).toContain('Giá tối thiểu đã thay đổi.')
  expect(document.activeElement.id).toBe('auction-bid-amount')
  expect(api.getAuction).toHaveBeenCalledTimes(2)
})
it('rejects malformed amount locally with focus and no write', async () => {
  const { wrapper } = await setup()
  await wrapper.get('#auction-bid-amount').setValue('abc')
  await wrapper.get('form').trigger('submit')
  expect(api.placeAuctionBid).not.toHaveBeenCalled()
  expect(wrapper.get('#auction-bid-amount').attributes('aria-invalid')).toBe('true')
})
it('guest bid preserves a safe intended-return login destination', async () => {
  const { wrapper, router } = await setup(Arena, { user: null })
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(router.currentRoute.value.name).toBe('login')
  expect(router.currentRoute.value.query.redirect).toBe('/auctions/7')
  expect(api.placeAuctionBid).not.toHaveBeenCalled()
})
it('own auction has no enabled bid action', async () => {
  const { wrapper } = await setup(Arena, { user: { id: '99', role: 'USER' } })
  expect(wrapper.find('.auction-bid-form').exists()).toBe(false)
  expect(wrapper.text()).toContain('Người bán không thể ra giá')
})
it.each(['SCHEDULED', 'ENDED', 'CANCELLED'])('renders %s with no bid form', async (status) => {
  api.getAuction.mockResolvedValue({ ...auction(), status })
  const { wrapper } = await setup()
  expect(wrapper.find('.auction-bid-form').exists()).toBe(false)
  expect(wrapper.text()).not.toContain('Thanh toán')
})
it('terminal realtime status removes bidding and stops countdown', async () => {
  const { wrapper } = await setup()
  mock.subscriptions[0].handlers.AuctionStatusChanged({
    ...auction(),
    auction_id: '7',
    status: 'ENDED',
    updated_at: '2026-10-08T00:01:00Z',
  })
  await flushPromises()
  expect(wrapper.find('.auction-bid-form').exists()).toBe(false)
  expect(wrapper.get('[role=timer]').text()).toBe('Đã kết thúc')
})
it('reconnect REST refresh also reloads bid history', async () => {
  const { wrapper } = await setup()
  api.getAuction.mockResolvedValue({
    ...auction(),
    current_price: '200000.00',
    bid_count: 1,
    updated_at: '2026-10-08T00:01:00Z',
  })
  api.listAuctionBids.mockResolvedValue(
    page([{ id: '1', user_id: '2', amount: '200000.00', created_at: time }]),
  )
  await mock.subscriptions[0].ready()
  await flushPromises()
  expect(wrapper.get('.auction-bid-log').text()).toContain('Người mua #2')
  expect(wrapper.get('.auction-price').text()).toContain('200.000')
})
it('route ID changes clear old state, subscriptions and pending writes', async () => {
  const { router } = await setup()
  const first = mock.subscriptions[0]
  api.getAuction.mockResolvedValue({ ...auction(), id: '8' })
  await router.push('/auctions/8')
  await flushPromises()
  expect(first.release).toHaveBeenCalledOnce()
  expect(mock.subscriptions.at(-1).id).toBe('8')
})
it('loads older history pages without duplicates', async () => {
  api.listAuctionBids.mockResolvedValue({
    data: [{ id: '2', user_id: '2', amount: '110000', created_at: time }],
    meta: { current_page: 1, last_page: 2 },
  })
  const { wrapper } = await setup()
  api.listAuctionBids.mockResolvedValue({
    data: [
      { id: '2', user_id: '2', amount: '110000', created_at: time },
      { id: '1', user_id: '1', amount: '100000', created_at: time },
    ],
    meta: { current_page: 2, last_page: 2 },
  })
  await wrapper.get('.auction-history button').trigger('click')
  await flushPromises()
  expect(wrapper.findAll('.auction-bid-log li')).toHaveLength(2)
})
it('countdown uses server time, warning text, terminal timer cleanup', () => {
  vi.useFakeTimers()
  const scope = effectScope(),
    a = ref({ ...auction(), ends_at: '2026-10-08T00:00:09Z' })
  const countdown = scope.run(() => useAuctionCountdown(a))
  expect(countdown.text.value).toBe('00:00:09')
  expect(countdown.urgent.value).toBe(true)
  expect(countdown.finalSeconds.value).toBe(true)
  scope.stop()
  expect(vi.getTimerCount()).toBe(0)
})
it('scheduled countdown counts to start; expired display never invents ENDED', () => {
  const scope = effectScope(),
    a = ref({ ...auction(), status: 'SCHEDULED', starts_at: '2026-10-08T00:00:05Z' })
  const countdown = scope.run(() => useAuctionCountdown(a))
  expect(countdown.text.value).toBe('00:00:05')
  scope.stop()
  const ended = effectScope()
  const display = ended.run(() =>
    useAuctionCountdown({ ...auction(), ends_at: '2026-10-07T22:00:00Z' }),
  )
  expect(display.remaining.value).toBe(0)
  expect(display.text.value).toBe('Chờ cập nhật từ máy chủ')
  ended.stop()
})
it('toasts safely render outbid product, deduplicate, and navigate to Arena', async () => {
  const { wrapper, router } = await setup(Toasts)
  const sub = mock.subscriptions[0]
  const notice = {
    auction_id: '7',
    new_current_price: '110000.00',
    product: { name: '<img src=x>' },
  }
  sub.handlers.AuctionOutbid(notice)
  await flushPromises()
  sub.handlers.AuctionOutbid({ ...notice })
  await flushPromises()
  expect(wrapper.findAll('.auction-toast')).toHaveLength(1)
  expect(wrapper.find('img').exists()).toBe(false)
  await wrapper.findAll('button')[0].trigger('click')
  expect(router.currentRoute.value.path).toBe('/auctions/7')
  expect(wrapper.find('.auction-toast').exists()).toBe(false)
})
it('outbid toasts pause on interaction, support Escape, and clear on logout', async () => {
  vi.useFakeTimers()
  const { wrapper, auth } = await setup(Toasts)
  mock.subscriptions[0].handlers.AuctionOutbid({
    auction_id: '7',
    new_current_price: '1',
    product: { name: 'Camera' },
  })
  await flushPromises()
  await wrapper.get('.auction-toast').trigger('pointerenter')
  vi.advanceTimersByTime(13000)
  expect(wrapper.find('.auction-toast').exists()).toBe(true)
  await wrapper.get('.auction-toast').trigger('keydown', { key: 'Escape' })
  expect(wrapper.find('.auction-toast').exists()).toBe(false)
  mock.subscriptions[0].handlers.AuctionOutbid({
    auction_id: '7',
    new_current_price: '2',
    product: { name: 'Camera' },
  })
  await flushPromises()
  auth.user = null
  await flushPromises()
  expect(wrapper.find('.auction-toast').exists()).toBe(false)
})
it('toasts auto-dismiss and do not appear without a private outbid event', async () => {
  vi.useFakeTimers()
  const { wrapper } = await setup(Toasts)
  expect(wrapper.find('.auction-toast').exists()).toBe(false)
  mock.subscriptions[0].handlers.AuctionOutbid({
    auction_id: '7',
    new_current_price: '1',
    product: { name: 'Camera' },
  })
  await flushPromises()
  vi.advanceTimersByTime(12000)
  await flushPromises()
  expect(wrapper.find('.auction-toast').exists()).toBe(false)
})
it('discovery filters real API data with no fabricated count', async () => {
  const { wrapper } = await setup(Discovery, { path: '/auctions' })
  expect(wrapper.findAll('.auction-card')).toHaveLength(1)
  await wrapper.get('select').setValue('SCHEDULED')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(api.listAuctions).toHaveBeenLastCalledWith({ page: 1, per_page: 12, status: 'SCHEDULED' })
})
it('seller only offers eligible products and supported creation fields', async () => {
  products.listSellerProducts.mockResolvedValue(
    page([
      product(),
      { ...product(), id: '4', status: 'DRAFT' },
      { ...product(), id: '5', stock_quantity: 0 },
    ]),
  )
  const { wrapper } = await setup(Seller)
  await wrapper.findAll('button')[0].trigger('click')
  expect(wrapper.findAll('#create-auction-product_id option')).toHaveLength(2)
  for (const [key, value] of Object.entries({
    product_id: '3',
    start_price: '100.000',
    bid_increment: '10.000',
    starts_at: '2026-10-08T09:00',
    ends_at: '2026-10-08T10:00',
  }))
    await wrapper.get('#create-auction-' + key).setValue(value)
  await wrapper.get('form[aria-label="Tạo phiên đấu giá"]').trigger('submit')
  await flushPromises()
  expect(Object.keys(api.createAuction.mock.calls[0][0]).sort()).toEqual([
    'bid_increment',
    'ends_at',
    'product_id',
    'start_price',
    'starts_at',
  ])
  expect(api.createAuction.mock.calls[0][0].start_price).toBe('100000')
})
it('seller cancellation requires confirmation and is hidden once bids exist', async () => {
  const { wrapper } = await setup(Seller)
  await wrapper.get('.btn-outline-danger').trigger('click')
  expect(api.cancelAuction).not.toHaveBeenCalled()
  await wrapper.get('.btn-danger').trigger('click')
  await flushPromises()
  expect(api.cancelAuction).toHaveBeenCalledWith('7')
})
it('seller excludes products already in unfinished auctions', async () => {
  api.listSellerAuctions.mockResolvedValue(page([auction()]))
  const { wrapper } = await setup(Seller)
  await wrapper.findAll('button')[0].trigger('click')
  expect(wrapper.findAll('#create-auction-product_id option')).toHaveLength(1)
  expect(
    wrapper.get('form[aria-label="Tạo phiên đấu giá"] button.btn-primary').attributes('disabled'),
  ).toBeDefined()
})
it('seller hides cancellation after bidding and exposes field validation', async () => {
  api.listSellerAuctions.mockImplementation(async (params) =>
    page(params?.status ? [] : [{ ...auction(), bid_count: 1 }]),
  )
  api.createAuction.mockRejectedValueOnce({
    response: { status: 422, data: { errors: { ends_at: ['Kết thúc phải sau bắt đầu.'] } } },
  })
  const { wrapper } = await setup(Seller)
  expect(wrapper.find('.btn-outline-danger').exists()).toBe(false)
  await wrapper.findAll('button')[0].trigger('click')
  await wrapper.get('form[aria-label="Tạo phiên đấu giá"]').trigger('submit')
  await flushPromises()
  expect(wrapper.text()).toContain('Kết thúc phải sau bắt đầu.')
  expect(document.activeElement.id).toBe('create-auction-ends_at')
})
it('owner-only auction endpoint rejection does not render another sellers data', async () => {
  api.getSellerAuction.mockRejectedValue({ response: { status: 403 } })
  const { wrapper } = await setup(Arena, { props: { seller: true } })
  expect(wrapper.find('.auction-controls').exists()).toBe(false)
  expect(products.getSellerProduct).not.toHaveBeenCalled()
  expect(wrapper.find('[role=alert]').exists()).toBe(true)
})
it('ENDED fetches the server-confirmed winner without inventing a no-bid outcome', async () => {
  const { wrapper } = await setup()
  api.getAuction.mockResolvedValue({
    ...auction(),
    status: 'ENDED',
    bid_count: 1,
    winner_user_id: '2',
    updated_at: '2026-10-08T01:00:00Z',
  })
  mock.subscriptions[0].handlers.AuctionStatusChanged({
    ...auction(),
    auction_id: '7',
    status: 'ENDED',
    bid_count: 1,
    updated_at: '2026-10-08T01:00:00Z',
  })
  await flushPromises()
  expect(wrapper.text()).toContain('Người thắng: Người mua #2')
  expect(wrapper.text()).not.toContain('mà không có lượt ra giá')
})
it('expired display disables bidding while retaining server LIVE state', async () => {
  api.getAuction.mockResolvedValue({ ...auction(), ends_at: '2026-10-07T22:00:00Z' })
  const { wrapper } = await setup()
  expect(wrapper.get('.auction-bid-button').attributes('disabled')).toBeDefined()
  expect(wrapper.text()).toContain('Đang đấu giá')
  expect(wrapper.text()).toContain('chờ trạng thái máy chủ')
  expect(api.placeAuctionBid).not.toHaveBeenCalled()
})
it('notification bursts stay bounded and reject malformed destinations', async () => {
  const { wrapper } = await setup(Toasts)
  const handler = mock.subscriptions[0].handlers.AuctionOutbid
  handler({ auction_id: '../admin', new_current_price: '1', product: { name: 'Camera' } })
  await flushPromises()
  expect(wrapper.find('.auction-toast').exists()).toBe(false)
  for (let amount = 1; amount <= 5; amount++) {
    handler({ auction_id: '7', new_current_price: String(amount), product: { name: 'Camera' } })
    await flushPromises()
  }
  expect(wrapper.findAll('.auction-toast')).toHaveLength(3)
})

it('only the ended winner sees the checkout CTA, never the seller or loser', async () => {
  const ended = {
    ...auction(),
    status: 'ENDED',
    winner_user_id: '1',
    settlement_state: 'AWAITING_CHECKOUT',
    winner_checkout_expires_at: '2026-10-09T00:00:00Z',
  }
  api.getAuction.mockResolvedValue(ended)
  api.getSellerAuction.mockResolvedValue(ended)
  const winner = await setup()
  expect(winner.wrapper.find('[data-auction-winner] a').attributes('href')).toBe(
    '/auctions/7/checkout',
  )
  const loser = await setup(Arena, { user: { id: '2', role: 'USER' } })
  expect(loser.wrapper.find('[data-auction-winner]').exists()).toBe(false)
  const seller = await setup(Arena, {
    props: { seller: true },
    user: { id: '99', role: 'USER' },
    path: '/seller/auctions/7',
  })
  expect(seller.wrapper.find('[data-auction-winner]').exists()).toBe(false)
})
it('expired winner sees expiry without checkout CTA', async () => {
  api.getAuction.mockResolvedValue({
    ...auction(),
    status: 'ENDED',
    winner_user_id: '1',
    settlement_state: 'CHECKOUT_EXPIRED',
  })
  const { wrapper } = await setup()
  expect(wrapper.find('[data-auction-winner]').text()).toContain('hết hạn')
  expect(wrapper.find('[data-auction-winner] a').exists()).toBe(false)
})
