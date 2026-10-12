import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import OrdersView from '../views/OrdersView.vue'
import * as orderApi from '../api/order.api'
import StatusBadge from '../components/ui/StatusBadge.vue'
import { getStatusLabel } from '../utils/labels'
import DomainField from '../components/DomainField.vue'
import ShopsView from '../views/ShopsView.vue'
import * as shopApi from '../api/shop.api'

it('distinguishes status semantics without changing business values', () => {
  const groups = {
    success: ['ACTIVE', 'PUBLISHED', 'PAID', 'COMPLETED'],
    warning: ['PENDING', 'PROCESSING', 'HIDDEN', 'SUSPENDED'],
    danger: ['REJECTED', 'FAILED', 'CANCELLED'],
    neutral: ['DRAFT', 'REFUNDED'],
  }
  for (const [tone, statuses] of Object.entries(groups)) {
    for (const status of statuses) {
      const wrapper = mount(StatusBadge, { props: { status } })
      expect(wrapper.text()).toBe(getStatusLabel(status))
      expect(wrapper.classes()).toContain(`buyer-status-${tone}`)
      wrapper.unmount()
    }
  }
})

it('preserves required field labels and associated validation with a decorative marker', () => {
  const wrapper = mount(DomainField, {
    props: { name: 'name', label: 'Họ tên', required: true, errors: ['Vui lòng nhập họ tên.'] },
  })
  expect(wrapper.get('label').attributes('for')).toBe('name')
  expect(wrapper.get('.ui-required').attributes('aria-hidden')).toBe('true')
  expect(wrapper.get('input').element.required).toBe(true)
  expect(wrapper.get('input').attributes('aria-describedby')).toBe('name-errors')
  expect(wrapper.get('#name-errors').text()).toContain('Vui lòng nhập họ tên.')
  wrapper.unmount()
})

it('shows a shop loading error without a contradictory empty result', async () => {
  const spy = vi.spyOn(shopApi, 'listShops').mockRejectedValue(new Error('Network unavailable'))
  const wrapper = mount(ShopsView, { global: { stubs: { RouterLink: true } } })
  await flushPromises()
  expect(wrapper.find('[role="alert"]').exists()).toBe(true)
  expect(wrapper.find('.buyer-empty').exists()).toBe(false)
  wrapper.unmount()
  spy.mockRestore()
})

it('hides stale empty orders during a retry and its error state', async () => {
  const spy = vi.spyOn(orderApi, 'listSellerOrders').mockResolvedValue({ data: [], meta: null })
  const wrapper = mount(OrdersView, {
    props: { seller: true },
    global: { plugins: [createPinia()], stubs: { RouterLink: true } },
  })
  await flushPromises()
  expect(wrapper.find('.buyer-empty').exists()).toBe(true)
  spy.mockRejectedValue(new Error('Network unavailable'))
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.find('[role="alert"]').exists()).toBe(true)
  expect(wrapper.find('.buyer-empty').exists()).toBe(false)
  wrapper.unmount()
  spy.mockRestore()
})
