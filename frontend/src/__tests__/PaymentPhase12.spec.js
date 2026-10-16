import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia, disposePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { createAuthGuard } from '../router/authGuard'
import * as orderApi from '../api/order.api'
import * as paymentApi from '../api/payment.api'
import * as cartApi from '../api/cart.api'
import * as addressApi from '../api/address.api'
import CheckoutView from '../views/CheckoutView.vue'
import PaymentResultView from '../views/PaymentResultView.vue'
import PaymentAttempts from '../components/PaymentAttempts.vue'
import OrderView from '../views/OrderView.vue'
import axios from '../api/axios'
const pending = {
  id: '8',
  attempt_number: 1,
  method: 'ZALOPAY',
  status: 'PENDING',
  amount: '250000.00',
  order_url: 'https://sbgateway.zalopay.vn/openinapp?token=test',
}
const order = {
  id: '7',
  order_code: 'SHM-TEST',
  payment_method: 'ZALOPAY',
  payment_status: 'PENDING',
  overall_status: 'PENDING',
  total_amount: '250000.00',
  amount_due: '250000.00',
  cod_collected_amount: '0.00',
  shipping_address: {},
  payments: [pending],
  shop_orders: [],
  can_retry_payment: false,
  reconciliation_required: false,
}
const wrappers = [],
  piniaInstances = []
async function setup(component, props = {}, path = '/payment/result?order=7&status=PAID') {
  const pinia = createPinia()
  piniaInstances.push(pinia)
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.initialized = true
  auth.user = { id: '4', role: 'USER', status: 'ACTIVE' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      '/payment/result',
      '/orders',
      '/orders/:id',
      '/checkout',
      '/cart',
      '/account/addresses',
    ].map((path) => ({ path, component: { template: '<div />' } })),
  })
  await router.push(path)
  const wrapper = mount(component, { props, global: { plugins: [pinia, router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router, auth }
}
beforeEach(() => {
  vi.restoreAllMocks()
  sessionStorage.clear()
  vi.spyOn(orderApi, 'getOrder').mockResolvedValue(structuredClone(order))
  vi.spyOn(paymentApi, 'queryPayment').mockResolvedValue(structuredClone(order))
  vi.spyOn(paymentApi, 'retryPayment').mockResolvedValue({
    ...structuredClone(order),
    payments: [
      { ...pending, status: 'FAILED' },
      { ...pending, id: '9', attempt_number: 2 },
    ],
  })
  vi.spyOn(paymentApi, 'redirectToGateway').mockImplementation(() => {})
  vi.spyOn(orderApi, 'checkout').mockResolvedValue(structuredClone(order))
  vi.spyOn(cartApi, 'getCart').mockResolvedValue({
    items: [
      {
        id: '1',
        quantity: 1,
        product: { name: 'Phone' },
        shop: { id: '2', name: 'Shop A' },
        subtotal: '250000.00',
      },
    ],
    subtotal: '250000.00',
    has_unavailable_items: false,
  })
  vi.spyOn(addressApi, 'listAddresses').mockResolvedValue({
    data: [
      {
        id: '10',
        recipient_name: 'Buyer',
        address_line: 'Street',
        province: 'City',
        is_default: true,
      },
    ],
  })
})
afterEach(() => {
  wrappers.splice(0).forEach((w) => w.unmount())
  piniaInstances.splice(0).forEach(disposePinia)
  sessionStorage.clear()
})
it('result queries owned backend order and ignores forged redirect paid status', async () => {
  vi.useFakeTimers()
  try {
    const { wrapper } = await setup(PaymentResultView)
    expect(orderApi.getOrder).toHaveBeenCalledWith('7')
    expect(paymentApi.queryPayment).toHaveBeenCalledWith('7')
    // "?status=PAID" on the URL changes nothing: the server still says PENDING.
    expect(wrapper.get('[data-verified-result] h2').text()).toBe('Đang xác nhận thanh toán…')
    expect(wrapper.get('[data-payment-attempt]').text()).toContain('Chờ thanh toán')
    expect(wrapper.get('[data-payment-attempt]').text()).not.toContain('Đã thanh toán')
    expect(wrapper.find('[data-check-again]').exists()).toBe(false)
    // About 30 s of asking every 3 s, then the page stops and offers a manual check.
    await vi.advanceTimersByTimeAsync(31000)
    expect(paymentApi.queryPayment.mock.calls.length).toBeGreaterThanOrEqual(9)
    expect(paymentApi.queryPayment.mock.calls.length).toBeLessThanOrEqual(11)
    expect(wrapper.get('[data-verified-result] h2').text()).toBe('Đang chờ kết quả thanh toán')
    expect(wrapper.find('[data-check-again]').exists()).toBe(true)
    const calls = paymentApi.queryPayment.mock.calls.length
    await vi.advanceTimersByTimeAsync(60000)
    expect(paymentApi.queryPayment.mock.calls.length).toBe(calls)
  } finally {
    vi.useRealTimers()
  }
})
it('result keeps asking while pending and shows success as soon as the server confirms it', async () => {
  vi.useFakeTimers()
  try {
    paymentApi.queryPayment
      .mockResolvedValueOnce(structuredClone(order))
      .mockResolvedValueOnce(structuredClone(order))
      .mockResolvedValue({
        ...order,
        payment_status: 'PAID',
        payments: [{ ...pending, status: 'PAID', order_url: null }],
      })
    const { wrapper } = await setup(PaymentResultView)
    expect(wrapper.get('[data-verified-result] h2').text()).toBe('Đang xác nhận thanh toán…')
    await vi.advanceTimersByTimeAsync(6500)
    expect(paymentApi.queryPayment).toHaveBeenCalledTimes(3)
    expect(wrapper.get('[data-verified-result] h2').text()).toBe('Đã thanh toán')
    expect(wrapper.find('[data-verified-result].buyer-result-success').exists()).toBe(true)
    await vi.advanceTimersByTimeAsync(30000)
    expect(paymentApi.queryPayment).toHaveBeenCalledTimes(3)
  } finally {
    vi.useRealTimers()
  }
})
it('result shows failure, and "Kiểm tra lại" asks again after the waiting state', async () => {
  vi.useFakeTimers()
  try {
    paymentApi.queryPayment.mockResolvedValueOnce({
      ...order,
      payment_status: 'FAILED',
      payments: [{ ...pending, status: 'FAILED', order_url: null }],
    })
    const failed = (await setup(PaymentResultView)).wrapper
    expect(failed.get('[data-verified-result] h2').text()).toBe('Thanh toán không thành công')
    expect(failed.find('[data-verified-result].buyer-result-failure').exists()).toBe(true)
    expect(paymentApi.queryPayment).toHaveBeenCalledTimes(1)

    paymentApi.queryPayment.mockResolvedValue(structuredClone(order))
    const waiting = (await setup(PaymentResultView)).wrapper
    await vi.advanceTimersByTimeAsync(31000)
    expect(waiting.get('[data-verified-result] h2').text()).toBe('Đang chờ kết quả thanh toán')
    paymentApi.queryPayment.mockResolvedValue({
      ...order,
      payment_status: 'PAID',
      payments: [{ ...pending, status: 'PAID', order_url: null }],
    })
    await waiting.get('[data-check-again]').trigger('click')
    await flushPromises()
    expect(waiting.get('[data-verified-result] h2').text()).toBe('Đã thanh toán')
    expect(waiting.find('[data-check-again]').exists()).toBe(false)
  } finally {
    vi.useRealTimers()
  }
})
it.each([
  ['FAILED', false, 'Thanh toán không thành công'],
  ['CANCELLED', false, 'Thanh toán đã hủy'],
  ['PAID', true, 'Giao dịch cần đối soát'],
])(
  'result presentation follows verified %s and reconciliation state',
  async (status, reconciliation, title) => {
    paymentApi.queryPayment.mockResolvedValue({
      ...order,
      payment_status: status,
      reconciliation_required: reconciliation,
      payments: [{ ...pending, status, order_url: null }],
    })
    const { wrapper } = await setup(PaymentResultView)
    expect(wrapper.get('[data-verified-result] h2').text()).toBe(title)
    expect(wrapper.find('[data-verified-result].buyer-result-success').exists()).toBe(false)
  },
)
it('only renders paid after verified backend query and removes payment link', async () => {
  paymentApi.queryPayment.mockResolvedValue({
    ...order,
    payment_status: 'PAID',
    payments: [{ ...pending, status: 'PAID', order_url: null }],
  })
  const { wrapper } = await setup(PaymentResultView)
  expect(wrapper.get('[data-payment-attempt]').text()).toContain('Đã thanh toán')
  expect(wrapper.find('[data-resume-payment]').exists()).toBe(false)
  expect(wrapper.find('[data-retry-payment]').exists()).toBe(false)
})
it('query failure preserves pending truth and shows error instead of trusting redirect', async () => {
  paymentApi.queryPayment.mockRejectedValue(new Error('Network'))
  const { wrapper } = await setup(PaymentResultView)
  expect(wrapper.text()).toContain('Không thể kết nối')
  expect(wrapper.get('[data-payment-attempt]').text()).toContain('Chờ thanh toán')
})
it('changing result order while its request is pending loads the new order and ignores the old response', async () => {
  let resolveFirst
  orderApi.getOrder.mockImplementationOnce(
    () =>
      new Promise((resolve) => {
        resolveFirst = resolve
      }),
  )
  const { wrapper, router } = await setup(PaymentResultView)
  const next = { ...structuredClone(order), id: '8', order_code: 'SHM-NEXT' }
  orderApi.getOrder.mockResolvedValue(next)
  paymentApi.queryPayment.mockResolvedValue(next)
  await router.push('/payment/result?order=8&status=PAID')
  await flushPromises()
  expect(orderApi.getOrder).toHaveBeenLastCalledWith('8')
  expect(paymentApi.queryPayment).toHaveBeenCalledWith('8')
  expect(wrapper.text()).toContain('SHM-NEXT')
  resolveFirst(structuredClone(order))
  await flushPromises()
  expect(wrapper.text()).toContain('SHM-NEXT')
  expect(wrapper.text()).not.toContain('SHM-TEST')
  expect(paymentApi.queryPayment).not.toHaveBeenCalledWith('7')
})
it('ownership failure and invalid result order reveal no payment details', async () => {
  orderApi.getOrder.mockRejectedValue({ response: { status: 403 } })
  const { wrapper } = await setup(PaymentResultView)
  expect(wrapper.text()).toContain('không có quyền')
  expect(wrapper.find('[data-payments]').exists()).toBe(false)
  expect(paymentApi.queryPayment).not.toHaveBeenCalled()
  const invalid = await setup(
    PaymentResultView,
    {},
    '/payment/result?order=../../admin&status=PAID',
  )
  expect(invalid.wrapper.find('[data-payments]').exists()).toBe(false)
  expect(invalid.wrapper.get('[role="alert"]').text()).toContain(
    'Mã đơn hàng bị thiếu hoặc không hợp lệ',
  )
  expect(invalid.wrapper.find('a[href="/orders"]').exists()).toBe(true)
  expect(orderApi.getOrder).toHaveBeenCalledTimes(1)
})
it('checkout chooses online channels and redirects using backend url without payment amounts', async () => {
  const { wrapper } = await setup(CheckoutView, {}, '/checkout')
  await wrapper.get('#checkout-payment').setValue('ZALOPAY')
  await wrapper.get('#checkout-channel').setValue('domestic')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(orderApi.checkout).toHaveBeenCalledWith(
    {
      address_id: '10',
      payment_method: 'ZALOPAY',
      preferred_payment_method: ['domestic_card', 'account'],
    },
    expect.any(String),
  )
  expect(paymentApi.redirectToGateway).toHaveBeenCalledWith(pending.order_url)
  expect(wrapper.find('input[name="card_number"]').exists()).toBe(false)
})
it('failed online creation navigates to persisted order with attempt history', async () => {
  orderApi.checkout.mockResolvedValue({
    ...order,
    payment_status: 'FAILED',
    payments: [{ ...pending, status: 'FAILED', order_url: null }],
    can_retry_payment: true,
  })
  const { wrapper, router } = await setup(CheckoutView, {}, '/checkout')
  await wrapper.get('#checkout-payment').setValue('ZALOPAY')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(router.currentRoute.value.path).toBe('/orders/7')
  expect(paymentApi.redirectToGateway).not.toHaveBeenCalled()
})
it('online checkout network retry keeps identical key and original payload', async () => {
  orderApi.checkout.mockRejectedValueOnce(new Error('Lost response'))
  const { wrapper } = await setup(CheckoutView, {}, '/checkout')
  await wrapper.get('#checkout-payment').setValue('ZALOPAY')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  const first = orderApi.checkout.mock.calls[0]
  expect(wrapper.get('#checkout-payment').element.disabled).toBe(true)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(orderApi.checkout.mock.calls[1]).toEqual(first)
})
it('restores the selected gateway channel for a pending checkout without changing its payload', async () => {
  const payload = {
    address_id: '10',
    payment_method: 'ZALOPAY',
    preferred_payment_method: ['domestic_card', 'account'],
  }
  const key = '11111111-1111-4111-8111-111111111111'
  sessionStorage.setItem('checkout-attempt:4', JSON.stringify({ key, payload }))
  const { wrapper } = await setup(CheckoutView, {}, '/checkout')
  expect(wrapper.get('#checkout-channel').element.value).toBe('domestic')
  expect(wrapper.get('#checkout-channel').element.disabled).toBe(true)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(orderApi.checkout).toHaveBeenCalledWith(payload, key)
})
it('retry creates fresh backend attempt and retains idempotency key after network error', async () => {
  paymentApi.retryPayment.mockRejectedValueOnce(new Error('Lost response'))
  const { wrapper } = await setup(PaymentAttempts, {
    order: {
      ...order,
      can_retry_payment: true,
      payments: [{ ...pending, status: 'FAILED', order_url: null }],
    },
  })
  await wrapper.get('[data-retry-payment]').trigger('click')
  await flushPromises()
  const first = paymentApi.retryPayment.mock.calls[0]
  await wrapper.get('[data-retry-payment]').trigger('click')
  await flushPromises()
  expect(paymentApi.retryPayment.mock.calls[1]).toEqual(first)
  expect(wrapper.emitted('changed')[0][0].payments).toHaveLength(2)
  expect(paymentApi.redirectToGateway).toHaveBeenCalledWith(pending.order_url)
})
it('stale retry conflict displays error and reconciliation disables retries and gateway links', async () => {
  paymentApi.retryPayment.mockRejectedValue({
    response: { status: 409, data: { message: 'Order already paid' } },
  })
  const { wrapper } = await setup(PaymentAttempts, { order: { ...order, can_retry_payment: true } })
  await wrapper.get('[data-retry-payment]').trigger('click')
  await flushPromises()
  expect(wrapper.text()).toContain('Dữ liệu đã thay đổi')
  await wrapper.setProps({
    order: { ...order, reconciliation_required: true, can_retry_payment: true },
  })
  expect(wrapper.text()).toContain('cần đối soát')
  expect(wrapper.find('[data-retry-payment]').exists()).toBe(false)
  expect(wrapper.find('[data-resume-payment]').exists()).toBe(false)
})
it('buyer order shows online attempts and query updates backend status', async () => {
  const { wrapper } = await setup(OrderView, {}, '/orders/7')
  expect(wrapper.findAll('[data-payment-attempt]')).toHaveLength(1)
  paymentApi.queryPayment.mockResolvedValue({
    ...order,
    payment_status: 'PAID',
    payments: [{ ...pending, status: 'PAID', order_url: null }],
  })
  await wrapper.get('[data-query-payment]').trigger('click')
  await flushPromises()
  expect(wrapper.get('[data-payment-attempt]').text()).toContain('Đã thanh toán')
  expect(wrapper.text()).not.toContain('Đã thu:')
})
it('seller completes verified online payment without collecting COD again', async () => {
  const part = {
    id: '8',
    order_code: 'SHM-TEST',
    payment_method: 'ZALOPAY',
    payment_ready: true,
    can_cancel_payment: false,
    can_update: true,
    status: 'SHIPPING',
    total_amount: '250000.00',
    shop: { name: 'Shop A' },
    items: [],
    shipping_address: {},
  }
  vi.spyOn(orderApi, 'getSellerOrder').mockResolvedValue(part)
  vi.spyOn(orderApi, 'updateSellerOrder').mockResolvedValue({ ...part, status: 'COMPLETED' })
  const { wrapper } = await setup(OrderView, { seller: true }, '/orders/8')
  expect(wrapper.find('[data-cod-collected]').exists()).toBe(false)
  expect(wrapper.get('[data-next-status]').element.disabled).toBe(false)
  expect(wrapper.find('form').exists()).toBe(false)
  await wrapper.get('[data-next-status]').trigger('click')
  await flushPromises()
  expect(orderApi.updateSellerOrder).toHaveBeenCalledWith('8', {
    expected_status: 'SHIPPING',
    status: 'COMPLETED',
  })
})
it('seller cannot advance unpaid online orders and buyer cannot cancel a paid order', async () => {
  const part = {
    id: '8',
    payment_method: 'ZALOPAY',
    payment_ready: false,
    can_cancel_payment: false,
    can_update: true,
    status: 'PENDING',
    total_amount: '250000.00',
    shop: { name: 'Shop A' },
    items: [],
    shipping_address: {},
  }
  vi.spyOn(orderApi, 'getSellerOrder').mockResolvedValue(part)
  const seller = await setup(OrderView, { seller: true }, '/orders/8')
  expect(seller.wrapper.find('[data-next-status]').exists()).toBe(false)
  orderApi.getOrder.mockResolvedValue({
    ...order,
    payment_status: 'PAID',
    shop_orders: [{ ...part, payment_ready: true }],
  })
  const buyer = await setup(OrderView, {}, '/orders/7')
  expect(buyer.wrapper.find('form').exists()).toBe(false)
})
it('authenticated result route and outbound URL allowlist reject untrusted destinations', async () => {
  const auth = { initialize: vi.fn(), isAuthenticated: false, isAdmin: false }
  expect(await createAuthGuard(auth)({ meta: { requiresAuth: true } })).toEqual({ name: 'login' })
  for (const url of [
    'javascript:alert(1)',
    'https://zalopay.vn.evil.test',
    'https://evil.test',
    'https://user@zalopay.vn',
    'http://sbgateway.zalopay.vn',
  ])
    expect(paymentApi.gatewayUrl(url)).toBeNull()
  expect(paymentApi.gatewayUrl(pending.order_url)).toBe(pending.order_url)
})
it('payment API uses CSRF, strict server actions and retry key, never client PAID', async () => {
  vi.restoreAllMocks()
  vi.spyOn(axios, 'get').mockResolvedValue({})
  vi.spyOn(axios, 'post').mockResolvedValue({ data: { data: order } })
  vi.spyOn(axios, 'request').mockResolvedValue({ data: { data: order } })
  await paymentApi.retryPayment('7', 'test-request-key')
  expect(axios.post).toHaveBeenCalledWith(
    '/api/v1/orders/7/payments/zalopay',
    {},
    { headers: { 'Idempotency-Key': 'test-request-key' } },
  )
  await paymentApi.queryPayment('7')
  expect(axios.request).toHaveBeenCalledWith({
    method: 'post',
    url: '/api/v1/orders/7/payments/zalopay/query',
    data: {},
  })
  expect(axios.get).toHaveBeenCalledWith('/sanctum/csrf-cookie')
})
it('opens ZaloPay in a new tab and keeps this tab on the verified result page', async () => {
  paymentApi.redirectToGateway.mockReturnValue('tab')
  const { wrapper, router } = await setup(CheckoutView, {}, '/checkout')
  await wrapper.get('#checkout-payment').setValue('ZALOPAY')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(paymentApi.redirectToGateway).toHaveBeenCalledWith(pending.order_url)
  expect(router.currentRoute.value.fullPath).toBe('/payment/result?order=7')
})
it('redirectToGateway uses a new tab, cuts the opener, and falls back to this tab when blocked', () => {
  vi.restoreAllMocks()
  const tab = { opener: 'shop' }
  const open = vi.spyOn(window, 'open').mockReturnValue(tab)
  const assign = vi.fn()
  const location = window.location
  Object.defineProperty(window, 'location', { configurable: true, value: { ...location, assign } })
  try {
    expect(paymentApi.redirectToGateway(pending.order_url)).toBe('tab')
    expect(open).toHaveBeenCalledWith(pending.order_url, '_blank')
    expect(tab.opener).toBeNull()
    expect(assign).not.toHaveBeenCalled()
    open.mockReturnValue(null)
    expect(paymentApi.redirectToGateway(pending.order_url)).toBe('same')
    expect(assign).toHaveBeenCalledWith(pending.order_url)
    expect(() => paymentApi.redirectToGateway('https://evil.test/pay')).toThrow('Invalid payment URL')
    expect(paymentApi.paymentResultPath('57')).toBe('/payment/result?order=57')
  } finally {
    Object.defineProperty(window, 'location', { configurable: true, value: location })
  }
})
it('result reads the order id even when ZaloPay appends its own parameters, and re-checks on return', async () => {
  vi.useFakeTimers()
  try {
    const { wrapper } = await setup(
      PaymentResultView,
      {},
      '/payment/result?order=7?amount=250000&apptransid=261016_x&status=1',
    )
    expect(orderApi.getOrder).toHaveBeenCalledWith('7')
    await vi.advanceTimersByTimeAsync(31000)
    expect(wrapper.get('[data-verified-result] h2').text()).toBe('Đang chờ kết quả thanh toán')
    // The buyer finished in the ZaloPay tab and comes back here.
    paymentApi.queryPayment.mockResolvedValue({
      ...order,
      payment_status: 'PAID',
      payments: [{ ...pending, status: 'PAID', order_url: null }],
    })
    window.dispatchEvent(new Event('focus'))
    await flushPromises()
    expect(wrapper.get('[data-verified-result] h2').text()).toBe('Đã thanh toán')
  } finally {
    vi.useRealTimers()
  }
})
