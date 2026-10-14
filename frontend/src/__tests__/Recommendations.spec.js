import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import * as api from '../api/recommendation.api'
import * as productApi from '../api/product.api'
import domainApi from '../api/axios'
import ProductSuggestions from '../components/ProductSuggestions.vue'
import ProductCard from '../components/ProductCard.vue'
import HomeView from '../views/HomeView.vue'
import ProductView from '../views/ProductView.vue'

const item = (id, extra = {}) => ({
  id: String(id),
  name: `Sản phẩm ${id}`,
  slug: `san-pham-${id}`,
  price: '100000.00',
  condition: 'GOOD',
  is_wishlisted: false,
  ...extra,
})
const wrappers = []
async function setup(component, props = {}, { guest = false, path = '/', stubs = {} } = {}) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = guest ? null : { id: '1', role: 'USER', status: 'ACTIVE' }
  auth.initialized = true
  const router = createRouter({
    history: createMemoryHistory(),
    routes: ['/', '/products', '/products/:slug', '/shops/:slug', '/login', '/auctions/:id'].map(
      (route) => ({ path: route, component: { template: '<div />' } }),
    ),
  })
  await router.push(path)
  const wrapper = mount(component, { props, global: { plugins: [pinia, router], stubs } })
  wrappers.push(wrapper)
  return { wrapper, auth, router }
}
beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(api, 'listRecommendations').mockResolvedValue([])
  vi.spyOn(api, 'listSimilarProducts').mockResolvedValue([])
})
afterEach(() => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount()
})

it('shows a skeleton while loading, then cards with their Vietnamese reason', async () => {
  let resolve
  api.listRecommendations.mockReturnValue(new Promise((done) => (resolve = done)))
  const { wrapper } = await setup(ProductSuggestions, { title: 'Gợi ý cho bạn' })
  await flushPromises()
  expect(api.listRecommendations).toHaveBeenCalledWith(12)
  expect(wrapper.get('section').attributes('aria-busy')).toBe('true')
  expect(wrapper.get('[role="status"]').text()).toContain('Đang tải gợi ý cho bạn')
  expect(wrapper.findAll('.ui-skeleton').length).toBeGreaterThan(0)
  expect(wrapper.findAll('[data-product]')).toHaveLength(0)

  resolve([item(1, { reason_label: 'Vì bạn quan tâm Điện thoại' }), item(2, { reason_label: 'Mới đăng gần đây' })])
  await flushPromises()
  expect(wrapper.find('[role="status"]').exists()).toBe(false)
  expect(wrapper.get('h2').text()).toBe('Gợi ý cho bạn')
  expect(wrapper.get('section').attributes('aria-labelledby')).toBe(wrapper.get('h2').attributes('id'))
  expect(wrapper.findAll('[data-product]').map((card) => card.attributes('data-product'))).toEqual(['1', '2'])
  expect(wrapper.findAll('.product-card-reason').map((reason) => reason.text())).toEqual([
    'Vì bạn quan tâm Điện thoại',
    'Mới đăng gần đây',
  ])
})

it('hides the whole section when there is nothing to suggest or the request fails', async () => {
  const empty = await setup(ProductSuggestions, { title: 'Gợi ý cho bạn' })
  await flushPromises()
  expect(empty.wrapper.find('section').exists()).toBe(false)
  expect(empty.wrapper.text()).toBe('')

  api.listRecommendations.mockRejectedValue(new Error('offline'))
  const failed = await setup(ProductSuggestions, { title: 'Gợi ý cho bạn' })
  await flushPromises()
  expect(failed.wrapper.find('section').exists()).toBe(false)
  expect(failed.wrapper.find('[role="alert"]').exists()).toBe(false)
})

it('loads similar products for a slug and reloads when the product changes', async () => {
  api.listSimilarProducts.mockResolvedValueOnce([item(3)]).mockResolvedValueOnce([item(4), item(5)])
  const { wrapper } = await setup(ProductSuggestions, { title: 'Sản phẩm tương tự', slug: 'may-a', limit: 8 })
  await flushPromises()
  expect(api.listSimilarProducts).toHaveBeenCalledWith('may-a', 8)
  expect(api.listRecommendations).not.toHaveBeenCalled()
  expect(wrapper.findAll('[data-product]')).toHaveLength(1)
  expect(wrapper.find('.product-card-reason').exists()).toBe(false)
  await wrapper.setProps({ slug: 'may-b' })
  await flushPromises()
  expect(api.listSimilarProducts).toHaveBeenLastCalledWith('may-b', 8)
  expect(wrapper.findAll('[data-product]')).toHaveLength(2)
})

it('asks again when the signed-in user changes so one user never keeps another user’s list', async () => {
  api.listRecommendations.mockResolvedValueOnce([item(1)]).mockResolvedValueOnce([item(9)])
  const { wrapper, auth } = await setup(ProductSuggestions, { title: 'Gợi ý cho bạn' }, { guest: true })
  await flushPromises()
  expect(wrapper.get('[data-product]').attributes('data-product')).toBe('1')
  auth.user = { id: '7', role: 'USER', status: 'ACTIVE' }
  await flushPromises()
  expect(api.listRecommendations).toHaveBeenCalledTimes(2)
  expect(wrapper.get('[data-product]').attributes('data-product')).toBe('9')
})

it('ignores a slow earlier answer that arrives after a newer request', async () => {
  let slow
  api.listSimilarProducts
    .mockReturnValueOnce(new Promise((done) => (slow = done)))
    .mockResolvedValueOnce([item(6)])
  const { wrapper } = await setup(ProductSuggestions, { title: 'Sản phẩm tương tự', slug: 'cu' })
  await wrapper.setProps({ slug: 'moi' })
  await flushPromises()
  slow([item(1), item(2)])
  await flushPromises()
  expect(wrapper.findAll('[data-product]').map((card) => card.attributes('data-product'))).toEqual(['6'])
})

it('product card shows the reason only when the API sends one', async () => {
  const withReason = await setup(ProductCard, { product: item(1, { reason_label: 'Được đánh giá cao' }) })
  expect(withReason.wrapper.get('.product-card-reason').text()).toBe('Được đánh giá cao')
  const plain = await setup(ProductCard, { product: item(2) })
  expect(plain.wrapper.find('.product-card-reason').exists()).toBe(false)
})

it('home page opens on the "Dành cho bạn" tab with recommendations, and product detail has "Sản phẩm tương tự"', async () => {
  vi.spyOn(domainApi, 'get').mockResolvedValue({ data: { status: 'ok' } })
  api.listRecommendations.mockResolvedValue([item(1, { reason_label: 'Mới đăng gần đây' })])
  vi.spyOn(productApi, 'listProducts').mockResolvedValue({ data: [item(7), item(8)] })
  const home = await setup(HomeView, {}, { stubs: { HomeDiscovery: true, HomeHero: true } })
  await flushPromises()
  expect(home.wrapper.findAll('[data-home-tab]').map((tab) => tab.text())).toEqual(['Dành cho bạn', 'Mới nhất', 'Video', 'Đấu giá'])
  expect(home.wrapper.get('[data-home-tab="foryou"]').attributes('aria-pressed')).toBe('true')
  expect(api.listRecommendations).toHaveBeenCalledWith(12, {})
  expect(home.wrapper.findAll('[data-product]')).toHaveLength(1)
  expect(home.wrapper.get('.product-card-reason').text()).toBe('Mới đăng gần đây')
  await home.wrapper.get('[data-home-tab="latest"]').trigger('click')
  await flushPromises()
  expect(productApi.listProducts).toHaveBeenLastCalledWith({ sort: 'newest', per_page: 12 })
  expect(home.wrapper.findAll('[data-product]')).toHaveLength(2)
  expect(home.wrapper.get('.home-more a').attributes('href')).toBe('/products?sort=newest')
  await home.wrapper.get('[data-home-tab="video"]').trigger('click')
  await flushPromises()
  expect(home.wrapper.find('[data-product]').exists()).toBe(false)
  expect(home.wrapper.findComponent({ name: 'HomeDiscovery' }).exists()).toBe(true)

  vi.spyOn(productApi, 'getProduct').mockResolvedValue({
    ...item(10, { slug: 'may-a' }),
    description: 'Mô tả',
    stock_quantity: 1,
    images: [],
  })
  api.listSimilarProducts.mockResolvedValue([item(11), item(12)])
  const detail = await setup(ProductView, {}, {
    path: '/products/may-a',
    stubs: { ProductReviews: true, ProductVideos: true, AddToCart: true, ReportDialog: true, ChatSellerButton: true },
  })
  await flushPromises()
  expect(api.listSimilarProducts).toHaveBeenCalledWith('may-a', 8)
  expect(detail.wrapper.get('#similar-products-title').text()).toBe('Sản phẩm tương tự')
  expect(detail.wrapper.findAll('.product-suggestions [data-product]')).toHaveLength(2)

  api.listSimilarProducts.mockResolvedValue([])
  const lonely = await setup(ProductView, {}, {
    path: '/products/may-a',
    stubs: { ProductReviews: true, ProductVideos: true, AddToCart: true, ReportDialog: true, ChatSellerButton: true },
  })
  await flushPromises()
  expect(lonely.wrapper.find('#similar-products-title').exists()).toBe(false)
})
