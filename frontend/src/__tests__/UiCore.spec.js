import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import * as categoryApi from '../api/category.api'
import { timeAgo } from '../composables/productPresentation'
import BrandLogo from '../components/ui/BrandLogo.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ProductCard from '../components/ProductCard.vue'
import DefaultLayout from '../layouts/DefaultLayout.vue'
vi.mock('../composables/useNotifications', async (importOriginal) => ({
  ...(await importOriginal()),
  provideNotifications: vi.fn(),
}))
vi.mock('../composables/useMessaging', async (importOriginal) => ({
  ...(await importOriginal()),
  provideMessaging: vi.fn(),
}))

const NOW = Date.parse('2026-10-13T12:00:00Z')
const product = (extra = {}) => ({
  id: '5',
  name: 'Máy ảnh phim',
  slug: 'may-anh-phim',
  price: '1500000.00',
  condition: 'GOOD',
  is_wishlisted: false,
  shop: { id: '2', name: 'Tiệm Cũ', slug: 'tiem-cu' },
  published_at: '2026-10-13T09:00:00Z',
  ...extra,
})
const wrappers = []
async function setup(component, { props = {}, path = '/', slots = {} } = {}) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = null
  auth.initialized = true
  const router = createRouter({
    history: createMemoryHistory(),
    routes: ['/', '/products', '/products/:slug', '/shops/:slug', '/auctions/:id', '/login', '/seller/shop', '/admin'].map(
      (route) => ({ path: route, component: { template: '<div />' } }),
    ),
  })
  await router.push(path)
  const wrapper = mount(component, {
    props,
    slots,
    global: {
      plugins: [pinia, router],
      stubs: { NotificationBell: true, ChatDrawer: true, ChatLauncher: true, CartLink: true, OutbidToasts: true, AdminShell: true, SellerShell: true },
    },
  })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router }
}
beforeEach(() => {
  vi.restoreAllMocks()
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(NOW)
})
afterEach(() => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount()
  vi.useRealTimers()
})

it('formats how long ago a listing was published, in Vietnamese', () => {
  const ago = (minutes) => timeAgo(new Date(NOW - minutes * 60000).toISOString(), NOW)
  expect(ago(0)).toBe('Vừa đăng')
  expect(ago(59)).toBe('59 phút trước')
  expect(ago(60)).toBe('1 giờ trước')
  expect(ago(23 * 60 + 59)).toBe('23 giờ trước')
  expect(ago(24 * 60)).toBe('1 ngày trước')
  expect(ago(45 * 1440)).toBe('1 tháng trước')
  expect(ago(800 * 1440)).toBe('2 năm trước')
  // A clock that is slightly behind the server never shows a negative age.
  expect(ago(-5)).toBe('Vừa đăng')
  for (const missing of [null, undefined, '', 'not-a-date']) expect(timeAgo(missing, NOW)).toBe('')
})

it('listing card shows price, then one muted line: condition, shop, time since publishing', async () => {
  const { wrapper } = await setup(ProductCard, { props: { product: product() } })
  const meta = wrapper.get('.product-card-meta')
  expect(meta.findAll(':scope > *').map((node) => node.text())).toEqual(['Tốt', 'Tiệm Cũ', '3 giờ trước'])
  expect(meta.get('a').attributes('href')).toBe('/shops/tiem-cu')
  expect(meta.get('time').attributes('datetime')).toBe('2026-10-13T09:00:00Z')
  expect(wrapper.get('.product-card-price').text()).toContain('1.500.000')
  expect(wrapper.get('h2 a').attributes('href')).toBe('/products/may-anh-phim')
  expect(wrapper.find('.product-card-badge').exists()).toBe(false)
  expect(wrapper.find('.product-card-reason').exists()).toBe(false)
  expect(wrapper.get('.product-card-favorite button').attributes('aria-label')).toBe('Thêm vào yêu thích: Máy ảnh phim')
  expect(wrapper.get('.product-card-favorite svg').attributes('aria-hidden')).toBe('true')

  const bare = await setup(ProductCard, { props: { product: product({ shop: null, published_at: null }) } })
  expect(bare.wrapper.get('.product-card-meta').text()).toBe('Tốt')
})

it('listing card labels an auction on the image corner and keeps the link to the session', async () => {
  const { wrapper } = await setup(ProductCard, {
    props: { product: product({ auction: { id: '9', status: 'LIVE', current_price: '2000000.00' }, reason_label: 'Mới đăng gần đây' }) },
  })
  const badge = wrapper.get('.product-card-media .product-card-badge')
  expect(badge.attributes('href')).toBe('/auctions/9')
  expect(badge.text()).toContain('Xem phiên')
  expect(wrapper.get('.product-card-price').text()).toContain('2.000.000')
  expect(wrapper.get('.product-card-reason').text()).toBe('Mới đăng gần đây')
})

it('interim logo, line icons and empty state use SVG with text labels and no emoji glyphs', () => {
  const logo = mount(BrandLogo)
  expect(logo.text()).toBe('pass lại')
  expect(logo.get('.brand-logo-mark').attributes('aria-hidden')).toBe('true')
  expect(logo.get('svg path').attributes('stroke')).toBe('var(--accent)')
  for (const name of ['search', 'menu', 'plus', 'chevronDown', 'heart', 'star', 'box', 'gavel', 'tag']) {
    const icon = mount(AppIcon, { props: { name } })
    expect(icon.get('svg').attributes()).toMatchObject({ 'aria-hidden': 'true', fill: 'none', 'stroke-width': '1.6' })
    expect(icon.get('path').attributes('d')).toBeTruthy()
  }
  expect(mount(AppIcon, { props: { name: 'heart', filled: true } }).get('svg').attributes('fill')).toBe('currentColor')
  const empty = mount(EmptyState, { props: { title: 'Chưa có sản phẩm' } })
  expect(empty.get('.buyer-empty-icon svg').exists()).toBe(true)
  expect(empty.text()).toBe('Chưa có sản phẩm')
})

it('header shows the logo and a category row that links into the product list', async () => {
  vi.spyOn(categoryApi, 'listCategories').mockResolvedValue({
    data: [
      { id: '3', name: 'Điện thoại', slug: 'dien-thoai' },
      { id: '4', name: 'Sách', slug: 'sach' },
    ],
  })
  const { wrapper } = await setup(DefaultLayout)
  expect(categoryApi.listCategories).toHaveBeenCalledTimes(1)
  expect(wrapper.get('.marketplace-brand').text()).toBe('pass lại')
  expect(wrapper.get('.marketplace-brand').attributes('href')).toBe('/')
  const row = wrapper.get('nav[aria-label="Danh mục"]')
  expect(row.findAll('a').map((link) => [link.text(), link.attributes('href')])).toEqual([
    ['Tất cả', '/products'],
    ['Điện thoại', '/products?category=3'],
    ['Sách', '/products?category=4'],
  ])
  expect(wrapper.get('.marketplace-sell').text()).toBe('Đăng bán nhanh')
  expect(wrapper.get('[role="search"] button').attributes('aria-label')).toBe('Tìm sản phẩm')
  expect(wrapper.get('header').text()).not.toMatch(/[↗＋⌄♥♡★]/)
})

it('category row is absent when categories cannot be loaded and in the seller and admin areas', async () => {
  vi.spyOn(categoryApi, 'listCategories').mockRejectedValue(new Error('offline'))
  const failed = await setup(DefaultLayout)
  expect(failed.wrapper.find('nav[aria-label="Danh mục"]').exists()).toBe(false)
  expect(failed.wrapper.find('header').exists()).toBe(true)

  categoryApi.listCategories.mockResolvedValue({ data: [{ id: '3', name: 'Điện thoại', slug: 'dien-thoai' }] })
  for (const path of ['/seller/shop', '/admin']) {
    const area = await setup(DefaultLayout, { path })
    expect(area.wrapper.find('nav[aria-label="Danh mục"]').exists()).toBe(false)
  }
})
