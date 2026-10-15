import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { timeAgo } from '../composables/productPresentation'
import { brand, brandLine, sellerBrand, adminBrand, pageTitle, pageNames } from '../config/brand'
import router from '../router'
import PageHeader from '../components/ui/PageHeader.vue'
import BrandLogo from '../components/ui/BrandLogo.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ProductCard from '../components/ProductCard.vue'
import DefaultLayout from '../layouts/DefaultLayout.vue'
import ProductView from '../views/ProductView.vue'
import * as productApi from '../api/product.api'
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

it('listing card: time and photo count over the image, then price and a muted line with condition, shop and area', async () => {
  const { wrapper } = await setup(ProductCard, {
    props: { product: product({ images: [{ id: '1' }, { id: '2' }, { id: '3' }], shop: { id: '2', name: 'Tiệm Cũ', slug: 'tiem-cu', province_code: '79', province_name: 'TP. Hồ Chí Minh' } }) },
  })
  const meta = wrapper.get('.product-card-meta')
  expect(meta.findAll(':scope > *').map((node) => node.text())).toEqual(['Tốt', 'Tiệm Cũ', 'TP. Hồ Chí Minh'])
  expect(meta.get('a').attributes('href')).toBe('/shops/tiem-cu')
  const overlay = wrapper.get('.product-card-media .product-card-overlay')
  expect(overlay.get('time').text()).toBe('3 giờ trước')
  expect(overlay.get('time').attributes('datetime')).toBe('2026-10-13T09:00:00Z')
  expect(overlay.get('.product-card-photos').text()).toBe('3')
  expect(overlay.get('.product-card-photos').attributes('aria-label')).toBe('3 ảnh')
  expect(wrapper.get('.product-card-price').text()).toContain('1.500.000')
  expect(wrapper.get('h2 a').attributes('href')).toBe('/products/may-anh-phim')
  expect(wrapper.find('.product-card-badge').exists()).toBe(false)
  expect(wrapper.find('.product-card-rating').exists()).toBe(false)
  expect(wrapper.find('.product-card-reason').exists()).toBe(false)
  expect(wrapper.get('.product-card-favorite button').attributes('aria-label')).toBe('Thêm vào yêu thích: Máy ảnh phim')
  expect(wrapper.get('.product-card-favorite svg').attributes('aria-hidden')).toBe('true')

  const bare = await setup(ProductCard, { props: { product: product({ shop: null, published_at: null }) } })
  expect(bare.wrapper.get('.product-card-meta').text()).toBe('Tốt')
  expect(bare.wrapper.find('.product-card-overlay').exists()).toBe(false)
  // A shop without an area simply has no area segment.
  const noArea = await setup(ProductCard, { props: { product: product() } })
  expect(noArea.wrapper.find('.product-card-area').exists()).toBe(false)
})

it('listing card labels an auction on the image corner and keeps the link to the session', async () => {
  const { wrapper } = await setup(ProductCard, {
    props: { product: product({ auction: { id: '9', status: 'LIVE', current_price: '2000000.00' }, reason_label: 'Mới đăng gần đây' }) },
  })
  const badge = wrapper.get('.product-card-media .product-card-badge-auction')
  expect(wrapper.find('.product-card-badge-video').exists()).toBe(false)
  expect(badge.attributes('href')).toBe('/auctions/9')
  expect(badge.text()).toContain('Xem phiên')
  expect(wrapper.get('.product-card-price').text()).toContain('2.000.000')
  expect(wrapper.get('.product-card-reason').text()).toBe('Mới đăng gần đây')
})

it('logo, line icons and empty state use SVG with text labels and no emoji glyphs', () => {
  const logo = mount(BrandLogo)
  expect(logo.text()).toBe('passdi')
  expect(logo.get('.brand-logo-mark').attributes('aria-hidden')).toBe('true')
  for (const name of ['search', 'menu', 'plus', 'chevronDown', 'heart', 'star', 'box', 'play', 'gavel', 'tag']) {
    const icon = mount(AppIcon, { props: { name } })
    expect(icon.get('svg').attributes()).toMatchObject({ 'aria-hidden': 'true', fill: 'none', 'stroke-width': '1.6' })
    expect(icon.get('path').attributes('d')).toBeTruthy()
  }
  expect(mount(AppIcon, { props: { name: 'heart', filled: true } }).get('svg').attributes('fill')).toBe('currentColor')
  const empty = mount(EmptyState, { props: { title: 'Chưa có sản phẩm' } })
  expect(empty.get('.buyer-empty-icon svg').exists()).toBe(true)
  expect(empty.text()).toBe('Chưa có sản phẩm')
})

it('header shows the logo, the post button and no emoji glyphs', async () => {
  const { wrapper } = await setup(DefaultLayout, { path: '/products' })
  expect(wrapper.get('.marketplace-brand').text()).toBe('passdi')
  expect(wrapper.get('.marketplace-brand').attributes('href')).toBe('/')
  expect(wrapper.get('.marketplace-sell').text()).toBe('Đăng tin')
  expect(wrapper.get('[role="search"] button[type="submit"]').attributes('aria-label')).toBe('Tìm sản phẩm')
  expect(wrapper.get('header').text()).not.toMatch(/[↗＋⌄♥♡★☰▾]/)
})

it('listing card marks a video on the image and ends the meta line with a compact rating', async () => {
  const { wrapper } = await setup(ProductCard, {
    props: { product: product({ has_video: true, review_count: 12, average_rating: 4.75 }) },
  })
  expect(wrapper.get('.product-card-media .product-card-badge-video').text()).toBe('Có video')
  const parts = wrapper.get('.product-card-meta').findAll(':scope > *')
  expect(parts.map((node) => node.text())).toEqual(['Tốt', 'Tiệm Cũ', '4.8 (12)'])
  expect(parts.at(-1).attributes('aria-label')).toBe('4.8 trên 5, 12 đánh giá')
  expect(parts.at(-1).get('svg').attributes('aria-hidden')).toBe('true')

  for (const extra of [{ has_video: false }, { review_count: 0, average_rating: null }, {}]) {
    const plain = await setup(ProductCard, { props: { product: product(extra) } })
    expect(plain.wrapper.find('.product-card-badge-video').exists()).toBe(false)
    expect(plain.wrapper.find('.product-card-rating').exists()).toBe(false)
    expect(plain.wrapper.text()).not.toContain('(0)')
  }
})

it('brand config is the single source for the name, area labels and document titles', async () => {
  expect(brand).toMatchObject({ name: 'Passdi', slogan: 'Không dùng nữa? Passdi!' })
  expect(brandLine).toBe('Passdi · Không dùng nữa? Passdi!')
  expect(sellerBrand).toBe('Passdi · Kênh người bán')
  expect(adminBrand).toBe('Passdi · Quản trị')
  expect(pageTitle('Giỏ hàng')).toBe('Giỏ hàng · Passdi')
  expect(pageTitle('')).toBe(brandLine)
  expect(pageTitle(undefined)).toBe(brandLine)
  // Every named route has a Vietnamese document title.
  const names = router.getRoutes().map((route) => route.name)
  expect(names.filter((name) => !(name in pageNames))).toEqual([])
  expect(mount(BrandLogo).attributes('aria-label')).toBe(brand.name)
  expect(mount(PageHeader, { props: { title: 'Đơn mua' } }).find('.buyer-eyebrow').exists()).toBe(false)
  expect(mount(PageHeader, { props: { title: 'Đơn mua', eyebrow: brandLine } }).get('.buyer-eyebrow').text()).toBe(brandLine)
})

it('product detail seller card shows the shop rating and join month only when the API provides them', async () => {
  const detail = { ...product(), description: 'Mô tả', stock_quantity: 2, images: [] }
  const stubs = { ProductReviews: true, ProductVideos: true, AddToCart: true, ReportDialog: true, ChatSellerButton: true, ProductSuggestions: true }
  const mountDetail = async (shop) => {
    vi.spyOn(productApi, 'getProduct').mockResolvedValue({ ...detail, shop })
    const pinia = (await import('pinia')).createPinia()
    const auth = useAuthStore(pinia)
    auth.initialized = true
    const router = createRouter({
      history: createMemoryHistory(),
      routes: ['/products', '/products/:slug', '/shops/:slug', '/login'].map((path) => ({ path, component: { template: '<div />' } })),
    })
    await router.push('/products/may-anh-phim')
    const wrapper = mount(ProductView, { global: { plugins: [pinia, router], stubs } })
    wrappers.push(wrapper)
    await flushPromises()
    return wrapper
  }
  const rated = await mountDetail({ ...detail.shop, rating_avg: 4.25, rating_count: 8, joined_at: '2026-03-05T02:00:00Z' })
  const stats = rated.get('.product-seller-stats')
  expect(stats.text()).toContain('4.3')
  expect(stats.text()).toContain('(8)')
  expect(stats.text()).toMatch(/Tham gia .*3.*2026/)
  expect(stats.get('span').attributes('aria-label')).toContain('8 lượt')
  const fresh = await mountDetail({ ...detail.shop, rating_avg: null, rating_count: 0, joined_at: '2026-03-05T02:00:00Z' })
  expect(fresh.get('.product-seller-stats').text()).not.toContain('(0)')
  const bare = await mountDetail(detail.shop)
  expect(bare.find('.product-seller-stats').exists()).toBe(false)
  expect(bare.get('.buyer-shop-panel a').attributes('href')).toBe('/shops/tiem-cu')
})
