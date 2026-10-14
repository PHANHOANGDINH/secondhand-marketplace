import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import * as productApi from '../api/product.api'
import * as shopApi from '../api/shop.api'
import appRouter from '../router'
import MyAdsView from '../views/MyAdsView.vue'

const ad = (extra = {}) => ({
  id: '10',
  name: 'Máy ảnh cũ',
  price: '500000.00',
  stock_quantity: 3,
  inventory_state: 'IN_STOCK',
  auction_locked: false,
  auction: null,
  condition: 'GOOD',
  status: 'ACTIVE',
  can_edit: true,
  archived: false,
  shop_status: 'ACTIVE',
  created_at: '2026-10-01T01:00:00Z',
  primary_image: null,
  ...extra,
})
const collection = (data, last = 1) => ({ data, meta: { current_page: 1, last_page: last, total: data.length } })
const counts = { DRAFT: 3, PENDING: 1, ACTIVE: 4, SOLD: 0, HIDDEN: 1, REJECTED: 2, AUCTION: 1, ALL: 11 }
const wrappers = []
async function setup(path = '/my-ads') {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = { id: '1', name: 'Lan Anh', role: 'USER', status: 'ACTIVE' }
  auth.initialized = true
  const router = createRouter({
    history: createMemoryHistory(),
    routes: ['/', '/my-ads', '/seller/shop', '/seller/orders', '/seller/auctions', '/seller/vouchers', '/seller/products/create', '/seller/products/:id/edit'].map(
      (route) => ({ path: route, component: { template: '<div />' } }),
    ),
  })
  await router.push(path)
  const wrapper = mount(MyAdsView, { global: { plugins: [pinia, router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router }
}
beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(shopApi, 'getSellerShop').mockResolvedValue({ id: '5', name: 'Tiệm Lan Anh', status: 'ACTIVE' })
  vi.spyOn(productApi, 'getSellerProductCounts').mockResolvedValue(counts)
  vi.spyOn(productApi, 'listSellerProducts').mockResolvedValue(collection([ad()]))
})
afterEach(() => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount()
})

it('shows the breadcrumb, shortcuts, profile line and the seven counted tabs', async () => {
  const { wrapper } = await setup()
  expect(wrapper.get('.my-ads-breadcrumb').text()).toContain('Trang chủ')
  expect(wrapper.get('.my-ads-breadcrumb [aria-current="page"]').text()).toBe('Quản lý tin')
  expect(wrapper.findAll('.my-ads-shortcuts a').map((link) => [link.text(), link.attributes('href')])).toEqual([
    ['Voucher', '/seller/vouchers'],
    ['Đấu giá', '/seller/auctions'],
    ['Đơn bán', '/seller/orders'],
  ])
  expect(wrapper.get('.my-ads-who').text()).toContain('Lan Anh')
  expect(wrapper.get('.my-ads-shop').text()).toContain('Tiệm Lan Anh')
  expect(wrapper.find('[data-create-shop]').exists()).toBe(false)
  expect(wrapper.get('#my-ads-search').attributes('placeholder')).toBe('Tìm tin đăng của bạn…')
  expect(wrapper.findAll('[data-tab]').map((tab) => tab.text().replace(/\s+/g, ' '))).toEqual([
    'Đang hiển thị (4)',
    'Hết hàng (0)',
    'Bị gỡ (2)',
    'Tin nháp (3)',
    'Chờ xem xét (1)',
    'Đã ẩn (1)',
    'Đang đấu giá (1)',
  ])
  expect(wrapper.get('[data-tab="active"]').attributes('aria-pressed')).toBe('true')
  expect(productApi.listSellerProducts).toHaveBeenCalledWith({ status: 'ACTIVE', page: 1, search: undefined })
  expect(productApi.getSellerProductCounts).toHaveBeenCalledTimes(1)
  const row = wrapper.get('[data-product="10"]')
  expect(row.text()).toContain('Máy ảnh cũ')
  expect(row.text()).toContain('500.000 ₫')
  expect(row.text()).toContain('Đăng ngày')
})

it('keeps the tab, the search and the page in the URL and reloads from it', async () => {
  const { wrapper, router } = await setup('/my-ads?tab=draft&q=sony&page=2')
  expect(wrapper.get('[data-tab="draft"]').attributes('aria-pressed')).toBe('true')
  expect(wrapper.get('#my-ads-search').element.value).toBe('sony')
  expect(productApi.listSellerProducts).toHaveBeenCalledWith({ status: 'DRAFT', page: 2, search: 'sony' })
  // Past the last page: the view asks again for the real last page.
  expect(productApi.listSellerProducts).toHaveBeenLastCalledWith({ status: 'DRAFT', page: 1, search: 'sony' })
  await wrapper.get('[data-tab="auction"]').trigger('click')
  await flushPromises()
  expect(router.currentRoute.value.fullPath).toBe('/my-ads?tab=auction&q=sony')
  expect(productApi.listSellerProducts).toHaveBeenLastCalledWith({ auction: 1, page: 1, search: 'sony' })
  await wrapper.get('#my-ads-search').setValue('  ')
  await wrapper.get('form[role="search"]').trigger('submit')
  await flushPromises()
  expect(router.currentRoute.value.fullPath).toBe('/my-ads?tab=auction')
  // An unknown tab falls back to the first one instead of sending junk to the API.
  await router.push('/my-ads?tab=everything')
  await flushPromises()
  expect(wrapper.get('[data-tab="active"]').attributes('aria-pressed')).toBe('true')
  expect(productApi.listSellerProducts).toHaveBeenLastCalledWith({ status: 'ACTIVE', page: 1, search: undefined })
})

it('refreshes the list and the counters after an action, and still works when counters fail', async () => {
  vi.spyOn(productApi, 'productAction').mockResolvedValue(ad({ status: 'HIDDEN' }))
  const { wrapper } = await setup()
  await wrapper.get('[data-product="10"] [data-action="hide"]').trigger('click')
  await flushPromises()
  expect(productApi.productAction).toHaveBeenCalledWith('10', 'hide')
  expect(productApi.listSellerProducts).toHaveBeenCalledTimes(2)
  expect(productApi.getSellerProductCounts).toHaveBeenCalledTimes(2)

  productApi.getSellerProductCounts.mockRejectedValue(new Error('offline'))
  const plain = await setup()
  expect(plain.wrapper.find('.my-ads-count').exists()).toBe(false)
  expect(plain.wrapper.findAll('[data-tab]')).toHaveLength(7)
  expect(plain.wrapper.find('[role="alert"]').exists()).toBe(false)
  expect(plain.wrapper.find('[data-product="10"]').exists()).toBe(true)
})

it('an empty tab shows the drawing, the message and a "Đăng tin" button', async () => {
  productApi.listSellerProducts.mockResolvedValue(collection([]))
  const { wrapper } = await setup('/my-ads?tab=hidden')
  const empty = wrapper.get('.my-ads-empty')
  expect(empty.find('svg').exists()).toBe(true)
  expect(empty.get('h2').text()).toBe('Không tìm thấy tin đăng')
  expect(empty.text()).toContain('Đã ẩn')
  expect(empty.get('[data-post]').text()).toBe('Đăng tin')
  expect(empty.get('[data-post]').attributes('href')).toBe('/seller/products/create')
})

it('a user without a shop is offered "+ Tạo cửa hàng" and no product request is made', async () => {
  shopApi.getSellerShop.mockRejectedValue({ response: { status: 404 } })
  const { wrapper } = await setup()
  expect(wrapper.get('[data-create-shop]').text()).toBe('+ Tạo cửa hàng')
  expect(wrapper.get('[data-create-shop]').attributes('href')).toBe('/seller/shop')
  expect(productApi.listSellerProducts).not.toHaveBeenCalled()
  expect(productApi.getSellerProductCounts).not.toHaveBeenCalled()
  expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  expect(wrapper.get('.my-ads-empty [data-post]').attributes('href')).toBe('/seller/shop')
})

it('the old seller product list address redirects to /my-ads and keeps its query', () => {
  const old = appRouter.resolve('/seller/products?tab=draft')
  expect(old.matched[0].redirect(old)).toEqual({ path: '/my-ads', query: { tab: 'draft' } })
  expect(appRouter.resolve('/seller/products/create').name).toBe('seller-product-create')
  expect(appRouter.resolve('/seller/products/7/edit').name).toBe('seller-product-edit')
  expect(appRouter.resolve('/my-ads').meta.requiresAuth).toBe(true)
})
