import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia, disposePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useCartStore } from '../stores/cart'
import { createAuthGuard } from '../router/authGuard'
import * as cartApi from '../api/cart.api'
import * as addressApi from '../api/address.api'
import * as api from '../api/order.api'
import axios from '../api/axios'
import CheckoutView from '../views/CheckoutView.vue'
import OrdersView from '../views/OrdersView.vue'
import OrderView from '../views/OrderView.vue'
import CartView from '../views/CartView.vue'
const address = {
  id: '10',
  recipient_name: 'Buyer',
  phone: '0900000000',
  address_line: '123 Street',
  ward: 'Ward',
  province: 'City',
  country_code: 'VN',
  is_default: true,
}
const line = {
  id: '1',
  product: { id: '1', name: 'Phone', slug: 'phone', is_public: true },
  shop: { id: '2', name: 'Shop A' },
  quantity: 2,
  unit_price: '0.10',
  subtotal: '0.20',
  available: true,
  max_quantity: 3,
}
const cartData = {
  id: '4',
  items: [line, { ...line, id: '2', shop: { id: '3', name: 'Shop B' } }],
  subtotal: '0.40',
  available_subtotal: '0.40',
  item_count: 2,
  total_quantity: 4,
  has_unavailable_items: false,
}
const empty = { ...cartData, items: [], subtotal: '0.00', item_count: 0, total_quantity: 0 }
const part = {
  id: '8',
  order_id: '7',
  order_code: 'SHM-TEST',
  shop: { id: '2', name: 'Shop A' },
  status: 'PENDING',
  subtotal: '0.40',
  total_amount: '0.40',
  can_update: true,
  shipping_address: address,
  items: [
    {
      id: '9',
      product_name: 'Phone snapshot',
      product_price: '0.10',
      quantity: 4,
      subtotal: '0.40',
      product_image_url: '/storage/snapshot.png',
    },
  ],
}
const order = {
  id: '7',
  order_code: 'SHM-TEST',
  overall_status: 'PENDING',
  payment_status: 'PENDING',
  total_amount: '0.40',
  payable_amount: '0.40',
  amount_due: '0.40',
  cod_collected_amount: '0.00',
  shipping_address: address,
  shop_orders: [part],
  payments: [{ method: 'COD', status: 'PENDING', amount: '0.40' }],
}
const meta = { current_page: 1, last_page: 2, total: 2 }
const wrappers = [],
  piniaInstances = []
async function setup(component, { seller = false, path = '/checkout', guest = false } = {}) {
  const pinia = createPinia()
  piniaInstances.push(pinia)
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.initialized = true
  auth.user = guest ? null : { id: '4', role: 'USER', status: 'ACTIVE' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      '/checkout',
      '/cart',
      '/orders',
      '/orders/:id',
      '/seller/orders',
      '/seller/orders/:id',
      '/account/addresses',
      '/products',
      '/products/:slug',
      '/login',
    ].map((path) => ({ path, component: { template: '<div />' } })),
  })
  await router.push(path)
  const wrapper = mount(component, { props: { seller }, global: { plugins: [pinia, router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router, auth, cart: useCartStore() }
}
const button = (w, text) => w.findAll('button').find((b) => b.text().includes(text))
beforeEach(() => {
  vi.restoreAllMocks()
  sessionStorage.clear()
  vi.spyOn(cartApi, 'getCart').mockResolvedValue(structuredClone(cartData))
  vi.spyOn(addressApi, 'listAddresses').mockResolvedValue({
    data: [address, { ...address, id: '11', is_default: false, recipient_name: 'Other address' }],
  })
  vi.spyOn(api, 'checkout').mockResolvedValue(structuredClone(order))
  vi.spyOn(api, 'listOrders').mockResolvedValue({ data: [order], meta })
  vi.spyOn(api, 'listSellerOrders').mockResolvedValue({ data: [part], meta })
  vi.spyOn(api, 'getOrder').mockResolvedValue(structuredClone(order))
  vi.spyOn(api, 'getSellerOrder').mockResolvedValue(structuredClone(part))
  vi.spyOn(api, 'cancelShopOrder').mockResolvedValue({
    ...order,
    shop_orders: [{ ...part, status: 'CANCELLED' }],
  })
  vi.spyOn(api, 'updateSellerOrder').mockResolvedValue({ ...part, status: 'CONFIRMED' })
})
afterEach(() => {
  wrappers.splice(0).forEach((w) => w.unmount())
  piniaInstances.splice(0).forEach(disposePinia)
  sessionStorage.clear()
})
it('loads addresses and shop groups with exact displayed decimal total and COD', async () => {
  const { wrapper } = await setup(CheckoutView)
  expect(wrapper.get('#checkout-address').element.value).toBe('10')
  expect(wrapper.findAll('[data-checkout-shop]')).toHaveLength(2)
  expect(wrapper.get('[data-checkout-total]').text()).toContain('0,40 ₫')
  expect(wrapper.text()).toContain('Thanh toán khi nhận hàng')
  expect(button(wrapper, 'Xác nhận').element.disabled).toBe(false)
})
it('submits selected address and COD only, refreshes cart badge and redirects to saved order', async () => {
  const { wrapper, router, cart } = await setup(CheckoutView)
  await wrapper.get('#checkout-address').setValue('11')
  cartApi.getCart.mockResolvedValue(empty)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(api.checkout).toHaveBeenCalledWith(
    { address_id: '11', payment_method: 'COD' },
    expect.stringMatching(/^[0-9a-f-]{36}$/),
  )
  expect(cart.count).toBe(0)
  expect(router.currentRoute.value.path).toBe('/orders/7')
  expect(sessionStorage.length).toBe(0)
})
it('blocks whole-cart checkout when any item is unavailable or no address exists', async () => {
  cartApi.getCart.mockResolvedValue({ ...cartData, has_unavailable_items: true })
  const { wrapper } = await setup(CheckoutView)
  expect(button(wrapper, 'Xác nhận').element.disabled).toBe(true)
  expect(wrapper.text()).toContain('không thể mua')
  await wrapper.get('form').trigger('submit')
  expect(api.checkout).not.toHaveBeenCalled()
  addressApi.listAddresses.mockResolvedValue({ data: [] })
  cartApi.getCart.mockResolvedValue(cartData)
  const next = await setup(CheckoutView)
  expect(button(next.wrapper, 'Xác nhận').element.disabled).toBe(true)
})
it('displays checkout stock conflict, refreshes availability and allows correcting cart', async () => {
  const { wrapper } = await setup(CheckoutView)
  api.checkout.mockRejectedValue({ response: { status: 409, data: { message: 'Stock changed' } } })
  cartApi.getCart.mockResolvedValue({ ...cartData, has_unavailable_items: true })
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.text()).toContain('Dữ liệu đã thay đổi')
  expect(button(wrapper, 'Xác nhận').element.disabled).toBe(true)
  expect(sessionStorage.length).toBe(0)
})
it('shows address validation errors', async () => {
  const { wrapper } = await setup(CheckoutView)
  api.checkout.mockRejectedValue({
    response: { status: 422, data: { errors: { address_id: ['Address no longer exists'] } } },
  })
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.text()).toContain('Địa chỉ giao hàng không hợp lệ')
})
it('retains idempotency key after uncertain network result and retries even when cart is already empty', async () => {
  const { wrapper, router } = await setup(CheckoutView)
  api.checkout.mockRejectedValueOnce(new Error('Network'))
  cartApi.getCart.mockResolvedValue(empty)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  const key = api.checkout.mock.calls[0][1]
  expect(button(wrapper, 'Kiểm tra lại').element.disabled).toBe(false)
  expect(wrapper.get('#checkout-address').element.disabled).toBe(true)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(api.checkout.mock.calls[1][1]).toBe(key)
  expect(router.currentRoute.value.path).toBe('/orders/7')
})
it('restores only the current buyer pending checkout key after page reload', async () => {
  sessionStorage.setItem(
    'checkout-attempt:4',
    JSON.stringify({
      key: '11111111-1111-4111-8111-111111111111',
      payload: { address_id: '11', payment_method: 'COD' },
    }),
  )
  cartApi.getCart.mockResolvedValue(empty)
  const { wrapper } = await setup(CheckoutView)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(api.checkout).toHaveBeenCalledWith(
    { address_id: '11', payment_method: 'COD' },
    '11111111-1111-4111-8111-111111111111',
  )
})
it('prevents duplicate submits while checkout request is pending', async () => {
  let resolve
  api.checkout.mockImplementation(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  const { wrapper } = await setup(CheckoutView)
  await wrapper.get('form').trigger('submit')
  await wrapper.get('form').trigger('submit')
  expect(api.checkout).toHaveBeenCalledOnce()
  resolve(order)
  await flushPromises()
})
it('loads buyer orders and pagination and handles empty list and error', async () => {
  const { wrapper } = await setup(OrdersView, { path: '/orders' })
  expect(wrapper.text()).toContain('SHM-TEST')
  expect(wrapper.get('a[href="/orders/7"]').exists()).toBe(true)
  await button(wrapper, 'Sau').trigger('click')
  await flushPromises()
  expect(api.listOrders).toHaveBeenLastCalledWith({ page: 2, per_page: 20 })
  api.listOrders.mockResolvedValue({ data: [], meta: { last_page: 1 } })
  const next = await setup(OrdersView, { path: '/orders' })
  expect(next.wrapper.text()).toContain('Chưa có đơn hàng')
  api.listOrders.mockRejectedValue(new Error('Offline'))
  const failed = await setup(OrdersView, { path: '/orders' })
  expect(failed.wrapper.text()).toContain('Không thể kết nối')
})
it('buyer detail renders immutable product/address snapshots and cancels a permitted part', async () => {
  const { wrapper } = await setup(OrderView, { path: '/orders/7' })
  expect(wrapper.text()).toContain('Phone snapshot')
  expect(wrapper.text()).toContain('123 Street')
  expect(wrapper.get('img').attributes('src')).toBe('/storage/snapshot.png')
  await wrapper.get('input[name="reason"]').setValue('Changed plans')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(api.cancelShopOrder).toHaveBeenCalledWith('7', '8', {
    expected_status: 'PENDING',
    reason: 'Changed plans',
  })
  expect(wrapper.find('form').exists()).toBe(false)
})
it('buyer cannot cancel SHIPPING and handles unavailable order', async () => {
  api.getOrder.mockResolvedValue({ ...order, shop_orders: [{ ...part, status: 'SHIPPING' }] })
  const { wrapper } = await setup(OrderView, { path: '/orders/7' })
  expect(wrapper.find('form').exists()).toBe(false)
  api.getOrder.mockRejectedValue({ response: { status: 404 } })
  const failed = await setup(OrderView, { path: '/orders/404' })
  expect(failed.wrapper.text()).toContain('Không tìm thấy')
})
it('seller list filters status and links to only seller detail', async () => {
  const { wrapper } = await setup(OrdersView, { seller: true, path: '/seller/orders' })
  expect(wrapper.get('a[href="/seller/orders/8"]').exists()).toBe(true)
  await wrapper.get('select').setValue('SHIPPING')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(api.listSellerOrders).toHaveBeenLastCalledWith({
    page: 1,
    per_page: 20,
    status: 'SHIPPING',
  })
})
it('seller sends expected status and advances only the next transition', async () => {
  const { wrapper } = await setup(OrderView, { seller: true, path: '/seller/orders/8' })
  await wrapper.get('[data-next-status]').trigger('click')
  await flushPromises()
  expect(api.updateSellerOrder).toHaveBeenCalledWith('8', {
    expected_status: 'PENDING',
    status: 'CONFIRMED',
  })
  expect(wrapper.get('[data-next-status]').text()).toContain('Đang xử lý')
})
it('COD completion requires explicit full collection confirmation', async () => {
  api.getSellerOrder.mockResolvedValue({ ...part, status: 'SHIPPING' })
  api.updateSellerOrder.mockResolvedValue({ ...part, status: 'COMPLETED' })
  const { wrapper } = await setup(OrderView, { seller: true, path: '/seller/orders/8' })
  expect(wrapper.get('[data-next-status]').element.disabled).toBe(true)
  expect(wrapper.find('form').exists()).toBe(false)
  await wrapper.get('[data-cod-collected]').setValue(true)
  await wrapper.get('[data-next-status]').trigger('click')
  await flushPromises()
  expect(api.updateSellerOrder).toHaveBeenCalledWith('8', {
    expected_status: 'SHIPPING',
    status: 'COMPLETED',
    cod_collected: true,
  })
  expect(wrapper.find('[data-next-status]').exists()).toBe(false)
})
it('seller cancellation includes failure category, and stale conflict refreshes current state', async () => {
  const { wrapper } = await setup(OrderView, { seller: true, path: '/seller/orders/8' })
  await wrapper.get('input[name="reason"]').setValue('Broken')
  await wrapper.get('select').setValue('DAMAGED')
  api.updateSellerOrder.mockRejectedValue({
    response: { status: 409, data: { message: 'Status changed' } },
  })
  api.getSellerOrder.mockResolvedValue({ ...part, status: 'SHIPPING' })
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(api.updateSellerOrder).toHaveBeenCalledWith('8', {
    expected_status: 'PENDING',
    status: 'CANCELLED',
    reason: 'Broken',
    failure_category: 'DAMAGED',
  })
  expect(wrapper.text()).toContain('Dữ liệu đã thay đổi')
  expect(wrapper.find('form').exists()).toBe(false)
})
it('seller suspended shop sees history without transition buttons', async () => {
  api.getSellerOrder.mockResolvedValue({ ...part, can_update: false })
  const { wrapper } = await setup(OrderView, { seller: true, path: '/seller/orders/8' })
  expect(wrapper.text()).toContain('Phone snapshot')
  expect(wrapper.find('[data-next-status]').exists()).toBe(false)
  expect(wrapper.find('form').exists()).toBe(false)
})
it('cart shows checkout only for a nonempty completely available cart', async () => {
  const { wrapper } = await setup(CartView, { path: '/cart' })
  expect(wrapper.find('a[href="/checkout"]').exists()).toBe(true)
  cartApi.getCart.mockResolvedValue({ ...cartData, has_unavailable_items: true })
  const invalid = await setup(CartView, { path: '/cart' })
  expect(invalid.wrapper.find('a[href="/checkout"]').exists()).toBe(false)
})
it('all order pages require login and checkout API carries CSRF plus idempotency header', async () => {
  const auth = { initialized: true, isAuthenticated: false, isAdmin: false, initialize: vi.fn() }
  for (const path of ['/checkout', '/orders', '/orders/7', '/seller/orders', '/seller/orders/8']) {
    expect(await createAuthGuard(auth)({ path, meta: { requiresAuth: true } })).toEqual({
      name: 'login',
    })
  }
  vi.restoreAllMocks()
  const csrf = vi.spyOn(axios, 'get').mockResolvedValue({ data: null })
  const post = vi.spyOn(axios, 'post').mockResolvedValue({ data: { data: order } })
  const key = '11111111-1111-4111-8111-111111111111'
  await api.checkout({ address_id: '10', payment_method: 'COD' }, key)
  expect(csrf).toHaveBeenCalledWith('/sanctum/csrf-cookie')
  expect(post).toHaveBeenCalledWith(
    '/api/v1/checkout',
    { address_id: '10', payment_method: 'COD' },
    { headers: { 'Idempotency-Key': key } },
  )
})

it('buyer list has status tabs that use the existing overall_status filter', async () => {
  const { wrapper } = await setup(OrdersView, { path: '/orders' })
  const tabs = wrapper.findAll('.order-tab')
  expect(tabs.map((tab) => tab.text())[0]).toBe('Tất cả')
  expect(tabs).toHaveLength(6)
  expect(tabs[0].attributes('aria-pressed')).toBe('true')
  expect(api.listOrders).toHaveBeenLastCalledWith({ page: 1, per_page: 20 })
  await tabs[3].trigger('click')
  await flushPromises()
  expect(api.listOrders).toHaveBeenLastCalledWith({ page: 1, per_page: 20, overall_status: 'COMPLETED' })
  expect(wrapper.findAll('.order-tab')[3].attributes('aria-pressed')).toBe('true')
  await wrapper.findAll('.order-tab')[0].trigger('click')
  await flushPromises()
  expect(api.listOrders).toHaveBeenLastCalledWith({ page: 1, per_page: 20 })
  const seller = await setup(OrdersView, { seller: true, path: '/seller/orders' })
  // The seller list has its own tabs, bound to the same state as its status select.
  const sellerTabs = seller.wrapper.findAll('.order-tab')
  expect(sellerTabs).toHaveLength(7)
  await sellerTabs[4].trigger('click')
  await flushPromises()
  expect(api.listSellerOrders).toHaveBeenLastCalledWith({ page: 1, per_page: 20, status: 'SHIPPING' })
  expect(seller.wrapper.get('select').element.value).toBe('SHIPPING')
})
it('order timeline marks reached steps from API timestamps and shows a cancellation', async () => {
  const { default: OrderTimeline } = await import('../components/OrderTimeline.vue')
  const { mount } = await import('@vue/test-utils')
  const running = mount(OrderTimeline, {
    props: { part: { status: 'PROCESSING', created_at: '2026-10-01T02:00:00Z', confirmed_at: '2026-10-01T03:00:00Z', processing_at: '2026-10-02T03:00:00Z' } },
  })
  const steps = running.findAll('li')
  expect(steps).toHaveLength(5)
  expect(steps.map((step) => step.classes('is-done'))).toEqual([true, true, true, false, false])
  expect(running.get('[aria-current="step"]').text()).toContain('Đang xử lý')
  expect(running.findAll('time')).toHaveLength(3)
  const cancelled = mount(OrderTimeline, {
    props: { part: { status: 'CANCELLED', created_at: '2026-10-01T02:00:00Z', confirmed_at: '2026-10-01T03:00:00Z', cancelled_at: '2026-10-01T04:00:00Z' } },
  })
  expect(cancelled.findAll('li')).toHaveLength(3)
  expect(cancelled.get('.is-cancelled').attributes('aria-current')).toBe('step')
})
