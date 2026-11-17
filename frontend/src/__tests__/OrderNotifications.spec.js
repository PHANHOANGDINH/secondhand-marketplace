import { mount } from '@vue/test-utils'
import NotificationList from '../components/notifications/NotificationList.vue'
import { safeNotificationTarget } from '../composables/useNotifications'

const row = (type, target_route, extra = {}) => ({
  id: `${type}-${target_route}`,
  type,
  message: `Thông báo ${type}`,
  target_route,
  order_code: 'SHM-ABC',
  created_at: '2026-11-17T10:00:00Z',
  read_at: null,
  ...extra,
})

it.each([
  ['ORDER_PLACED', '/orders/12'],
  ['ORDER_PAID', '/seller/orders/7'],
  ['ORDER_STATUS_CHANGED', '/orders/12'],
])('%s offers "Xem đơn hàng" and a safe link to %s', (type, target) => {
  const wrapper = mount(NotificationList, { props: { rows: [row(type, target)] } })
  expect(wrapper.get('.notification-action').text()).toBe('Xem đơn hàng →')
  expect(wrapper.text()).not.toContain('Xem phiên')
  expect(wrapper.get('.notification-read').attributes('aria-label')).toBe(
    `Đánh dấu đã đọc: Thông báo ${type}`,
  )
  expect(safeNotificationTarget(target)).toBe(target)
  wrapper.unmount()
})

it('order notifications open emits the row for navigation', async () => {
  const notice = row('ORDER_STATUS_CHANGED', '/orders/12', { order_status: 'SHIPPING' })
  const wrapper = mount(NotificationList, { props: { rows: [notice] } })
  await wrapper.get('.notification-open').trigger('click')
  expect(wrapper.emitted('open')).toEqual([[notice]])
  wrapper.unmount()
})
