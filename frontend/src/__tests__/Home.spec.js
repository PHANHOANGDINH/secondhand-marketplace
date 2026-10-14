import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import * as categoryApi from '../api/category.api'
import * as recommendationApi from '../api/recommendation.api'
import * as productApi from '../api/product.api'
import * as domainApi from '../api/domain.api'
import axios from '../api/axios'
import HomeView from '../views/HomeView.vue'
import HomeHero from '../components/home/HomeHero.vue'
import HomeTabs from '../components/home/HomeTabs.vue'
import { areaPickerOpen, useArea } from '../composables/useArea'

const wrappers = []
async function setup(component, { user = null, path = '/', stubs = {} } = {}) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = user ? { id: user, name: 'Người dùng', role: 'USER', status: 'ACTIVE' } : null
  auth.initialized = true
  const router = createRouter({
    history: createMemoryHistory(),
    routes: ['/', '/products', '/products/:slug', '/shops/:slug', '/auctions/:id', '/login'].map((route) => ({
      path: route,
      component: { template: '<div />' },
    })),
  })
  await router.push(path)
  const wrapper = mount(component, { global: { plugins: [pinia, router], stubs: { ProductSearch: false, ...stubs } } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router }
}
const item = (id) => ({ id: String(id), name: `Món ${id}`, slug: `mon-${id}`, price: '100000.00', condition: 'GOOD', is_wishlisted: false })
beforeEach(() => {
  vi.restoreAllMocks()
  localStorage.clear()
  areaPickerOpen.value = false
  useArea().setArea('')
  vi.spyOn(axios, 'get').mockResolvedValue({ data: { status: 'ok' } })
  vi.spyOn(domainApi, 'read').mockResolvedValue({ data: [] })
  vi.spyOn(categoryApi, 'listCategories').mockResolvedValue({
    data: [
      { id: '3', name: 'Điện tử', slug: 'dien-tu' },
      { id: '4', name: 'Sách', slug: 'sach' },
    ],
  })
  vi.spyOn(recommendationApi, 'listRecommendations').mockResolvedValue([item(1)])
  vi.spyOn(productApi, 'listProducts').mockResolvedValue({ data: [item(2)] })
})
afterEach(() => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount()
})

it('hero has the slogan, a search that carries the chosen area, and a category row', async () => {
  const { wrapper, router } = await setup(HomeHero)
  expect(wrapper.get('h2').text()).toBe('Đồ cũ giá tốt, gần bạn, chốt nhanh!')
  expect(wrapper.get('[data-area-open]').text()).toBe('Chọn khu vực')
  expect(wrapper.findAll('.home-category').map((link) => [link.text(), link.attributes('href')])).toEqual([
    ['Điện tử', '/products?category=3'],
    ['Sách', '/products?category=4'],
  ])
  expect(wrapper.get('.home-categories-next').attributes('aria-label')).toBe('Xem thêm danh mục')
  await wrapper.get('[data-area-open]').trigger('click')
  expect(areaPickerOpen.value).toBe(true)
  useArea().setArea('79')
  await flushPromises()
  expect(wrapper.get('.home-category').attributes('href')).toBe('/products?category=3&province=79')
  await wrapper.get('input[type="search"]').setValue('  tai nghe ')
  await wrapper.get('form[role="search"]').trigger('submit')
  await flushPromises()
  expect(router.currentRoute.value.fullPath).toBe('/products?q=tai+nghe&province=79')
})

it('product tabs follow the chosen area and reload when it changes', async () => {
  useArea().setArea('01')
  const { wrapper } = await setup(HomeTabs, { stubs: { HomeDiscovery: true } })
  expect(recommendationApi.listRecommendations).toHaveBeenLastCalledWith(12, { province: '01' })
  await wrapper.get('[data-home-tab="latest"]').trigger('click')
  await flushPromises()
  expect(productApi.listProducts).toHaveBeenLastCalledWith({ sort: 'newest', per_page: 12, province: '01' })
  expect(wrapper.get('.home-more a').attributes('href')).toBe('/products?sort=newest&province=01')
  useArea().setArea('')
  await flushPromises()
  expect(productApi.listProducts).toHaveBeenLastCalledWith({ sort: 'newest', per_page: 12 })
  // An empty area gets a clear message instead of an empty grid.
  productApi.listProducts.mockResolvedValue({ data: [] })
  useArea().setArea('48')
  await flushPromises()
  expect(wrapper.find('[data-product]').exists()).toBe(false)
  expect(wrapper.text()).toContain('Chưa có tin đăng ở')
})

it('a deep link to a video opens the video tab', async () => {
  const { wrapper } = await setup(HomeTabs, { path: '/?discovery=video', stubs: { HomeDiscovery: true } })
  expect(wrapper.get('[data-home-tab="video"]').attributes('aria-pressed')).toBe('true')
  expect(recommendationApi.listRecommendations).not.toHaveBeenCalled()
})

it('the area picker opens by itself once for a signed-in user, after the welcome tour is done', async () => {
  const stubs = { HomeHero: true, HomeTabs: true }
  // Welcome tour still pending: the area picker waits.
  await setup(HomeView, { user: '7', stubs })
  expect(areaPickerOpen.value).toBe(false)
  localStorage.setItem('passlai:first-time:7:welcome', '1')
  await setup(HomeView, { user: '7', stubs })
  expect(areaPickerOpen.value).toBe(true)
  expect(localStorage.getItem('passlai:first-time:7:area')).toBe('1')
  areaPickerOpen.value = false
  await setup(HomeView, { user: '7', stubs })
  expect(areaPickerOpen.value).toBe(false)
  // Guests are never interrupted.
  await setup(HomeView, { stubs })
  expect(areaPickerOpen.value).toBe(false)
})
