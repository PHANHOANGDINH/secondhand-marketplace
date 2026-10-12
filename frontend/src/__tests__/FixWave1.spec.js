import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { beforeEach, afterEach, it, expect, vi } from 'vitest'
import { useAuthStore } from '../stores/auth'
import appRouter from '../router'
import { formatCancellationReason } from '../utils/labels'
import * as orderApi from '../api/order.api'
import * as auctionApi from '../api/auction.api'
import * as productApi from '../api/product.api'
import * as shopApi from '../api/shop.api'
import OrderView from '../views/OrderView.vue'
import SellerAuctionsView from '../views/SellerAuctionsView.vue'

vi.mock('../composables/auctionEcho', () => ({
  realtimeConnection: { value: 'disabled' },
  subscribeRealtime: () => () => {},
}))

const wrappers = []
const address = {
  recipient_name: 'Người mua',
  phone: '0900000000',
  address_line: '123 Đường Thử',
  ward: 'Phường',
  province: 'Thành phố',
  country_code: 'VN',
}
const part = (reason) => ({
  id: '8',
  order_id: '7',
  order_code: 'SHM-TEST',
  shop: { id: '2', name: 'Gian hàng A' },
  status: 'CANCELLED',
  subtotal: '100000.00',
  total_amount: '100000.00',
  cancellation_reason: reason,
  shipping_address: address,
  items: [],
})
const order = (reason) => ({
  id: '7',
  order_code: 'SHM-TEST',
  overall_status: 'CANCELLED',
  payment_status: 'CANCELLED',
  payment_method: 'COD',
  total_amount: '100000.00',
  payable_amount: '0.00',
  amount_due: '0.00',
  cod_collected_amount: '0.00',
  shipping_address: address,
  shop_orders: [part(reason)],
  payments: [],
})
async function show(component, path, props = {}) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.initialized = true
  auth.user = { id: '4', role: 'USER', status: 'ACTIVE' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }],
  })
  await router.push(path)
  const wrapper = mount(component, { props, global: { plugins: [pinia, router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return wrapper
}
beforeEach(() => vi.restoreAllMocks())
afterEach(() => {
  wrappers.splice(0).forEach((w) => w.unmount())
  vi.useRealTimers()
})

it('registers every route name and path exactly once', () => {
  const routes = appRouter.options.routes.filter((route) => !route.path.includes(':pathMatch'))
  for (const key of ['name', 'path']) {
    const values = routes.map((route) => route[key])
    const repeated = values.filter((value, index) => values.indexOf(value) !== index)
    expect(repeated, `duplicate route ${key}`).toEqual([])
  }
  expect(routes.some((route) => route.path === '/seller/inventory')).toBe(true)
})

it('translates the stored failure category of a cancellation reason', () => {
  expect(formatCancellationReason('OUT_OF_STOCK: Kho đã hết')).toBe('Hết hàng — Kho đã hết')
  expect(formatCancellationReason('DAMAGED:Vỡ màn hình')).toBe('Hư hỏng — Vỡ màn hình')
  expect(formatCancellationReason('UNFULFILLABLE:   Không liên hệ được')).toBe(
    'Không giao được — Không liên hệ được',
  )
  expect(formatCancellationReason('OUT_OF_STOCK: ')).toBe('Hết hàng')
  expect(formatCancellationReason('Đổi ý, không mua nữa')).toBe('Đổi ý, không mua nữa')
  expect(formatCancellationReason('Ghi chú OUT_OF_STOCK: ở giữa')).toBe(
    'Ghi chú OUT_OF_STOCK: ở giữa',
  )
  expect(formatCancellationReason('UNKNOWN_CODE: giữ nguyên')).toBe('UNKNOWN_CODE: giữ nguyên')
  expect(formatCancellationReason(null)).toBe('')
})

it.each([
  ['buyer', false, '/orders/7'],
  ['seller', true, '/seller/orders/8'],
])('%s order detail shows the cancellation reason in Vietnamese', async (_, seller, path) => {
  vi.spyOn(orderApi, 'getOrder').mockResolvedValue(order('OUT_OF_STOCK: Kho đã hết'))
  vi.spyOn(orderApi, 'getSellerOrder').mockResolvedValue(part('OUT_OF_STOCK: Kho đã hết'))
  const wrapper = await show(OrderView, path, { seller })
  expect(wrapper.text()).toContain('Lý do hủy: Hết hàng — Kho đã hết')
  expect(wrapper.text()).not.toContain('OUT_OF_STOCK')
})

it('keeps a buyer written cancellation reason unchanged', async () => {
  vi.spyOn(orderApi, 'getOrder').mockResolvedValue(order('Đổi ý, không mua nữa'))
  const wrapper = await show(OrderView, '/orders/7')
  expect(wrapper.text()).toContain('Lý do hủy: Đổi ý, không mua nữa')
})

const serverTime = '2026-10-08T00:00:00.000000Z'
const auction = (endsAt) => ({
  id: '7',
  seller_id: '4',
  product_id: '3',
  product: { id: '3', name: 'Máy ảnh', slug: 'may-anh' },
  status: 'LIVE',
  start_price: '100000.00',
  current_price: '100000.00',
  minimum_next_bid: '100000.00',
  bid_increment: '10000.00',
  bid_count: 0,
  starts_at: '2026-10-07T23:00:00Z',
  ends_at: endsAt,
  server_time: serverTime,
  updated_at: serverTime,
})
async function sellerAuctions(endsAt, clientClock, topLevelTime = true) {
  vi.setSystemTime(new Date(clientClock))
  vi.spyOn(auctionApi, 'listSellerAuctions').mockImplementation(async (params) => ({
    data: params?.status ? [] : [auction(endsAt)],
    meta: { current_page: 1, last_page: 1 },
    ...(topLevelTime ? { server_time: serverTime } : {}),
  }))
  vi.spyOn(productApi, 'listSellerProducts').mockResolvedValue({ data: [], meta: { last_page: 1 } })
  vi.spyOn(productApi, 'listProducts').mockResolvedValue({ data: [], meta: { last_page: 1 } })
  vi.spyOn(shopApi, 'getSellerShop').mockResolvedValue({ id: '2', status: 'ACTIVE', name: 'Shop' })
  return show(SellerAuctionsView, '/seller/auctions')
}

it('offers cancellation by server time when the device clock runs a day ahead', async () => {
  const wrapper = await sellerAuctions('2026-10-08T01:00:00Z', '2026-10-09T00:00:00Z')
  expect(wrapper.find('.btn-outline-danger').exists()).toBe(true)
})

it('hides cancellation by server time when the device clock runs a day behind', async () => {
  const wrapper = await sellerAuctions('2026-10-07T23:30:00Z', '2026-10-07T00:00:00Z')
  expect(wrapper.find('.btn-outline-danger').exists()).toBe(false)
})

it('falls back to the row server_time when the collection carries none', async () => {
  const wrapper = await sellerAuctions('2026-10-08T01:00:00Z', '2026-10-09T00:00:00Z', false)
  expect(wrapper.find('.btn-outline-danger').exists()).toBe(true)
})
