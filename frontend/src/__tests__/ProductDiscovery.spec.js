import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createPinia } from 'pinia'
import * as products from '../api/product.api'
import * as options from '../api/catalogOptions'
import ProductsView from '../views/ProductsView.vue'
import ProductSearch from '../components/ProductSearch.vue'
import { normalizeDiscoveryQuery } from '../utils/discoveryQuery'
const wrappers = []
const item = {
  id: '1',
  name: 'Điện thoại',
  slug: 'dien-thoai',
  price: '100.00',
  condition: 'GOOD',
  auction: { id: '7', status: 'LIVE', current_price: '120.00' },
}
const collection = (data = []) => ({
  data,
  meta: { total: data.length, current_page: 1, last_page: 2 },
})
async function setup(component = ProductsView, path = '/products', props = {}) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: ['/products', '/products/:slug', '/auctions/:id'].map((path) => ({
      path,
      component: { template: '<div />' },
    })),
  })
  await router.push(path)
  const wrapper = mount(component, {
    props,
    attachTo: document.body,
    global: { plugins: [createPinia(), router] },
  })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router }
}
beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(products, 'listProducts').mockResolvedValue(collection([item]))
  vi.spyOn(products, 'productSuggestions').mockResolvedValue({
    data: {
      products: [item],
      categories: [{ id: '3', name: 'Điện thoại cũ' }],
      shops: [{ id: '5', name: 'Góc đồ cũ' }],
    },
  })
  vi.spyOn(options, 'categoryOptions').mockResolvedValue([{ value: '3', label: 'Điện thoại cũ' }])
  vi.spyOn(options, 'shopOptions').mockResolvedValue([{ value: '5', label: 'Góc đồ cũ' }])
})
afterEach(() => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount()
  vi.useRealTimers()
})
it('restores q/video/sale type and preserves auction CTA and pagination', async () => {
  const { wrapper, router } = await setup(
    ProductsView,
    '/products?q=phone&has_video=1&sale_type=auction&sort=oldest',
  )
  expect(products.listProducts).toHaveBeenCalledWith(
    expect.objectContaining({ q: 'phone', has_video: '1', sale_type: 'auction', sort: 'oldest' }),
  )
  expect(wrapper.get('a[href="/auctions/7"]').text()).toContain('Xem phiên')
  await wrapper
    .findAll('button')
    .find((b) => b.text() === 'Sau')
    .trigger('click')
  await flushPromises()
  expect(router.currentRoute.value.query).toMatchObject({
    q: 'phone',
    has_video: '1',
    sale_type: 'auction',
    page: '2',
  })
  router.back()
  await flushPromises()
  expect(router.currentRoute.value.query.page).toBeUndefined()
})
it('composes new filters on submit, changes sort, and clears all filters', async () => {
  const { wrapper, router } = await setup()
  await wrapper.get('select[name=has_video]').setValue('1')
  await wrapper.get('select[name=sale_type]').setValue('fixed')
  await wrapper.get('select[name=sort]').setValue('rating')
  await wrapper.get('select[name=category]').setValue('3')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(router.currentRoute.value.query).toMatchObject({
    has_video: '1',
    sale_type: 'fixed',
    sort: 'rating',
    category: '3',
  })
  await wrapper
    .findAll('a')
    .find((a) => a.text() === 'Xóa bộ lọc')
    .trigger('click')
  await flushPromises()
  expect(router.currentRoute.value.query).toEqual({})
})
it('normalizes malformed URL fields and decimal ranges without floating point', async () => {
  expect(
    normalizeDiscoveryQuery({
      q: ['bad'],
      sort: 'DROP',
      category: '-1',
      min_price: '1e3',
      has_video: 'true',
      page: '0',
    }),
  ).toEqual({})
  expect(
    normalizeDiscoveryQuery({ min_price: '9999999999999.99', max_price: '9999999999999.98' }),
  ).toEqual({ min_price: '9999999999999.99' })
  const { router } = await setup(ProductsView, '/products?sort=bad&min_price=-1&has_video=1')
  expect(router.currentRoute.value.query).toEqual({ has_video: '1' })
})
it('opens the accessible mobile filter dialog and restores focus on Escape', async () => {
  const { wrapper } = await setup()
  const opener = wrapper.get('.mobile-filters')
  opener.element.focus()
  await opener.trigger('click')
  await flushPromises()
  const dialog = document.querySelector('[role=dialog]')
  expect(dialog.getAttribute('aria-modal')).toBe('true')
  expect(document.body.style.overflow).toBe('hidden')
  dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
  await flushPromises()
  expect(document.querySelector('[role=dialog]')).toBeNull()
  expect(document.activeElement).toBe(opener.element)
})
it('shows skeleton, empty and safe error/retry states independently', async () => {
  let resolve
  products.listProducts.mockImplementationOnce(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  const { wrapper } = await setup()
  expect(wrapper.find('[aria-label="Đang tải sản phẩm"]').exists()).toBe(true)
  resolve(collection())
  await flushPromises()
  expect(wrapper.text()).toContain('Không tìm thấy sản phẩm phù hợp')
  products.listProducts.mockRejectedValue(new Error('SQL private failure'))
  await wrapper
    .findAll('button')
    .find((b) => b.text() === 'Tải lại')
    .trigger('click')
  await flushPromises()
  expect(wrapper.text()).not.toContain('SQL private failure')
  expect(wrapper.text()).not.toContain('Không tìm thấy sản phẩm phù hợp')
})
it('debounces suggestions, supports keyboard navigation and uses real destinations', async () => {
  vi.useFakeTimers()
  const { wrapper, router } = await setup(ProductSearch, '/products', { modelValue: '' })
  await wrapper.get('input').trigger('focus')
  await wrapper.setProps({ modelValue: 'p' })
  await vi.advanceTimersByTimeAsync(400)
  expect(products.productSuggestions).not.toHaveBeenCalled()
  await wrapper.setProps({ modelValue: 'ph' })
  await vi.advanceTimersByTimeAsync(100)
  await wrapper.setProps({ modelValue: 'phone' })
  await vi.advanceTimersByTimeAsync(300)
  await flushPromises()
  expect(products.productSuggestions).toHaveBeenCalledTimes(1)
  expect(wrapper.findAll('[role=option]')).toHaveLength(3)
  await wrapper.get('input').trigger('keydown', { key: 'ArrowDown' })
  expect(wrapper.get('input').attributes('aria-activedescendant')).toBeTruthy()
  await wrapper.get('input').trigger('keydown', { key: 'Enter' })
  await flushPromises()
  expect(router.currentRoute.value.path).toBe('/products/dien-thoai')
})
it('cancels debounce on unmount and ignores stale suggestion responses', async () => {
  vi.useFakeTimers()
  let resolve
  products.productSuggestions.mockImplementationOnce(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  const { wrapper } = await setup(ProductSearch)
  await wrapper.setProps({ modelValue: 'old' })
  await vi.advanceTimersByTimeAsync(300)
  await wrapper.setProps({ modelValue: 'x' })
  resolve({ data: { products: [item] } })
  await flushPromises()
  expect(wrapper.findAll('[role=option]')).toHaveLength(0)
  await wrapper.setProps({ modelValue: 'new' })
  wrapper.unmount()
  await vi.advanceTimersByTimeAsync(500)
  expect(products.productSuggestions).toHaveBeenCalledTimes(1)
})

it('shows the real auction lifecycle label while keeping its arena link', async () => {
  products.listProducts.mockResolvedValue(collection([{ ...item, auction: { ...item.auction, status: 'ENDED' } }]))
  const { wrapper } = await setup()
  const link = wrapper.get('a[href="/auctions/7"]')
  expect(link.text()).toContain('Đã kết thúc')
  expect(link.text()).toContain('Xem phiên')
  expect(wrapper.text()).not.toContain('Đang đấu giá')
})
