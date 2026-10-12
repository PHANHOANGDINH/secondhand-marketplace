import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent } from 'vue'
import { beforeEach, afterEach, expect, it, vi } from 'vitest'
import { useAuthStore } from '../stores/auth'
import * as api from '../api/notification.api'
import { provideNotifications, safeNotificationTarget } from '../composables/useNotifications'
import Bell from '../components/notifications/NotificationBell.vue'
import View from '../views/NotificationsView.vue'
const mock = vi.hoisted(() => ({ subscriptions: [] }))
vi.mock('../composables/auctionEcho', () => ({
  subscribeRealtime: (kind, id, handlers, ready) => {
    const release = vi.fn()
    mock.subscriptions.push({ kind, id, handlers, ready, release })
    return release
  },
}))
const row = (id = 'n1') => ({
  id,
  type: 'OUTBID',
  auction_id: '7',
  product_id: '3',
  product_name: 'Máy ảnh',
  message: 'Có người vừa trả giá cao hơn bạn cho Máy ảnh.',
  target_route: '/auctions/7',
  created_at: '2026-10-08T00:00:00Z',
  read_at: null,
})
let stored,
  state,
  wrappers = []
beforeEach(() => {
  vi.restoreAllMocks()
  mock.subscriptions.length = 0
  stored = [row()]
  vi.spyOn(api, 'listNotifications').mockImplementation(async (params) => {
    const filtered = stored.filter((n) => params?.filter !== 'unread' || !n.read_at)
    return {
      data: structuredClone(filtered),
      unread_count: stored.filter((n) => !n.read_at).length,
      meta: { current_page: params?.page || 1, last_page: 1, total: filtered.length },
    }
  })
  vi.spyOn(api, 'getNotificationCount').mockImplementation(async () => ({
    unread_count: stored.filter((n) => !n.read_at).length,
  }))
  vi.spyOn(api, 'markNotificationRead').mockImplementation(async (id) => {
    const item = stored.find((n) => n.id === id)
    item.read_at = '2026-10-08T00:01:00Z'
    return structuredClone(item)
  })
  vi.spyOn(api, 'markAllNotificationsRead').mockImplementation(async () => {
    stored.forEach((n) => {
      n.read_at = '2026-10-08T00:01:00Z'
    })
    return { unread_count: 0 }
  })
})
afterEach(() => {
  wrappers.splice(0).forEach((w) => w.unmount())
  document.body.innerHTML = ''
})
async function setup(component = Bell, user = { id: '1', role: 'USER' }) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = user
  auth.initialized = true
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }],
  })
  await router.push('/notifications')
  const Parent = defineComponent({
    components: { Child: component },
    setup() {
      state = provideNotifications()
    },
    template: '<Child />',
  })
  const wrapper = mount(Parent, {
    attachTo: document.body,
    global: { plugins: [pinia, router], stubs: { Teleport: true } },
  })
  wrappers.push(wrapper)
  await flushPromises()
  await flushPromises()
  return { wrapper, auth, router }
}
it('loads persistent offline notifications and unread badge after login', async () => {
  const { wrapper } = await setup()
  expect(api.listNotifications).toHaveBeenCalledWith({ per_page: 20 })
  expect(wrapper.get('.notification-badge').text()).toBe('1')
  expect(wrapper.get('.notification-bell-button').attributes('aria-label')).toContain('1 chưa đọc')
})
it('opens an accessible recent panel and Escape returns focus to the bell', async () => {
  const { wrapper } = await setup()
  await wrapper.get('.notification-bell-button').trigger('click')
  await flushPromises()
  expect(wrapper.get('[role="dialog"]').attributes('aria-label')).toBe('Thông báo')
  expect(wrapper.get('[role="dialog"]').text()).toContain('Ra giá lại')
  await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' })
  expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  expect(document.activeElement).toBe(wrapper.get('.notification-bell-button').element)
})
it('individual read action updates the database-backed count and styling', async () => {
  const { wrapper } = await setup()
  await wrapper.get('.notification-bell-button').trigger('click')
  await flushPromises()
  await wrapper.get('.notification-read').trigger('click')
  await flushPromises()
  expect(api.markNotificationRead).toHaveBeenCalledWith('n1')
  expect(wrapper.find('.is-unread').exists()).toBe(false)
  expect(wrapper.find('.notification-badge').exists()).toBe(false)
})
it('mark all read uses the API and clears unread display', async () => {
  stored.push(row('n2'))
  const { wrapper } = await setup()
  await wrapper.get('.notification-bell-button').trigger('click')
  await flushPromises()
  await wrapper.get('button.btn-link').trigger('click')
  await flushPromises()
  expect(api.markAllNotificationsRead).toHaveBeenCalledTimes(1)
  expect(wrapper.find('.notification-badge').exists()).toBe(false)
  expect(wrapper.findAll('.is-unread')).toHaveLength(0)
})
it('inserts realtime records once and reconciles with the persisted database list', async () => {
  stored = []
  await setup()
  stored.push(row())
  mock.subscriptions[0].handlers.NotificationCreated(row())
  mock.subscriptions[0].handlers.NotificationCreated(row())
  await flushPromises()
  expect(state.rows.value).toHaveLength(1)
  expect(state.unread.value).toBe(1)
  expect(api.getNotificationCount).toHaveBeenCalled()
})
it('reconnect refreshes notifications missed while the user was offline', async () => {
  stored = []
  await setup()
  stored.push(row())
  mock.subscriptions[0].ready()
  await flushPromises()
  expect(state.rows.value.map((n) => n.id)).toEqual(['n1'])
  expect(state.unread.value).toBe(1)
})
it('navigation marks the notification read before using its safe internal target', async () => {
  const { wrapper, router } = await setup()
  await wrapper.get('.notification-bell-button').trigger('click')
  await flushPromises()
  await wrapper.get('.notification-open').trigger('click')
  await flushPromises()
  expect(api.markNotificationRead).toHaveBeenCalledWith('n1')
  expect(router.currentRoute.value.path).toBe('/auctions/7')
  expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
})
it('rejects external, javascript and malformed navigation targets', () => {
  for (const value of [
    'https://evil.test',
    '//evil.test',
    'javascript:alert(1)',
    '/admin/users',
    '/auctions/0',
    '/orders/1?redirect=//evil.test',
  ])
    expect(safeNotificationTarget(value)).toBe('/notifications')
  expect(safeNotificationTarget('/auctions/7/checkout')).toBe('/auctions/7/checkout')
  expect(safeNotificationTarget('/seller/orders/5')).toBe('/seller/orders/5')
})
it('plain-text notification messages never render injected HTML', async () => {
  stored[0].message = '<img src=x onerror=alert(1)>'
  const { wrapper } = await setup()
  await wrapper.get('.notification-bell-button').trigger('click')
  await flushPromises()
  expect(wrapper.get('.notification-list').text()).toContain('<img src=x onerror=alert(1)>')
  expect(wrapper.find('.notification-list img').exists()).toBe(false)
})
it('the full page has all/unread/auction/orders filters and no competing empty/error state', async () => {
  const { wrapper } = await setup(View)
  expect(wrapper.findAll('#notification-filter option')).toHaveLength(4)
  await wrapper.get('#notification-filter').setValue('unread')
  await flushPromises()
  expect(api.listNotifications).toHaveBeenCalledWith({ filter: 'unread', page: 1, per_page: 20 })
  expect(wrapper.find('.is-unread').exists()).toBe(true)
})
it('notification page has readable empty and error states', async () => {
  stored = []
  const { wrapper } = await setup(View)
  expect(wrapper.text()).toContain('Chưa có thông báo trong mục này.')
  api.listNotifications.mockRejectedValue(new Error('network'))
  await wrapper.get('#notification-filter').setValue('orders')
  await flushPromises()
  expect(wrapper.find('[role="alert"]').exists()).toBe(true)
  expect(wrapper.text()).not.toContain('Chưa có thông báo trong mục này.')
})
it('logout clears private state and releases the realtime channel', async () => {
  const { auth } = await setup()
  auth.user = null
  await flushPromises()
  expect(state.rows.value).toHaveLength(0)
  expect(state.unread.value).toBe(0)
  expect(mock.subscriptions[0].release).toHaveBeenCalledTimes(1)
})
it('stale user responses cannot repopulate another account notifications', async () => {
  let resolve
  api.listNotifications.mockImplementationOnce(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  const { auth } = await setup()
  stored = []
  auth.user = { id: '2', role: 'USER' }
  await flushPromises()
  resolve({ data: [row()], unread_count: 1 })
  await flushPromises()
  expect(state.rows.value).toHaveLength(0)
  expect(state.unread.value).toBe(0)
})
it('unmount releases subscriptions and removes refresh listeners', async () => {
  const { wrapper } = await setup()
  wrapper.unmount()
  await flushPromises()
  const calls = api.listNotifications.mock.calls.length
  window.dispatchEvent(new Event('online'))
  document.dispatchEvent(new Event('visibilitychange'))
  await flushPromises()
  expect(api.listNotifications).toHaveBeenCalledTimes(calls)
  expect(mock.subscriptions[0].release).toHaveBeenCalledTimes(1)
})
it('read failure preserves unread data and shows a retryable error', async () => {
  api.markNotificationRead.mockRejectedValue(new Error('network'))
  const { wrapper } = await setup()
  await wrapper.get('.notification-bell-button').trigger('click')
  await flushPromises()
  await wrapper.get('.notification-read').trigger('click')
  await flushPromises()
  expect(wrapper.find('[role="alert"]').exists()).toBe(true)
  expect(wrapper.get('.notification-badge').text()).toBe('1')
})

it('a stale realtime unread-count response cannot undo a completed mark-all', async () => {
  await setup()
  let resolve
  api.getNotificationCount.mockImplementationOnce(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  const next = row('n2')
  stored.push(next)
  mock.subscriptions[0].handlers.NotificationCreated(next)
  await flushPromises()
  await state.readAll()
  await flushPromises()
  expect(state.unread.value).toBe(0)
  resolve({ unread_count: 2 })
  await flushPromises()
  expect(state.unread.value).toBe(0)
})

it('resizing an open bell closes safely when the event target is Window', async () => {
  const { wrapper } = await setup()
  await wrapper.get('.notification-bell-button').trigger('click')
  await flushPromises()
  expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
  window.dispatchEvent(new Event('resize'))
  await flushPromises()
  expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
})
