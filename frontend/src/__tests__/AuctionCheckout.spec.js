import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { beforeEach, afterEach, it, expect, vi } from 'vitest'
import { useAuthStore } from '../stores/auth'
import * as api from '../api/auction.api'
import * as payments from '../api/payment.api'
import Checkout from '../views/AuctionCheckoutView.vue'
import Source from '../components/auction/AuctionOrderSource.vue'
const fixture = () => ({
  auction: {
    id: '7',
    product: { name: 'Máy ảnh' },
    status: 'ENDED',
    settlement_state: 'AWAITING_CHECKOUT',
    server_time: '2026-10-08T00:00:00Z',
  },
  winning_price: '150000.25',
  product: { name: 'Máy ảnh', images: [] },
  checkout_deadline: '2026-10-09T00:00:00Z',
  remaining_seconds: 86400,
  addresses: [
    {
      id: '9',
      is_default: true,
      recipient_name: 'Buyer',
      address_line: '10 Nguyễn Trãi',
      province: 'HCM',
    },
  ],
  payment_methods: ['COD', 'ZALOPAY'],
  product_available: true,
  winner_order: null,
})
const order = (method = 'COD') => ({
  id: '42',
  order_code: 'SHM-demo',
  payment_method: method,
  payments: [
    {
      status: 'PENDING',
      order_url: method === 'ZALOPAY' ? 'https://sb-openapi.zalopay.vn/test' : null,
    },
  ],
})
const wrappers = []
beforeEach(() => {
  vi.restoreAllMocks()
  sessionStorage.clear()
  vi.spyOn(api, 'getAuctionCheckout').mockResolvedValue(fixture())
  vi.spyOn(api, 'checkoutAuction').mockResolvedValue(order())
  vi.spyOn(payments, 'redirectToGateway').mockImplementation(() => {})
})
afterEach(() => wrappers.splice(0).forEach((w) => w.unmount()))
async function setup(component = Checkout, props = {}) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = { id: '1', role: 'USER' }
  auth.initialized = true
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } },
      { path: '/auctions/:id/checkout', component: { template: '<div />' } },
    ],
  })
  await router.push('/auctions/7/checkout')
  const wrapper = mount(component, { props, global: { plugins: [pinia, router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router, auth }
}
it('shows immutable one-unit winning price, server deadline, addresses and payment methods', async () => {
  const { wrapper } = await setup()
  expect(wrapper.text()).toContain('Số lượng: 1')
  expect(wrapper.text()).toContain('150.000,25')
  expect(wrapper.find('input[type="number"]').exists()).toBe(false)
  expect(wrapper.find('#auction-address').element.value).toBe('9')
  expect(wrapper.find('#auction-payment').findAll('option')).toHaveLength(2)
  expect(wrapper.find('time').attributes('datetime')).toBe('2026-10-09T00:00:00Z')
})
it('COD submits only the supported fields and returns to the existing buyer order', async () => {
  const { wrapper, router } = await setup()
  await wrapper.find('form').trigger('submit')
  await flushPromises()
  expect(api.checkoutAuction).toHaveBeenCalledWith(
    '7',
    { address_id: '9', payment_method: 'COD' },
    expect.any(String),
  )
  expect(router.currentRoute.value.path).toBe('/orders/42')
  expect(payments.redirectToGateway).not.toHaveBeenCalled()
})
it('blocks double submission while an order is being created', async () => {
  let resolve
  api.checkoutAuction.mockImplementation(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  const { wrapper } = await setup()
  await wrapper.find('form').trigger('submit')
  await wrapper.find('form').trigger('submit')
  expect(api.checkoutAuction).toHaveBeenCalledTimes(1)
  resolve(order())
  await flushPromises()
})
it('keeps the same idempotency key and payload after a network failure', async () => {
  api.checkoutAuction.mockRejectedValueOnce(new Error('network'))
  const { wrapper } = await setup()
  await wrapper.find('form').trigger('submit')
  await flushPromises()
  const first = api.checkoutAuction.mock.calls[0]
  expect(wrapper.find('#auction-address').attributes('disabled')).toBeDefined()
  await wrapper.find('form').trigger('submit')
  await flushPromises()
  expect(api.checkoutAuction.mock.calls[1]).toEqual(first)
})
it('restores an uncertain attempt after reload', async () => {
  sessionStorage.setItem(
    'auction-checkout:1:7',
    JSON.stringify({ key: 'saved-key', payload: { address_id: '9', payment_method: 'ZALOPAY' } }),
  )
  const { wrapper } = await setup()
  await wrapper.find('form').trigger('submit')
  await flushPromises()
  expect(api.checkoutAuction).toHaveBeenCalledWith(
    '7',
    { address_id: '9', payment_method: 'ZALOPAY' },
    'saved-key',
  )
})
it('uses existing ZaloPay redirect only for a pending payment with a valid order URL', async () => {
  api.checkoutAuction.mockResolvedValue(order('ZALOPAY'))
  const { wrapper } = await setup()
  await wrapper.find('#auction-payment').setValue('ZALOPAY')
  await wrapper.find('form').trigger('submit')
  await flushPromises()
  expect(payments.redirectToGateway).toHaveBeenCalledWith('https://sb-openapi.zalopay.vn/test')
})
it('failed gateway creation opens the existing order for payment retry', async () => {
  api.checkoutAuction.mockResolvedValue({ ...order('ZALOPAY'), payments: [{ status: 'FAILED' }] })
  const { wrapper, router } = await setup()
  await wrapper.find('form').trigger('submit')
  await flushPromises()
  expect(router.currentRoute.value.path).toBe('/orders/42')
  expect(payments.redirectToGateway).not.toHaveBeenCalled()
})
it('expired claim has no enabled checkout action', async () => {
  api.getAuctionCheckout.mockResolvedValue({
    ...fixture(),
    auction: { ...fixture().auction, settlement_state: 'CHECKOUT_EXPIRED' },
    remaining_seconds: 0,
  })
  const { wrapper } = await setup()
  expect(wrapper.find('[data-auction-expired]').exists()).toBe(true)
  expect(wrapper.find('button:not([type])').attributes('disabled')).toBeDefined()
  await wrapper.find('form').trigger('submit')
  expect(api.checkoutAuction).not.toHaveBeenCalled()
})
it('an existing order replaces checkout controls and links to the original order', async () => {
  api.getAuctionCheckout.mockResolvedValue({
    ...fixture(),
    winner_order: order(),
    auction: { ...fixture().auction, settlement_state: 'ORDER_CREATED' },
  })
  const { wrapper } = await setup()
  expect(wrapper.find('form').exists()).toBe(false)
  expect(wrapper.find('[data-existing-auction-order] a').attributes('href')).toBe('/orders/42')
})
it('unauthorized access shows a friendly state and no checkout form', async () => {
  api.getAuctionCheckout.mockRejectedValue({ response: { status: 403 } })
  const { wrapper } = await setup()
  expect(wrapper.text()).toContain('Chỉ người thắng')
  expect(wrapper.find('form').exists()).toBe(false)
})
it('unavailable product and absent address prevent ordering', async () => {
  api.getAuctionCheckout.mockResolvedValue({
    ...fixture(),
    product_available: false,
    addresses: [],
  })
  const { wrapper } = await setup()
  await wrapper.find('form').trigger('submit')
  expect(api.checkoutAuction).not.toHaveBeenCalled()
})
it('validation errors clear the uncertain attempt and allow correcting the address', async () => {
  api.checkoutAuction.mockRejectedValue({
    response: { status: 422, data: { errors: { address_id: ['Choose your address.'] } } },
  })
  const { wrapper } = await setup()
  await wrapper.find('form').trigger('submit')
  await flushPromises()
  expect(wrapper.text()).toContain('Địa chỉ giao hàng không hợp lệ')
  expect(wrapper.find('#auction-address').attributes('disabled')).toBeUndefined()
  expect(sessionStorage.getItem('auction-checkout:1:7')).toBe(null)
})
it('discarded checkout response cannot redirect a different authenticated user', async () => {
  let resolve
  api.checkoutAuction.mockImplementation(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  const { wrapper, auth, router } = await setup()
  await wrapper.find('form').trigger('submit')
  auth.user = { id: '2', role: 'USER' }
  await flushPromises()
  resolve(order())
  await flushPromises()
  expect(router.currentRoute.value.path).toBe('/auctions/7/checkout')
  expect(payments.redirectToGateway).not.toHaveBeenCalled()
})
it('source labels use winner checkout, seller arena or plain admin reference appropriately', async () => {
  for (const [props, href] of [
    [{ auctionId: '7' }, '/auctions/7/checkout'],
    [{ auctionId: '7', seller: true }, '/seller/auctions/7'],
    [{ auctionId: '7', admin: true }, null],
  ]) {
    const { wrapper } = await setup(Source, props)
    expect(wrapper.text()).toContain('Thắng đấu giá')
    expect(wrapper.findAll('a').map((link) => link.attributes('href'))).toEqual(href ? [href] : [])
  }
})

it('uncertain checkout can be checked even after the displayed deadline expired', async () => {
  sessionStorage.setItem(
    'auction-checkout:1:7',
    JSON.stringify({ key: 'uncertain-key', payload: { address_id: '9', payment_method: 'COD' } }),
  )
  api.getAuctionCheckout.mockResolvedValue({
    ...fixture(),
    auction: { ...fixture().auction, settlement_state: 'CHECKOUT_EXPIRED' },
    product_available: false,
  })
  const { wrapper, router } = await setup()
  await wrapper.find('form').trigger('submit')
  await flushPromises()
  expect(api.checkoutAuction).toHaveBeenCalledWith(
    '7',
    { address_id: '9', payment_method: 'COD' },
    'uncertain-key',
  )
  expect(router.currentRoute.value.path).toBe('/orders/42')
})
