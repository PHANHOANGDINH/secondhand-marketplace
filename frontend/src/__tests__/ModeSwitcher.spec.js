import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import * as shopApi from '../api/shop.api'
import ModeSwitcher from '../components/layout/ModeSwitcher.vue'

const wrappers = []
async function setup(path, { role = 'ADMIN', inline = false } = {}) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = { id: '5', name: 'Người dùng', role, status: 'ACTIVE' }
  const component = { template: '<div />' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: ['/', '/admin', '/admin/dashboard', '/seller/dashboard', '/seller/shop', '/orders', '/my-ads'].map(
      (route) => ({ path: route, component }),
    ),
  })
  await router.push(path)
  const wrapper = mount(ModeSwitcher, { props: { inline }, global: { plugins: [pinia, router] } })
  wrappers.push(wrapper)
  if (!inline) await wrapper.get('.mode-switcher-button').trigger('click')
  await flushPromises()
  return { wrapper, router }
}
async function choose(wrapper, mode) {
  await wrapper.get(`[data-mode-option="${mode}"]`).trigger('click')
  await flushPromises()
}

beforeEach(() => {
  localStorage.clear()
  vi.spyOn(shopApi, 'getSellerShop').mockResolvedValue({ id: '1' })
})
afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  vi.restoreAllMocks()
})

it('every area can be reached from the seller and admin areas', async () => {
  const seller = await setup('/seller/dashboard')
  for (const button of seller.wrapper.findAll('[data-mode-option]'))
    expect(button.attributes('disabled')).toBeUndefined()
  await choose(seller.wrapper, 'admin')
  expect(seller.router.currentRoute.value.path).toBe('/admin')

  const admin = await setup('/admin/dashboard')
  await choose(admin.wrapper, 'seller')
  expect(admin.router.currentRoute.value.path).toBe('/seller/dashboard')

  const back = await setup('/admin/dashboard')
  await choose(back.wrapper, 'buyer')
  expect(back.router.currentRoute.value.path).toBe('/')
})

it('sends a user without a shop to the page that creates one', async () => {
  shopApi.getSellerShop.mockRejectedValue({ response: { status: 404 } })
  const { wrapper, router } = await setup('/admin/dashboard')
  await choose(wrapper, 'seller')
  expect(router.currentRoute.value.path).toBe('/seller/shop')
})

it('choosing the area already open stays on the current page', async () => {
  const { wrapper, router } = await setup('/my-ads')
  await choose(wrapper, 'seller')
  expect(router.currentRoute.value.path).toBe('/my-ads')
})

it('the buyer account menu keeps swapping shortcuts without leaving the page', async () => {
  const { wrapper, router } = await setup('/orders', { inline: true })
  await choose(wrapper, 'admin')
  expect(router.currentRoute.value.path).toBe('/orders')
  expect(wrapper.findAll('.mode-switcher-links a').map((link) => link.attributes('href'))).toContain('/admin')
})

it('only an administrator is offered the admin area', async () => {
  const { wrapper } = await setup('/seller/dashboard', { role: 'USER' })
  expect(wrapper.findAll('[data-mode-option]').map((button) => button.attributes('data-mode-option'))).toEqual([
    'buyer',
    'seller',
  ])
})
