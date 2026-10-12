import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createPinia } from 'pinia'
import ConfirmDelete from '../components/ui/ConfirmDelete.vue'
import SellerShell from '../components/seller/SellerShell.vue'
const wrappers = []
afterEach(() => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount()
})
function confirmation(props = {}) {
  const wrapper = mount(ConfirmDelete, { props, attachTo: document.body })
  wrappers.push(wrapper)
  return wrapper
}
it('address confirmation focuses a safe action and Escape restores the delete trigger', async () => {
  const wrapper = confirmation()
  await wrapper.get('button').trigger('click')
  expect(wrapper.emitted('confirm')).toBeUndefined()
  expect(document.activeElement.textContent.trim()).toBe('Giữ lại')
  await wrapper.get('[role=group]').trigger('keydown', { key: 'Escape' })
  expect(wrapper.find('[role=group]').exists()).toBe(false)
  expect(document.activeElement.textContent.trim()).toBe('Xóa')
  expect(wrapper.emitted('confirm')).toBeUndefined()
})
it('address deletion emits once only after explicit confirmation', async () => {
  const wrapper = confirmation()
  await wrapper.get('button').trigger('click')
  await wrapper.findAll('button')[1].trigger('click')
  expect(wrapper.emitted('confirm')).toHaveLength(1)
  expect(wrapper.find('[role=group]').exists()).toBe(false)
})
it('a busy address cannot open the destructive confirmation', async () => {
  const wrapper = confirmation({ disabled: true })
  await wrapper.get('button').trigger('click')
  expect(wrapper.find('[role=group]').exists()).toBe(false)
  expect(wrapper.emitted('confirm')).toBeUndefined()
})
it('seller navigation highlights edit pages and closes after route navigation', async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      '/seller/dashboard',
      '/seller/products',
      '/seller/products/:id/edit',
      '/seller/orders',
      '/seller/shop',
      '/',
      '/products',
      '/account',
      '/account/profile',
    ].map((path) => ({ path, component: { template: '<div />' } })),
  })
  await router.push('/seller/products/7/edit')
  const wrapper = mount(SellerShell, { global: { plugins: [router, createPinia()] } })
  wrappers.push(wrapper)
  expect(wrapper.get('nav a.active').text()).toContain('Sản phẩm')
  expect(wrapper.get('nav a.active').attributes('aria-current')).toBe('page')
  await wrapper.get('[aria-controls=seller-navigation]').trigger('click')
  expect(wrapper.get('[aria-controls=seller-navigation]').attributes('aria-expanded')).toBe('true')
  await router.push('/seller/orders')
  await flushPromises()
  expect(wrapper.get('[aria-controls=seller-navigation]').attributes('aria-expanded')).toBe('false')
  expect(wrapper.get('nav a.active').text()).toContain('Đơn hàng')
})
