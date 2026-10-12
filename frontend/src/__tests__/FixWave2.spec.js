import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { beforeEach, afterEach, it, expect, vi } from 'vitest'
import { useAuthStore } from '../stores/auth'
import * as productApi from '../api/product.api'
import * as videoApi from '../api/productVideo.api'
import * as inventoryApi from '../api/inventory.api'
import * as shopApi from '../api/shop.api'
import * as optionsApi from '../api/catalogOptions'
import {
  auctionLockMessage,
  auctionLockRoute,
  sellerActions,
} from '../composables/productPresentation'
import SellerProductsView from '../views/SellerProductsView.vue'
import SellerInventoryView from '../views/SellerInventoryView.vue'
import SellerProductEditView from '../views/SellerProductEditView.vue'

const product = (extra = {}) => ({
  id: '10',
  name: 'Máy ảnh cũ',
  slug: 'may-anh-cu',
  description: 'Mô tả trung thực',
  price: '500000.00',
  stock_quantity: 3,
  inventory_state: 'LOW_STOCK',
  stock_editable: true,
  stock_edit_reason: null,
  auction_locked: false,
  auction: null,
  condition: 'GOOD',
  status: 'ACTIVE',
  can_edit: true,
  can_moderate: false,
  archived: false,
  shop_status: 'ACTIVE',
  updated_at: '2026-10-10T01:00:00.000000Z',
  created_at: '2026-10-01T01:00:00Z',
  shop: { id: '5', name: 'Gian hàng', slug: 'gian-hang' },
  category: { id: '3', name: 'Máy ảnh', slug: 'may-anh' },
  images: [
    { id: '20', url: '/storage/products/10/a.png', is_primary: true, sort_order: 0 },
    { id: '21', url: '/storage/products/10/b.png', is_primary: false, sort_order: 1 },
  ],
  primary_image: { url: '/storage/products/10/a.png' },
  ...extra,
})
const held = (extra = {}) =>
  product({
    id: '11',
    name: 'Đồng hồ đang đấu giá',
    auction_locked: true,
    stock_editable: false,
    stock_edit_reason: 'Sản phẩm đang được giữ cho đấu giá.',
    auction: { id: '9', status: 'LIVE', current_price: '120000.00' },
    ...extra,
  })
const collection = (data) => ({
  data,
  low_stock_threshold: 5,
  meta: { current_page: 1, last_page: 1, total: data.length },
})
const wrappers = []
async function show(component, path) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = { id: '1', role: 'USER', status: 'ACTIVE' }
  auth.initialized = true
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/seller/products/:id/edit', component: { template: '<div />' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } },
    ],
  })
  await router.push(path)
  await router.isReady()
  const wrapper = mount(component, { global: { plugins: [pinia, router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return wrapper
}
beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(shopApi, 'getSellerShop').mockResolvedValue({ id: '5', status: 'ACTIVE', name: 'Shop' })
  vi.spyOn(optionsApi, 'categoryOptions').mockResolvedValue([{ value: '3', label: 'Máy ảnh' }])
  vi.spyOn(videoApi, 'listSellerVideos').mockResolvedValue({
    data: [],
    limits: { max_files: 3, max_bytes: 52428800 },
  })
})
afterEach(() => wrappers.splice(0).forEach((w) => w.unmount()))

it('offers no seller transition while an auction holds the product', () => {
  expect(sellerActions(product())).toEqual(['hide'])
  expect(sellerActions(held())).toEqual([])
  for (const status of ['DRAFT', 'PENDING', 'REJECTED', 'HIDDEN', 'SOLD'])
    expect(sellerActions(held({ status }))).toEqual([])
  expect(auctionLockRoute(held())).toBe('/seller/auctions/9')
  expect(auctionLockRoute(held({ auction: null }))).toBe(null)
  expect(auctionLockRoute(held({ auction: { id: '../admin' } }))).toBe(null)
  expect(auctionLockRoute(product({ auction: { id: '9' } }))).toBe(null)
})

it('seller product list locks the held row and explains why with a link to the auction', async () => {
  vi.spyOn(productApi, 'listSellerProducts').mockResolvedValue(collection([product(), held()]))
  const wrapper = await show(SellerProductsView, '/seller/products')
  const free = wrapper.get('[data-product="10"]'),
    locked = wrapper.get('[data-product="11"]')
  expect(free.find('[data-action="hide"]').exists()).toBe(true)
  expect(free.text()).toContain('Xem / Sửa')
  expect(free.find('[data-auction-lock]').exists()).toBe(false)
  expect(locked.findAll('button')).toHaveLength(0)
  expect(locked.text()).not.toContain('Xem / Sửa')
  expect(locked.get('[data-auction-lock]').text()).toContain(auctionLockMessage)
  expect(locked.get('[data-auction-lock] a').attributes('href')).toBe('/seller/auctions/9')
})

it('seller inventory replaces quick editing with the auction notice', async () => {
  vi.spyOn(inventoryApi, 'listInventory').mockResolvedValue(collection([held()]))
  const wrapper = await show(SellerInventoryView, '/seller/inventory')
  expect(wrapper.find('[aria-label^="Cập nhật tồn kho:"]').exists()).toBe(false)
  expect(wrapper.get('[data-auction-lock]').text()).toContain(auctionLockMessage)
  expect(wrapper.get('[data-auction-lock] a').attributes('href')).toBe('/seller/auctions/9')
})

it.each([
  ['ACTIVE', 'ACTIVE', 'Đã cập nhật tồn kho.', 'mở bán lại'],
  ['SOLD', 'ACTIVE', 'Đã cập nhật tồn kho. Sản phẩm đã mở bán lại.', 'chờ duyệt theo'],
  ['ACTIVE', 'SOLD', 'Đã cập nhật tồn kho.', 'mở bán lại'],
  ['SOLD', 'PENDING', 'chờ duyệt theo quy định hiện có', 'mở bán lại'],
  ['HIDDEN', 'HIDDEN', 'Đã cập nhật tồn kho.', 'chờ duyệt theo'],
])('stock saved from %s to %s reports the right outcome', async (from, to, shown, absent) => {
  vi.spyOn(inventoryApi, 'listInventory').mockResolvedValue(collection([product({ status: from })]))
  vi.spyOn(productApi, 'updateProduct').mockResolvedValue(product({ status: to }))
  const wrapper = await show(SellerInventoryView, '/seller/inventory')
  await wrapper.get('[aria-label^="Cập nhật tồn kho:"]').trigger('click')
  expect(wrapper.get('#inventory-help').text()).toContain('vẫn tiếp tục được bán')
  await wrapper.get('#inventory-quantity').setValue('8')
  await wrapper.get('form[aria-label="Cập nhật tồn kho"]').trigger('submit')
  await flushPromises()
  expect(productApi.updateProduct).toHaveBeenCalledWith('10', {
    stock_quantity: 8,
    expected_updated_at: '2026-10-10T01:00:00.000000Z',
  })
  const status = wrapper.get('[role="status"]').text()
  expect(status).toContain(shown)
  expect(status).not.toContain(absent)
})

it('seller editor disables content and images but keeps videos while an auction holds the product', async () => {
  vi.spyOn(productApi, 'getSellerProduct').mockResolvedValue(held({ id: '10' }))
  const wrapper = await show(SellerProductEditView, '/seller/products/10/edit')
  expect(wrapper.get('[data-auction-lock]').text()).toContain(auctionLockMessage)
  expect(wrapper.get('[data-auction-lock] a').attributes('href')).toBe('/seller/auctions/9')
  expect(wrapper.get('#seller-product-form fieldset').attributes('disabled')).toBeDefined()
  expect(wrapper.find('#seller-product-form button[type="submit"]').exists()).toBe(false)
  expect(wrapper.get('#product_images').attributes('disabled')).toBeDefined()
  expect(wrapper.get('#product-video').attributes('disabled')).toBeUndefined()
  expect(wrapper.find('[data-action]').exists()).toBe(false)
  expect(wrapper.text()).not.toContain('Chỉ thay đổi tồn kho thì sản phẩm vẫn tiếp tục')
})

it('seller editor stays editable without a claim and describes the stock-only rule', async () => {
  vi.spyOn(productApi, 'getSellerProduct').mockResolvedValue(product())
  const wrapper = await show(SellerProductEditView, '/seller/products/10/edit')
  expect(wrapper.find('[data-auction-lock]').exists()).toBe(false)
  expect(wrapper.get('#seller-product-form fieldset').attributes('disabled')).toBeUndefined()
  expect(wrapper.find('#seller-product-form button[type="submit"]').exists()).toBe(true)
  expect(wrapper.get('#product_images').attributes('disabled')).toBeUndefined()
  expect(wrapper.text()).toContain('Chỉ thay đổi tồn kho thì sản phẩm vẫn tiếp tục')
})
