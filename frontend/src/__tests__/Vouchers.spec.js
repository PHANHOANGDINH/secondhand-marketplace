import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import * as cartApi from '../api/cart.api'
import * as addressApi from '../api/address.api'
import * as orderApi from '../api/order.api'
import * as voucherApi from '../api/voucher.api'
import CheckoutView from '../views/CheckoutView.vue'
import OrderView from '../views/OrderView.vue'
import VouchersView from '../views/VouchersView.vue'

const address = { id: '10', recipient_name: 'Buyer', phone: '0900000000', address_line: '123 Street', ward: 'Ward', province: 'City', country_code: 'VN', is_default: true }
const line = (id, shop, subtotal) => ({
  id: String(id),
  product: { id: String(id), name: `Món ${id}`, slug: `mon-${id}`, is_public: true },
  shop: { id: String(shop), name: `Shop ${shop}` },
  quantity: 1,
  unit_price: subtotal,
  subtotal,
  available: true,
  max_quantity: 3,
})
const cartData = { id: '4', items: [line(1, 2, '100000.00'), line(2, 3, '200000.00')], subtotal: '300000.00', available_subtotal: '300000.00', item_count: 2, total_quantity: 2, has_unavailable_items: false }
const quote = (overrides = {}) => ({
  shop_orders: [
    { shop_id: '2', shop_name: 'Shop 2', subtotal: '100000.00', discount_shop: '0.00', discount_platform: '3333.00', total: '96667.00', voucher_code: null },
    { shop_id: '3', shop_name: 'Shop 3', subtotal: '200000.00', discount_shop: '0.00', discount_platform: '6667.00', total: '193333.00', voucher_code: null },
  ],
  subtotal: '300000.00',
  discount_total: '10000.00',
  total: '290000.00',
  platform_voucher_code: 'SAN10K',
  platform_discount: '10000.00',
  errors: { platform_voucher: null, shop_vouchers: {} },
  ...overrides,
})
const order = {
  id: '7',
  order_code: 'SHM-TEST',
  overall_status: 'PENDING',
  payment_status: 'PENDING',
  payment_method: 'COD',
  subtotal: '300000.00',
  discount_amount: '15000.00',
  platform_voucher_code: 'SAN10K',
  total_amount: '285000.00',
  payable_amount: '285000.00',
  amount_due: '285000.00',
  cod_collected_amount: '0.00',
  shipping_address: address,
  payments: [{ method: 'COD', status: 'PENDING', amount: '285000.00' }],
  shop_orders: [
    { id: '8', order_id: '7', shop: { id: '2', name: 'Shop 2' }, status: 'PENDING', subtotal: '100000.00', discount_amount: '8220.00', discount_shop: '5000.00', discount_platform: '3220.00', voucher_code: 'SHOPTEN', total_amount: '91780.00', items: [], created_at: '2026-10-14T01:00:00Z' },
    { id: '9', order_id: '7', shop: { id: '3', name: 'Shop 3' }, status: 'PENDING', subtotal: '200000.00', discount_amount: '0.00', discount_shop: '0.00', discount_platform: '0.00', voucher_code: null, total_amount: '200000.00', items: [], created_at: '2026-10-14T01:00:00Z' },
  ],
}
const voucher = (id, extra = {}) => ({
  id: String(id),
  code: `CODE${id}`,
  scope: 'SHOP',
  shop: { id: '2', name: 'Shop 2', slug: 'shop-2' },
  type: 'FIXED',
  value: '20000.00',
  min_order: '100000.00',
  max_discount: null,
  usage_limit: 10,
  per_user_limit: 1,
  used_count: 3,
  starts_at: '2026-10-01T00:00:00.000Z',
  ends_at: '2026-11-01T00:00:00.000Z',
  status: 'ACTIVE',
  state: 'ACTIVE',
  has_redemptions: false,
  can_manage: true,
  ...extra,
})
const wrappers = []
async function setup(component, { path = '/checkout', props = {} } = {}) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.initialized = true
  auth.user = { id: '4', role: 'USER', status: 'ACTIVE' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: ['/checkout', '/cart', '/orders', '/orders/:id', '/account/addresses', '/products', '/products/:slug', '/seller/vouchers', '/admin/vouchers', '/login'].map(
      (route) => ({ path: route, component: { template: '<div />' } }),
    ),
  })
  await router.push(path)
  const wrapper = mount(component, { props, attachTo: document.body, global: { plugins: [pinia, router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router }
}
const button = (wrapper, text) => wrapper.findAll('button').find((item) => item.text().includes(text))
beforeEach(() => {
  vi.restoreAllMocks()
  sessionStorage.clear()
  vi.spyOn(cartApi, 'getCart').mockResolvedValue(structuredClone(cartData))
  vi.spyOn(addressApi, 'listAddresses').mockResolvedValue({ data: [address] })
  vi.spyOn(orderApi, 'checkout').mockResolvedValue(structuredClone(order))
  vi.spyOn(orderApi, 'getOrder').mockResolvedValue(structuredClone(order))
  vi.spyOn(voucherApi, 'previewCheckout').mockResolvedValue(quote())
  vi.spyOn(voucherApi, 'listVouchers').mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })
  vi.spyOn(voucherApi, 'createVoucher').mockResolvedValue(voucher(1))
  vi.spyOn(voucherApi, 'updateVoucher').mockResolvedValue(voucher(1))
  vi.spyOn(voucherApi, 'deactivateVoucher').mockResolvedValue(voucher(1, { status: 'INACTIVE' }))
})
afterEach(() => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount()
  document.body.innerHTML = ''
  sessionStorage.clear()
})

it('checkout applies a platform code through the preview, updates the sticky total and sends the code', async () => {
  const { wrapper } = await setup(CheckoutView)
  expect(wrapper.get('[data-checkout-total]').text()).toContain('300.000')
  expect(wrapper.get('[data-checkout-discount]').text()).toContain('Giảm giá: 0 ₫')
  const panel = wrapper.get('[data-platform-voucher]')
  expect(button(panel, 'Áp dụng').element.disabled).toBe(true)
  await panel.get('#voucher-platform').setValue(' san10k ')
  await button(panel, 'Áp dụng').trigger('click')
  await flushPromises()
  expect(voucherApi.previewCheckout).toHaveBeenCalledWith({ platform_voucher: 'SAN10K' })
  expect(wrapper.get('[data-checkout-total]').text()).toContain('290.000')
  expect(wrapper.get('[data-checkout-discount]').text()).toContain('10.000')
  expect(wrapper.findAll('[data-shop-discount]').map((row) => row.text())).toEqual([
    expect.stringContaining('3.333'),
    expect.stringContaining('6.667'),
  ])
  expect(panel.get('#voucher-platform').element.disabled).toBe(true)
  expect(button(panel, 'Bỏ mã')).toBeDefined()

  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(orderApi.checkout).toHaveBeenCalledWith(
    { address_id: '10', payment_method: 'COD', platform_voucher: 'SAN10K' },
    expect.stringMatching(/^[0-9a-f-]{36}$/),
  )
})

it('checkout shows the reason for a rejected code, keeps the old total, and can drop an applied code', async () => {
  voucherApi.previewCheckout.mockResolvedValueOnce(
    quote({ platform_voucher_code: null, platform_discount: '0.00', discount_total: '0.00', total: '300000.00', errors: { platform_voucher: 'Mã giảm giá đã hết hạn.', shop_vouchers: {} }, shop_orders: quote().shop_orders.map((row) => ({ ...row, discount_platform: '0.00', total: row.subtotal })) }),
  )
  const { wrapper } = await setup(CheckoutView)
  const panel = wrapper.get('[data-platform-voucher]')
  await panel.get('#voucher-platform').setValue('OLD')
  await button(panel, 'Áp dụng').trigger('click')
  await flushPromises()
  expect(panel.get('[role="alert"]').text()).toBe('Mã giảm giá đã hết hạn.')
  expect(wrapper.get('[data-checkout-total]').text()).toContain('300.000')
  expect(wrapper.find('[data-shop-discount]').exists()).toBe(false)
  expect(panel.get('#voucher-platform').element.disabled).toBe(false)

  await panel.get('#voucher-platform').setValue('SAN10K')
  await button(panel, 'Áp dụng').trigger('click')
  await flushPromises()
  expect(wrapper.get('[data-checkout-total]').text()).toContain('290.000')
  voucherApi.previewCheckout.mockClear()
  await button(panel, 'Bỏ mã').trigger('click')
  await flushPromises()
  // Nothing left to price: no request, back to the plain total.
  expect(voucherApi.previewCheckout).not.toHaveBeenCalled()
  expect(wrapper.get('[data-checkout-total]').text()).toContain('300.000')
  expect(panel.get('#voucher-platform').element.value).toBe('')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(orderApi.checkout).toHaveBeenCalledWith({ address_id: '10', payment_method: 'COD' }, expect.any(String))
})

it('checkout has one code box per shop and combines shop and platform codes; buy now passes its selection', async () => {
  const shopQuote = quote({
    platform_voucher_code: null,
    platform_discount: '0.00',
    discount_total: '5000.00',
    total: '295000.00',
    shop_orders: [
      { shop_id: '2', shop_name: 'Shop 2', subtotal: '100000.00', discount_shop: '5000.00', discount_platform: '0.00', total: '95000.00', voucher_code: 'SHOPTEN' },
      { shop_id: '3', shop_name: 'Shop 3', subtotal: '200000.00', discount_shop: '0.00', discount_platform: '0.00', total: '200000.00', voucher_code: null },
    ],
  })
  voucherApi.previewCheckout.mockResolvedValueOnce(shopQuote)
  const { wrapper } = await setup(CheckoutView)
  expect(wrapper.findAll('[data-shop-voucher]').map((box) => box.attributes('data-shop-voucher'))).toEqual(['2', '3'])
  const box = wrapper.get('[data-shop-voucher="2"]')
  await box.get('input').setValue('shopten')
  await button(box, 'Áp dụng').trigger('click')
  await flushPromises()
  expect(voucherApi.previewCheckout).toHaveBeenLastCalledWith({ shop_vouchers: { 2: 'SHOPTEN' } })
  expect(box.get('[data-shop-discount]').text()).toContain('5.000')
  expect(wrapper.get('[data-checkout-total]').text()).toContain('295.000')

  voucherApi.previewCheckout.mockResolvedValueOnce({ ...shopQuote, platform_voucher_code: 'SAN10K', platform_discount: '10000.00', discount_total: '15000.00', total: '285000.00' })
  const panel = wrapper.get('[data-platform-voucher]')
  await panel.get('#voucher-platform').setValue('SAN10K')
  await button(panel, 'Áp dụng').trigger('click')
  await flushPromises()
  expect(voucherApi.previewCheckout).toHaveBeenLastCalledWith({ platform_voucher: 'SAN10K', shop_vouchers: { 2: 'SHOPTEN' } })
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(orderApi.checkout).toHaveBeenCalledWith(
    { address_id: '10', payment_method: 'COD', platform_voucher: 'SAN10K', shop_vouchers: { 2: 'SHOPTEN' } },
    expect.any(String),
  )

  const buyNow = await setup(CheckoutView, { path: '/checkout?items=2' })
  expect(buyNow.wrapper.findAll('[data-shop-voucher]')).toHaveLength(1)
  await buyNow.wrapper.get('#voucher-platform').setValue('SAN10K')
  await button(buyNow.wrapper.get('[data-platform-voucher]'), 'Áp dụng').trigger('click')
  await flushPromises()
  expect(voucherApi.previewCheckout).toHaveBeenLastCalledWith({ cart_item_ids: [2], platform_voucher: 'SAN10K' })
})

it('order detail shows the codes used and each discount', async () => {
  const { wrapper } = await setup(OrderView, { path: '/orders/7' })
  const summary = wrapper.get('[data-order-discount]').text()
  expect(summary).toContain('15.000')
  expect(summary).toContain('SAN10K')
  const parts = wrapper.findAll('[data-part-discount]')
  expect(parts).toHaveLength(1)
  expect(parts[0].text()).toContain('SHOPTEN')
  expect(parts[0].text()).toContain('5.000')
  expect(parts[0].text()).toContain('3.220')
  orderApi.getOrder.mockResolvedValue({ ...order, discount_amount: '0.00', platform_voucher_code: null, shop_orders: [order.shop_orders[1]] })
  const plain = await setup(OrderView, { path: '/orders/7' })
  expect(plain.wrapper.find('[data-order-discount]').exists()).toBe(false)
  expect(plain.wrapper.find('[data-part-discount]').exists()).toBe(false)
})

it('seller voucher screen lists Vietnamese states and usage, and creates a voucher from the dialog', async () => {
  voucherApi.listVouchers.mockResolvedValue({
    data: [voucher(1), voucher(2, { state: 'EXHAUSTED', usage_limit: 3 }), voucher(3, { state: 'EXPIRED', type: 'PERCENT', value: '15.00', max_discount: '30000.00', usage_limit: null }), voucher(4, { state: 'INACTIVE', status: 'INACTIVE' }), voucher(5, { state: 'SCHEDULED' })],
    meta: { current_page: 1, last_page: 1, total: 5 },
  })
  const { wrapper } = await setup(VouchersView, { path: '/seller/vouchers' })
  expect(voucherApi.listVouchers).toHaveBeenCalledWith(false, { page: 1, per_page: 20 })
  expect(wrapper.findAll('.buyer-status').map((badge) => badge.text())).toEqual(['Đang áp dụng', 'Hết lượt', 'Hết hạn', 'Đã tắt', 'Chưa bắt đầu'])
  expect(wrapper.findAll('[data-voucher-usage]').map((cell) => cell.text())).toEqual(['3 / 10', '3 / 3', '3 / không giới hạn', '3 / 10', '3 / 10'])
  expect(wrapper.get('[data-voucher="3"]').text()).toContain('Giảm 15%, tối đa 30.000')
  expect(wrapper.text()).not.toMatch(/\b(ACTIVE|INACTIVE|EXHAUSTED|EXPIRED|SCHEDULED|PERCENT|FIXED)\b/)
  expect(wrapper.find('.order-tab').exists()).toBe(false)

  await wrapper.get('[data-voucher-create]').trigger('click')
  const dialog = document.querySelector('#voucher-dialog')
  expect(dialog.getAttribute('role')).toBe('dialog')
  const set = (selector, value) => {
    const input = dialog.querySelector(selector)
    input.value = value
    input.dispatchEvent(new Event(input.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true }))
  }
  set('#voucher-code', ' shop20k ')
  set('#voucher-value', '20000')
  set('#voucher-min', '100000')
  set('#voucher-limit', '10')
  set('#voucher-start', '2026-10-14T08:00')
  set('#voucher-end', '2026-11-14T08:00')
  dialog.querySelector('#voucher-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
  await flushPromises()
  expect(voucherApi.createVoucher).toHaveBeenCalledWith(false, {
    code: 'SHOP20K',
    type: 'FIXED',
    value: '20000',
    min_order: '100000',
    max_discount: null,
    usage_limit: 10,
    per_user_limit: null,
    starts_at: new Date('2026-10-14T08:00').toISOString(),
    ends_at: new Date('2026-11-14T08:00').toISOString(),
  })
  expect(document.querySelector('#voucher-dialog')).toBeNull()
  expect(wrapper.text()).toContain('Đã tạo mã giảm giá.')
})

it('a used voucher can only change its end date and total limit; switching off and on use the right calls', async () => {
  voucherApi.listVouchers.mockResolvedValue({ data: [voucher(1, { has_redemptions: true }), voucher(2, { status: 'INACTIVE', state: 'INACTIVE' })], meta: { current_page: 1, last_page: 1, total: 2 } })
  const { wrapper } = await setup(VouchersView, { path: '/seller/vouchers' })
  await wrapper.get('[data-voucher="1"] [data-voucher-edit]').trigger('click')
  const dialog = document.querySelector('#voucher-dialog')
  expect(dialog.textContent).toContain('chỉ đổi được ngày kết thúc và giới hạn lượt dùng')
  for (const id of ['#voucher-code', '#voucher-type', '#voucher-value', '#voucher-min', '#voucher-per-user', '#voucher-start'])
    expect(dialog.querySelector(id).disabled).toBe(true)
  for (const id of ['#voucher-limit', '#voucher-end']) expect(dialog.querySelector(id).disabled).toBe(false)
  const limit = dialog.querySelector('#voucher-limit')
  limit.value = '20'
  limit.dispatchEvent(new Event('input', { bubbles: true }))
  voucherApi.updateVoucher.mockRejectedValueOnce({ response: { status: 422, data: { errors: { usage_limit: ['Giới hạn lượt dùng không được thấp hơn số lượt đã dùng (3).'] } } } })
  dialog.querySelector('#voucher-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
  await flushPromises()
  expect(voucherApi.updateVoucher).toHaveBeenCalledWith(false, '1', { ends_at: '2026-11-01T00:00:00.000Z', usage_limit: 20 })
  expect(document.querySelector('#voucher-dialog').textContent).toContain('không được thấp hơn số lượt đã dùng')
  dialog.querySelector('#voucher-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
  await flushPromises()
  expect(document.querySelector('#voucher-dialog')).toBeNull()

  await wrapper.get('[data-voucher="1"] [data-voucher-toggle]').trigger('click')
  await flushPromises()
  expect(voucherApi.deactivateVoucher).toHaveBeenCalledWith(false, '1')
  await wrapper.get('[data-voucher="2"] [data-voucher-toggle]').trigger('click')
  await flushPromises()
  expect(voucherApi.updateVoucher).toHaveBeenLastCalledWith(false, '2', { status: 'ACTIVE', expected_status: 'INACTIVE' })
})

it('admin screen filters by scope and shows shop vouchers as read-only', async () => {
  voucherApi.listVouchers.mockResolvedValue({
    data: [voucher(1, { scope: 'PLATFORM', shop: null }), voucher(2, { can_manage: false })],
    meta: { current_page: 1, last_page: 1, total: 2 },
  })
  const { wrapper } = await setup(VouchersView, { path: '/admin/vouchers', props: { admin: true } })
  expect(voucherApi.listVouchers).toHaveBeenCalledWith(true, { page: 1, per_page: 20 })
  expect(wrapper.get('[data-voucher="1"]').text()).toContain('Toàn sàn')
  expect(wrapper.get('[data-voucher="1"]').find('[data-voucher-edit]').exists()).toBe(true)
  expect(wrapper.get('[data-voucher="2"]').text()).toContain('Shop 2')
  expect(wrapper.get('[data-voucher="2"]').text()).toContain('Chỉ xem')
  expect(wrapper.get('[data-voucher="2"]').find('button').exists()).toBe(false)
  await button(wrapper, 'Mã gian hàng').trigger('click')
  await flushPromises()
  expect(voucherApi.listVouchers).toHaveBeenLastCalledWith(true, { page: 1, per_page: 20, scope: 'SHOP' })
})
