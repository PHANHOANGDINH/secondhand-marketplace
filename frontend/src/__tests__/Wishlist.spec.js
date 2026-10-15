import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useWishlistStore } from '../stores/wishlist'
import { createAuthGuard } from '../router/authGuard'
import * as api from '../api/wishlist.api'
import FavoriteButton from '../components/FavoriteButton.vue'
import DefaultLayout from '../layouts/DefaultLayout.vue'
vi.mock('../composables/useNotifications', async importOriginal => ({ ...await importOriginal(), provideNotifications: vi.fn() }))
vi.mock('../composables/useMessaging', async importOriginal => ({ ...await importOriginal(), provideMessaging: vi.fn() }))
import ProductCard from '../components/ProductCard.vue'
import WishlistView from '../views/WishlistView.vue'
const product = {
  id: '5',
  name: 'Điện thoại cũ',
  slug: 'phone',
  price: '100.00',
  condition: 'GOOD',
  is_wishlisted: false,
}
const collection = (data) => ({ data, meta: { current_page: 1, last_page: 1, total: data.length } })
const wrappers = []
async function setup(component = FavoriteButton, props = { product }, guest = false) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = guest ? null : { id: '1', role: 'USER', status: 'ACTIVE' }
  auth.initialized = true
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/products', name: 'products', component: { template: '<div />' } },
      { path: '/products/:slug', name: 'product', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
      {
        path: '/wishlist',
        name: 'wishlist',
        meta: { requiresAuth: true },
        component: { template: '<div />' },
      },
      { path: '/auctions/:id', name: 'auction', component: { template: '<div />' } },
    ],
  })
  router.beforeEach(createAuthGuard(auth))
  await router.push('/products?q=phone&has_video=1')
  const wrapper = mount(component, { props, global: { plugins: [pinia, router], stubs: { NotificationBell: true, ChatDrawer: true, ChatLauncher: true, CartLink: true, LogoutButton: true, OutbidToasts: true } } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, auth, router, wishlist: useWishlistStore(pinia) }
}
beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(api, 'saveFavorite').mockResolvedValue({ data: { is_wishlisted: true } })
  vi.spyOn(api, 'removeFavorite').mockResolvedValue({ data: { is_wishlisted: false } })
  vi.spyOn(api, 'listWishlist').mockResolvedValue(collection([]))
})
afterEach(() => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount()
})
it('uses backend state, exposes Vietnamese accessible heart state, adds and removes', async () => {
  const { wrapper } = await setup()
  expect(wrapper.get('button').attributes('aria-pressed')).toBe('false')
  expect(wrapper.get('button').attributes('aria-label')).toContain(
    'Thêm vào yêu thích: Điện thoại cũ',
  )
  await wrapper.get('button').trigger('click')
  await flushPromises()
  expect(api.saveFavorite).toHaveBeenCalledWith('5')
  expect(wrapper.get('button').attributes('aria-pressed')).toBe('true')
  await wrapper.get('button').trigger('click')
  await flushPromises()
  expect(api.removeFavorite).toHaveBeenCalledWith('5')
  expect(wrapper.get('button').attributes('aria-pressed')).toBe('false')
})
it('does not change confirmed state on failure and prevents duplicate in-flight requests', async () => {
  let reject
  api.saveFavorite.mockImplementation(
    () =>
      new Promise((_, r) => {
        reject = r
      }),
  )
  const { wrapper } = await setup()
  await wrapper.get('button').trigger('click')
  await wrapper.get('button').trigger('click')
  expect(api.saveFavorite).toHaveBeenCalledTimes(1)
  expect(wrapper.get('button').attributes('disabled')).toBeDefined()
  reject(new Error('SQL secret'))
  await flushPromises()
  expect(wrapper.get('button').attributes('aria-pressed')).toBe('false')
  expect(wrapper.get('[role=alert]').text()).not.toContain('SQL secret')
})
it('redirects guests to login with the safe current search destination', async () => {
  const { wrapper, router } = await setup(FavoriteButton, { product }, true)
  await wrapper.get('button').trigger('click')
  await flushPromises()
  expect(router.currentRoute.value.name).toBe('login')
  expect(router.currentRoute.value.query.redirect).toBe('/products?q=phone&has_video=1')
  expect(api.saveFavorite).not.toHaveBeenCalled()
})
it('protects the wishlist route with the existing auth guard', async () => {
  const { router } = await setup(FavoriteButton, { product }, true)
  await router.push('/wishlist')
  expect(router.currentRoute.value.name).toBe('login')
  expect(router.currentRoute.value.query.redirect).toBe('/wishlist')
})
it('shares confirmed state across card controls without per-card reads or link interference', async () => {
  const Pair = {
    components: { ProductCard },
    data: () => ({ product }),
    template: '<ProductCard :product="product" /><ProductCard :product="product" />',
  }
  const { wrapper, router } = await setup(Pair, {})
  await wrapper.findAll('button')[0].trigger('click')
  await flushPromises()
  expect(wrapper.findAll('button').map((b) => b.attributes('aria-pressed'))).toEqual([
    'true',
    'true',
  ])
  expect(api.listWishlist).not.toHaveBeenCalled()
  expect(router.currentRoute.value.path).toBe('/products')
  expect(wrapper.get('a[href="/products/phone"]').exists()).toBe(true)
})
it('clears account state and ignores a mutation response after logout or account switch', async () => {
  let resolve
  api.saveFavorite.mockImplementation(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  const { wrapper, auth, wishlist } = await setup()
  await wrapper.get('button').trigger('click')
  auth.user = null
  auth.user = { id: '2', role: 'USER', status: 'ACTIVE' }
  await flushPromises()
  resolve({ data: { is_wishlisted: true } })
  await flushPromises()
  expect(wishlist.states['5']).toBeUndefined()
  expect(wrapper.get('button').attributes('aria-pressed')).toBe('false')
})
it('shows wishlist empty, safe error and retry states without conflicting messages', async () => {
  const { wrapper } = await setup(WishlistView, {})
  expect(wrapper.text()).toContain('Bạn chưa lưu sản phẩm nào.')
  expect(wrapper.get('a[href="/products"]').text()).toBe('Khám phá sản phẩm')
  api.listWishlist.mockRejectedValue(new Error('private stack'))
  await wrapper.get('button').trigger('click')
  await flushPromises()
  expect(wrapper.text()).not.toContain('Bạn chưa lưu sản phẩm nào.')
  expect(wrapper.text()).not.toContain('private stack')
  api.listWishlist.mockResolvedValue(
    collection([
      {
        ...product,
        is_wishlisted: true,
        auction: { id: '7', status: 'LIVE', current_price: '120.00' },
      },
    ]),
  )
  await wrapper.get('button').trigger('click')
  await flushPromises()
  expect(wrapper.get('a[href="/auctions/7"]').text()).toContain('Xem phiên')
  expect(wrapper.get('[aria-pressed=true]').exists()).toBe(true)
})
it('shows the loading skeleton and reloads the list after successful removal', async () => {
  let resolve
  api.listWishlist.mockImplementationOnce(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  const { wrapper } = await setup(WishlistView, {})
  expect(wrapper.find('[aria-busy=true]').exists()).toBe(true)
  resolve(collection([{ ...product, is_wishlisted: true }]))
  await flushPromises()
  api.listWishlist.mockResolvedValue(collection([]))
  await wrapper.get('[aria-pressed=true]').trigger('click')
  await flushPromises()
  expect(wrapper.text()).toContain('Bạn chưa lưu sản phẩm nào.')
})

it('exposes the wishlist account-menu entry only for authenticated users', async () => {
  const { wrapper, auth } = await setup(DefaultLayout, {})
  expect(wrapper.get('.marketplace-account-links a[href="/wishlist"]').text()).toBe('Sản phẩm yêu thích')
  auth.user = null; await flushPromises()
  expect(wrapper.find('a[href="/wishlist"]').exists()).toBe(false)
})
