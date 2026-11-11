import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter, RouterView } from 'vue-router'
import routes from '../router'
import DefaultLayout from '../layouts/DefaultLayout.vue'
import { useAuthStore } from '../stores/auth'
import api from '../api/axios'

beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: { items: [] } } })
})

it('unknown URLs render a recovery page with working catalog and home links', async () => {
  const router = createRouter({ history: createMemoryHistory(), routes: routes.options.routes })
  await router.push('/no-such-page')
  const wrapper = mount(RouterView, { global: { plugins: [router] } })
  await flushPromises()
  expect(router.currentRoute.value.name).toBe('not-found')
  expect(wrapper.get('h1').text()).toBe('Không tìm thấy trang')
  expect(wrapper.get('a[href="/products"]').exists()).toBe(true)
  expect(wrapper.get('a[href="/"]').exists()).toBe(true)
  wrapper.unmount()
})

it.each([null, 'USER', 'ADMIN'])(
  'navigation offers permitted destinations for %s',
  async (role) => {
    const pinia = createPinia()
    const auth = useAuthStore(pinia)
    auth.initialized = true
    auth.user = role ? { id: '9', role, status: 'ACTIVE' } : null
    const router = createRouter({ history: createMemoryHistory(), routes: routes.options.routes })
    await router.push('/')
    const wrapper = mount(DefaultLayout, { global: { plugins: [pinia, router] } })
    await flushPromises()
    const links = wrapper.findAll('a').map((link) => link.attributes('href'))
    expect(links).toContain('/products')
    expect(links).toContain('/shops')
    expect(wrapper.find('[data-mode-option="admin"]').exists()).toBe(role === 'ADMIN')
    expect(links.includes('/orders')).toBe(Boolean(role))
    expect(links.includes('/seller/shop')).toBe(Boolean(role))
    expect(links.includes('/login')).toBe(!role)
    for (const path of links) expect(router.resolve(path).name).not.toBe('not-found')
    const menuClosed = []
    if (role) {
      const menu = wrapper.get('details')
      menu.element.open = true
      await menu.trigger('keydown', { key: 'Escape' })
      menuClosed.push(menu.element.open)
      menu.element.open = true
      await wrapper.get('a[href="/orders"]').trigger('click')
      menuClosed.push(menu.element.open)
    }
    expect(menuClosed).toEqual(role ? [false, false] : [])
    wrapper.unmount()
  },
)
