import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia, disposePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useCartStore } from '../stores/cart'
import { createAuthGuard } from '../router/authGuard'
import * as cartApi from '../api/cart.api'
import api from '../api/axios'
import CartView from '../views/CartView.vue'
import AddToCart from '../components/AddToCart.vue'
import CartLink from '../components/CartLink.vue'
const a = {
  id: '11',
  quantity: 2,
  unit_price: '10.25',
  subtotal: '20.50',
  available: true,
  unavailable_reason: null,
  max_quantity: 5,
  product: { id: '1', name: 'Phone', slug: 'phone', is_public: true },
  shop: { id: '8', name: 'Shop A', slug: 'shop-a' },
  primary_image: { url: '/storage/products/1/a.png' },
}
const b = {
  ...a,
  id: '12',
  quantity: 3,
  unit_price: '0.10',
  subtotal: '0.30',
  product: { id: '2', name: 'Book', slug: 'book', is_public: true },
  shop: { id: '9', name: 'Shop B', slug: 'shop-b' },
  primary_image: null,
}
const base = {
  id: '7',
  items: [a, b],
  item_count: 2,
  total_quantity: 5,
  subtotal: '20.80',
  available_subtotal: '20.80',
  has_unavailable_items: false,
}
const empty = {
  ...base,
  items: [],
  item_count: 0,
  total_quantity: 0,
  subtotal: '0.00',
  available_subtotal: '0.00',
}
const wrappers = [],
  piniaInstances = []
function stores(guest = false) {
  const pinia = createPinia()
  piniaInstances.push(pinia)
  setActivePinia(pinia)
  const auth = useAuthStore(pinia)
  auth.initialized = true
  auth.user = guest ? null : { id: '4', role: 'USER', status: 'ACTIVE' }
  return { pinia, auth, cart: useCartStore(pinia) }
}
async function setup(component, { guest = false, props = {} } = {}) {
  const state = stores(guest)
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div />' } },
      { path: '/cart', name: 'cart', component: { template: '<div />' } },
      { path: '/checkout', name: 'checkout', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
      { path: '/products', component: { template: '<div />' } },
      { path: '/products/:slug', component: { template: '<div />' } },
    ],
  })
  await router.push('/cart')
  const wrapper = mount(component, { props, global: { plugins: [state.pinia, router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { ...state, wrapper, router }
}
const button = (wrapper, text) => wrapper.findAll('button').find((node) => node.text() === text)
const product = { id: '1', slug: 'phone', stock_quantity: 5 }
it('routes auction-reserved products to the arena without submitting a normal cart write', async () => {
  const { wrapper } = await setup(AddToCart, {
    props: { product: { ...product, auction: { id: '77', status: 'LIVE' } } },
  })
  expect(wrapper.find('form').exists()).toBe(false)
  expect(wrapper.get('a').attributes('href')).toBe('/auctions/77')
  expect(wrapper.text()).toContain('Xem phiên đấu giá')
  expect(cartApi.addCartItem).not.toHaveBeenCalled()
})
beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(cartApi, 'getCart').mockResolvedValue(structuredClone(base))
  vi.spyOn(cartApi, 'addCartItem').mockResolvedValue(a)
  vi.spyOn(cartApi, 'updateCartItem').mockResolvedValue(a)
  vi.spyOn(cartApi, 'removeCartItem').mockResolvedValue(undefined)
  vi.spyOn(cartApi, 'clearCart').mockResolvedValue(undefined)
})
afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  piniaInstances.splice(0).forEach(disposePinia)
})
it('loads current cart and renders multiple shop groups, prices, images and decimal totals', async () => {
  const { wrapper } = await setup(CartView)
  expect(cartApi.getCart).toHaveBeenCalledOnce()
  expect(wrapper.findAll('[data-shop]')).toHaveLength(2)
  expect(wrapper.get('[data-shop="8"]').text()).toContain('Phone')
  expect(wrapper.get('[data-shop="9"]').text()).toContain('Book')
  expect(wrapper.get('[data-cart-total]').text()).toContain('20,80 ₫')
  expect(wrapper.get('[data-cart-item="11"] img').attributes('src')).toBe(a.primary_image.url)
  expect(wrapper.get('[data-cart-item="12"] img').attributes('src')).toBe(
    '/product-placeholder.svg',
  )
  expect(wrapper.find('[href*="checkout"]').exists()).toBe(true)
})
it('makes the add-to-cart action visibly unavailable when backend stock is zero', async () => {
  const { wrapper } = await setup(AddToCart, {
    props: { product: { ...product, stock_quantity: 0 } },
  })
  expect(wrapper.get('button[type="submit"]').element.disabled).toBe(true)
  expect(wrapper.get('button[type="submit"]').text()).toBe('Tạm hết hàng')
  expect(wrapper.get('input[name="quantity"]').element.disabled).toBe(true)
  expect(cartApi.addCartItem).not.toHaveBeenCalled()
})
it('updates absolute quantity and takes new totals from server', async () => {
  const { wrapper, cart } = await setup(CartView)
  cartApi.getCart.mockResolvedValue({
    ...base,
    items: [{ ...a, quantity: 1, subtotal: '10.25' }, b],
    total_quantity: 4,
    subtotal: '10.55',
  })
  await wrapper.get('#quantity-11').setValue('1')
  await wrapper.get('[data-cart-item="11"] form').trigger('submit')
  await flushPromises()
  expect(cartApi.updateCartItem).toHaveBeenCalledWith('11', 1)
  expect(wrapper.get('[data-cart-total]').text()).toContain('10,55 ₫')
  expect(cart.count).toBe(4)
})
it('removes a line and reloads authoritative cart state', async () => {
  const { wrapper } = await setup(CartView)
  cartApi.getCart.mockResolvedValue({ ...base, items: [b], subtotal: '0.30', total_quantity: 3 })
  await button(wrapper.get('[data-cart-item="11"]'), 'Xóa').trigger('click')
  await flushPromises()
  expect(cartApi.removeCartItem).toHaveBeenCalledWith('11')
  expect(wrapper.find('[data-cart-item="11"]').exists()).toBe(false)
})
it('clears cart and shows an empty state and zero badge quantity', async () => {
  const { wrapper, cart } = await setup(CartView)
  cartApi.getCart.mockResolvedValue(empty)
  await button(wrapper, 'Xóa toàn bộ giỏ').trigger('click')
  await flushPromises()
  expect(cartApi.clearCart).toHaveBeenCalledOnce()
  expect(wrapper.text()).toContain('Giỏ hàng đang trống')
  expect(cart.count).toBe(0)
})
it('keeps unavailable lines visible with warnings and separate available subtotal', async () => {
  cartApi.getCart.mockResolvedValue({
    ...base,
    items: [
      {
        ...a,
        available: false,
        max_quantity: 0,
        unavailable_reason: 'PRODUCT_UNAVAILABLE',
        product: { ...a.product, is_public: false },
      },
      b,
    ],
    has_unavailable_items: true,
    available_subtotal: '0.30',
  })
  const { wrapper } = await setup(CartView)
  const row = wrapper.get('[data-cart-item="11"]')
  expect(row.text()).toContain('không còn khả dụng')
  expect(row.get('input').attributes()).toHaveProperty('disabled')
  expect(row.find('a').exists()).toBe(false)
  expect(button(row, 'Xóa').attributes()).not.toHaveProperty('disabled')
  expect(wrapper.get('[data-cart-total]').text()).toContain('20,80 ₫')
  expect(wrapper.get('[data-available-total]').text()).toContain('0,30 ₫')
})
it('permits reducing quantity after stock falls and displays validation errors', async () => {
  cartApi.getCart.mockResolvedValue({
    ...base,
    items: [{ ...a, available: false, unavailable_reason: 'INSUFFICIENT_STOCK', max_quantity: 1 }],
  })
  const { wrapper } = await setup(CartView)
  expect(wrapper.get('#quantity-11').attributes()).not.toHaveProperty('disabled')
  expect(wrapper.text()).toContain('Hãy giảm số lượng')
  cartApi.updateCartItem.mockRejectedValue({
    response: { status: 422, data: { errors: { quantity: ['Quantity must be an integer'] } } },
  })
  await wrapper.get('[data-cart-item="11"] form').trigger('submit')
  await flushPromises()
  expect(wrapper.text()).toContain('Số lượng không hợp lệ')
})
it('displays a load error and supports explicit retry', async () => {
  cartApi.getCart.mockRejectedValueOnce(new Error('offline'))
  const { wrapper } = await setup(CartView)
  expect(wrapper.text()).toContain('kết nối')
  await button(wrapper, 'Tải lại giỏ hàng').trigger('click')
  await flushPromises()
  expect(wrapper.findAll('[data-cart-item]')).toHaveLength(2)
})
it('guest add-to-cart navigates to login without sending a mutation', async () => {
  const { wrapper, router } = await setup(AddToCart, { guest: true, props: { product } })
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(router.currentRoute.value.name).toBe('login')
  expect(router.currentRoute.value.query.redirect).toBe(`/products/${product.slug}`)
  expect(cartApi.addCartItem).not.toHaveBeenCalled()
})
it('authenticated add-to-cart refreshes badge state and displays success', async () => {
  const { wrapper, cart } = await setup(AddToCart, { props: { product } })
  await wrapper.get('input').setValue('2')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(cartApi.addCartItem).toHaveBeenCalledWith('1', 2)
  expect(cart.count).toBe(5)
  expect(wrapper.text()).toContain('Đã thêm sản phẩm')
  expect(wrapper.get('a').attributes('href')).toBe('/cart')
})
it('shows stock conflict, refreshes state and never retries the add automatically', async () => {
  cartApi.addCartItem.mockRejectedValue({
    response: { status: 409, data: { message: 'Stock changed' } },
  })
  const { wrapper } = await setup(AddToCart, { props: { product } })
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.text()).toContain('Dữ liệu đã thay đổi')
  expect(cartApi.addCartItem).toHaveBeenCalledOnce()
  expect(cartApi.getCart).toHaveBeenCalledOnce()
})
it('navigation badge loads from server and updates after a mutation', async () => {
  const { wrapper, cart } = await setup(CartLink)
  expect(wrapper.get('[data-cart-count]').text()).toBe('5')
  cartApi.getCart.mockResolvedValue(empty)
  await cart.clear()
  await flushPromises()
  expect(wrapper.get('[data-cart-count]').text()).toBe('0')
})
it('deduplicates simultaneous reads and discards an older read after a mutation', async () => {
  let release
  cartApi.getCart.mockImplementationOnce(
    () =>
      new Promise((resolve) => {
        release = resolve
      }),
  )
  const { cart } = stores()
  const first = cart.load(),
    duplicate = cart.load()
  expect(cartApi.getCart).toHaveBeenCalledOnce()
  await cart.add('1', 1)
  expect(cart.count).toBe(5)
  release(empty)
  await Promise.all([first, duplicate])
  expect(cart.count).toBe(5)
})
it('resets cart on account change and ignores responses from the previous account', async () => {
  let release
  const { cart, auth } = stores()
  await cart.load()
  expect(cart.count).toBe(5)
  cartApi.getCart.mockImplementationOnce(
    () =>
      new Promise((resolve) => {
        release = resolve
      }),
  )
  const pending = cart.load()
  auth.user = null
  expect(cart.data).toBeNull()
  expect(cart.count).toBeNull()
  auth.user = { id: 'other', role: 'USER' }
  release(base)
  await pending
  expect(cart.data).toBeNull()
})
it('does not repeat a committed mutation when refreshing its result fails', async () => {
  const { cart } = stores()
  cartApi.getCart.mockRejectedValue(new Error('offline'))
  await cart.add('1', 1)
  expect(cartApi.addCartItem).toHaveBeenCalledOnce()
  expect(cart.data).toBeNull()
  expect(cart.error).toContain('trước khi thêm lần nữa')
})
it('cart route uses the existing authenticated route guard', async () => {
  const { auth } = stores(true)
  expect(await createAuthGuard(auth)({ meta: { requiresAuth: true } })).toEqual({ name: 'login' })
})
it('Cart API uses shared CSRF session client and sends only identifiers and quantity', async () => {
  vi.restoreAllMocks()
  const calls = []
  vi.spyOn(api, 'get').mockImplementation(async (url) => {
    calls.push(url)
    return { data: { data: base } }
  })
  vi.spyOn(api, 'request').mockImplementation(async (request) => {
    calls.push(request.url)
    return { data: { data: a } }
  })
  expect(await cartApi.getCart()).toEqual(base)
  await cartApi.addCartItem('1', 2)
  expect(calls.slice(-2)).toEqual(['/sanctum/csrf-cookie', '/api/v1/cart/items'])
  expect(api.request).toHaveBeenLastCalledWith({
    method: 'post',
    url: '/api/v1/cart/items',
    data: { product_id: '1', quantity: 2 },
  })
  await cartApi.updateCartItem('11', 3)
  expect(api.request).toHaveBeenLastCalledWith({
    method: 'patch',
    url: '/api/v1/cart/items/11',
    data: { quantity: 3 },
  })
  await cartApi.removeCartItem('11')
  await cartApi.clearCart()
  expect(api.request).toHaveBeenLastCalledWith({ method: 'delete', url: '/api/v1/cart', data: {} })
})
