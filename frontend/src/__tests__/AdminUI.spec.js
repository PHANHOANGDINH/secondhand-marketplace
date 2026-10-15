import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createPinia } from 'pinia'
import AdminShell from '../components/admin/AdminShell.vue'
import AdminRecordHeader from '../components/admin/AdminRecordHeader.vue'
import AdminRating from '../components/admin/AdminRating.vue'
const wrappers = []
afterEach(() => {
  wrappers.splice(0).forEach((w) => w.unmount())
  vi.unstubAllGlobals()
})
async function shell() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      '/',
      '/products',
      '/account',
      '/account/profile',
      ...[
        'dashboard',
        'users',
        'shops',
        'products',
        'categories',
        'orders',
        'reviews',
        'reports',
      ].map((name) => '/admin/' + name),
    ].map((path) => ({ path, component: { template: '<div />' } })),
  })
  await router.push('/admin/orders?order=12')
  const wrapper = mount(AdminShell, {
    attachTo: document.body,
    global: { plugins: [router, createPinia()] },
  })
  wrappers.push(wrapper)
  return { wrapper, router }
}
it('Admin shell retains existing sections and adds the report workflow and active state for query-linked orders', async () => {
  const { wrapper } = await shell()
  // D5 adds the Action Center, auctions and payment reconciliation to the eight original sections.
  expect(wrapper.findAll('nav a')).toHaveLength(12)
  expect(wrapper.get('nav a[href="/admin"]').classes()).not.toContain('active')
  expect(wrapper.get('a[href="/admin/reports"]').text()).toContain('Báo cáo vi phạm')
  expect(wrapper.get('nav a.active').attributes('href')).toBe('/admin/orders')
  expect(wrapper.get('nav a.active').attributes('aria-current')).toBe('page')
  expect(wrapper.get('.admin-brand').text().replace(/\s+/g, ' ')).toMatch(/^passdi ?Quản trị$/)
})
it('Admin menu supports Escape focus restoration and closes after query navigation', async () => {
  const { wrapper, router } = await shell()
  const toggle = wrapper.get('[aria-controls=admin-navigation]')
  await toggle.trigger('click')
  expect(toggle.attributes('aria-expanded')).toBe('true')
  await wrapper.get('.admin-sidebar').trigger('keydown', { key: 'Escape' })
  expect(toggle.attributes('aria-expanded')).toBe('false')
  expect(document.activeElement).toBe(toggle.element)
  await toggle.trigger('click')
  await router.push('/admin/orders?order=13')
  await flushPromises()
  expect(toggle.attributes('aria-expanded')).toBe('false')
})
it('Compact Admin record selection moves keyboard focus to the loaded detail', async () => {
  vi.stubGlobal('matchMedia', () => ({ matches: true }))
  const wrapper = mount(AdminRecordHeader, {
    attachTo: document.body,
    props: { title: 'Selected record', status: 'ACTIVE' },
  })
  wrappers.push(wrapper)
  await flushPromises()
  expect(document.activeElement).toBe(wrapper.get('header').element)
  expect(wrapper.get('h2').text()).toBe('Selected record')
})
it('Review rating has one readable accessible label and decorative stars', () => {
  const wrapper = mount(AdminRating, { props: { rating: 4 } })
  wrappers.push(wrapper)
  expect(wrapper.get('.admin-rating').attributes('aria-label')).toBe('4 trên 5 sao')
  expect(wrapper.get('.admin-rating-stars').attributes('aria-hidden')).toBe('true')
  expect(wrapper.text()).toContain('4 / 5')
})
