import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia, disposePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { createAuthGuard } from '../router/authGuard'
import applicationRouter from '../router'
import * as api from '../api/admin.api'
import axios from '../api/axios'
import AdminDashboardView from '../views/AdminDashboardView.vue'
import AdminUsersView from '../views/AdminUsersView.vue'
import AdminReviewsView from '../views/AdminReviewsView.vue'
import AdminOrdersView from '../views/AdminOrdersView.vue'
import ForbiddenView from '../views/ForbiddenView.vue'
import { formatPrice } from '../composables/productPresentation'
const user = {
  id: '2',
  name: 'Buyer',
  email: 'buyer@example.test',
  role: 'USER',
  status: 'ACTIVE',
  archived: false,
  can_moderate: true,
}
const review = {
  id: '7',
  rating: 4,
  comment: 'Original review',
  status: 'PUBLISHED',
  author: { id: '2', name: 'Buyer' },
  product: { id: '8', name: 'Phone', slug: 'phone', archived: false },
  moderation_reason: null,
}
const order = {
  id: '12',
  order_code: 'ORDER-12',
  buyer: { id: '2', name: 'Buyer' },
  overall_status: 'PARTIALLY_COMPLETED',
  total_amount: '100.10',
  shop_orders: [
    {
      id: '21',
      shop: { name: 'Shop A' },
      status: 'COMPLETED',
      total_amount: '100.10',
      items: [
        {
          id: '31',
          product_name: 'Snapshot phone',
          quantity: 1,
          product_price: '100.10',
          subtotal: '100.10',
        },
      ],
    },
    { id: '22', shop: { name: 'Shop B' }, status: 'CANCELLED', total_amount: '0.00', items: [] },
  ],
}
const metrics = {
  total_users: 7,
  active_users: 6,
  total_shops: 3,
  active_shops: 2,
  pending_shops: 1,
  total_products: 8,
  active_products: 6,
  pending_products: 2,
  total_orders: 4,
  completed_orders: 2,
  cancelled_orders: 1,
}
const dashboard = {
  ...metrics,
  total_revenue: '20000000000000.08',
  recent_orders: [order],
  recent_users: [user],
}
const collection = (rows, meta = {}) => ({
  data: rows,
  meta: { current_page: 1, last_page: 1, total: rows.length, ...meta },
})
const wrappers = [],
  instances = []
async function setup(component, { role = 'ADMIN', path = '/admin/dashboard' } = {}) {
  const pinia = createPinia()
  instances.push(pinia)
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.initialized = true
  auth.user = role ? { id: '1', role, status: 'ACTIVE' } : null
  const router = createRouter({
    history: createMemoryHistory(),
    routes: ['/admin/dashboard', '/admin/users', '/admin/reviews', '/admin/orders'].map((path) => ({
      path,
      component: { template: '<div />' },
    })),
  })
  await router.push(path)
  const wrapper = mount(component, { global: { plugins: [pinia, router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, auth, router }
}
const button = (wrapper, text) => wrapper.findAll('button').find((b) => b.text() === text)
beforeEach(() => {
  vi.restoreAllMocks()
  for (const [name, result] of Object.entries({
    getAdminDashboard: dashboard,
    listAdminUsers: collection([user]),
    getAdminUser: user,
    moderateUser: { ...user, status: 'SUSPENDED' },
    listAdminReviews: collection([review]),
    getAdminReview: review,
    moderateReview: { ...review, status: 'HIDDEN' },
    listAdminOrders: collection([order]),
    getAdminOrder: order,
  }))
    vi.spyOn(api, name).mockResolvedValue(structuredClone(result))
})
afterEach(() => {
  wrappers.splice(0).forEach((w) => w.unmount())
  instances.splice(0).forEach(disposePinia)
})
it('dashboard loads exact metrics, decimal merchandise revenue and recent records', async () => {
  const { wrapper } = await setup(AdminDashboardView)
  for (const [key, value] of Object.entries(metrics))
    expect(wrapper.get(`[data-metric="${key}"]`).text()).toBe(String(value))
  expect(wrapper.get('[data-total-revenue]').text()).toBe(formatPrice(dashboard.total_revenue))
  expect(wrapper.text()).toContain('toàn thời gian')
  expect(wrapper.text()).toContain('không phải doanh thu hoa hồng')
  expect(wrapper.get('[data-recent-order] a').attributes('href')).toBe('/admin/orders?order=12')
  expect(wrapper.get('[data-recent-user]').text()).toContain('Buyer')
})
it('dashboard empty state and API error support retry without stale totals', async () => {
  api.getAdminDashboard.mockRejectedValueOnce(new Error('Offline'))
  const { wrapper } = await setup(AdminDashboardView)
  expect(wrapper.text()).toContain('Không thể kết nối')
  expect(wrapper.find('[data-total-revenue]').exists()).toBe(false)
  api.getAdminDashboard.mockResolvedValue({
    ...dashboard,
    total_revenue: '0.00',
    recent_users: [],
    recent_orders: [],
  })
  await button(wrapper, 'Tải lại').trigger('click')
  await flushPromises()
  expect(wrapper.text()).toContain('Chưa có đơn hàng')
  expect(wrapper.text()).toContain('Chưa có người dùng')
  expect(wrapper.get('[data-total-revenue]').text()).toBe('0 ₫')
})
it('user search/status/role filters and pagination call the server', async () => {
  api.listAdminUsers.mockResolvedValue(collection([user], { last_page: 2, total: 21 }))
  const { wrapper } = await setup(AdminUsersView)
  await wrapper.get('[name="search"]').setValue('buyer')
  await wrapper.get('[name="status"]').setValue('ACTIVE')
  await wrapper.get('[name="role"]').setValue('USER')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(api.listAdminUsers).toHaveBeenLastCalledWith({
    page: 1,
    per_page: 20,
    search: 'buyer',
    status: 'ACTIVE',
    role: 'USER',
  })
  await button(wrapper, 'Sau').trigger('click')
  await flushPromises()
  expect(api.listAdminUsers).toHaveBeenLastCalledWith({
    page: 2,
    per_page: 20,
    search: 'buyer',
    status: 'ACTIVE',
    role: 'USER',
  })
})
it('user status action requires reason and expected status, with no credential/role mutation inputs', async () => {
  const { wrapper } = await setup(AdminUsersView)
  await button(wrapper, 'Xem').trigger('click')
  await flushPromises()
  expect(wrapper.get('[data-target="SUSPENDED"]').attributes('disabled')).toBeDefined()
  for (const field of ['email', 'password', 'role', 'status'])
    expect(wrapper.get('[data-selected]').find(`[name="${field}"]`).exists()).toBe(false)
  await wrapper.get('textarea[name="reason"]').setValue('Confirmed abuse')
  await wrapper.get('[data-selected] form').trigger('submit')
  await flushPromises()
  expect(api.moderateUser).toHaveBeenCalledWith('2', {
    expected_status: 'ACTIVE',
    status: 'SUSPENDED',
    reason: 'Confirmed abuse',
  })
  expect(wrapper.get('[data-target="ACTIVE"]').text()).toBe('Kích hoạt lại')
})
it('self/archived user has no status action', async () => {
  api.getAdminUser.mockResolvedValue({ ...user, can_moderate: false, archived: true })
  const { wrapper } = await setup(AdminUsersView)
  await button(wrapper, 'Xem').trigger('click')
  await flushPromises()
  expect(wrapper.find('[data-target]').exists()).toBe(false)
})
it('user status stale conflict and validation errors remain visible', async () => {
  const { wrapper } = await setup(AdminUsersView)
  await button(wrapper, 'Xem').trigger('click')
  await flushPromises()
  await wrapper.get('textarea').setValue('Reason')
  api.moderateUser.mockRejectedValueOnce({
    response: { status: 409, data: { message: 'User status has changed' } },
  })
  await wrapper.get('[data-selected] form').trigger('submit')
  await flushPromises()
  expect(wrapper.text()).toContain('Dữ liệu đã thay đổi')
  api.moderateUser.mockRejectedValueOnce({
    response: { status: 422, data: { errors: { reason: ['Reason is required'] } } },
  })
  await wrapper.get('[data-selected] form').trigger('submit')
  await flushPromises()
  expect(wrapper.text()).toContain('Lý do là bắt buộc')
  await button(wrapper, 'Tải lại chi tiết').trigger('click')
  await flushPromises()
  expect(api.getAdminUser).toHaveBeenCalledTimes(2)
})
it('review filter and hide/unhide preserve comment/rating and use optimistic status', async () => {
  const { wrapper } = await setup(AdminReviewsView)
  await wrapper.get('[name="status"]').setValue('PUBLISHED')
  await wrapper.get('[name="product"]').setValue('8')
  await wrapper.get('[name="rating"]').setValue(4)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(api.listAdminReviews).toHaveBeenLastCalledWith({
    page: 1,
    per_page: 20,
    status: 'PUBLISHED',
    product: 8,
    rating: 4,
  })
  await button(wrapper, 'Xem').trigger('click')
  await flushPromises()
  expect(wrapper.get('[data-selected]').text()).toContain('Original review')
  expect(wrapper.get('[data-selected]').find('[name="rating"]').exists()).toBe(false)
  await wrapper.get('textarea').setValue('Spam')
  await wrapper.get('[data-selected] form').trigger('submit')
  await flushPromises()
  expect(api.moderateReview).toHaveBeenLastCalledWith('7', {
    expected_status: 'PUBLISHED',
    status: 'HIDDEN',
    reason: 'Spam',
  })
  api.moderateReview.mockResolvedValue(review)
  await wrapper.get('textarea').setValue('Appeal accepted')
  await wrapper.get('[data-selected] form').trigger('submit')
  await flushPromises()
  expect(api.moderateReview).toHaveBeenLastCalledWith('7', {
    expected_status: 'HIDDEN',
    status: 'PUBLISHED',
    reason: 'Appeal accepted',
  })
})
it('review comments are escaped and list error/empty states are visible', async () => {
  api.listAdminReviews.mockRejectedValueOnce(new Error('Offline'))
  api.getAdminReview.mockResolvedValue({ ...review, comment: '<script>alert(1)</script>' })
  const { wrapper } = await setup(AdminReviewsView)
  expect(wrapper.text()).toContain('Không thể kết nối')
  api.listAdminReviews.mockResolvedValueOnce(collection([]))
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.text()).toContain('Không tìm thấy dữ liệu')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  await button(wrapper, 'Xem').trigger('click')
  await flushPromises()
  expect(wrapper.find('script').exists()).toBe(false)
  expect(wrapper.text()).toContain('<script>alert(1)</script>')
})
it('order filters use separate overall and shop states plus buyer/date range', async () => {
  const { wrapper } = await setup(AdminOrdersView)
  for (const [field, value] of Object.entries({
    overall_status: 'PARTIALLY_COMPLETED',
    shop_order_status: 'COMPLETED',
    buyer: '2',
    from: '2026-09-01',
    to: '2026-09-30',
  }))
    await wrapper.get(`[name="${field}"]`).setValue(value)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(api.listAdminOrders).toHaveBeenLastCalledWith({
    page: 1,
    per_page: 20,
    overall_status: 'PARTIALLY_COMPLETED',
    shop_order_status: 'COMPLETED',
    buyer: 2,
    from: '2026-09-01',
    to: '2026-09-30',
  })
})
it('order detail shows all shop snapshots and offers only read actions', async () => {
  const { wrapper } = await setup(AdminOrdersView)
  await button(wrapper, 'Xem').trigger('click')
  await flushPromises()
  const detail = wrapper.get('[data-order-detail]')
  expect(detail.text()).toContain('Shop A')
  expect(detail.text()).toContain('Shop B')
  expect(detail.text()).toContain('Snapshot phone')
  expect(detail.text()).toContain('100,10 ₫')
  expect(detail.findAll('button').map((b) => b.text())).toEqual(['Tải lại chi tiết'])
  expect(detail.find('form').exists()).toBe(false)
})
it('dashboard recent order query opens the order detail and handles missing records', async () => {
  const { wrapper } = await setup(AdminOrdersView, { path: '/admin/orders?order=12' })
  expect(api.getAdminOrder).toHaveBeenCalledWith('12')
  expect(wrapper.find('[data-order-detail]').exists()).toBe(true)
  api.getAdminOrder.mockRejectedValueOnce({ response: { status: 404 } })
  await button(wrapper, 'Tải lại chi tiết').trigger('click')
  await flushPromises()
  expect(wrapper.find('[data-order-detail]').exists()).toBe(false)
  expect(wrapper.text()).toContain('Không tìm thấy dữ liệu')
})
it('order empty list, pagination and server date validation are rendered', async () => {
  api.listAdminOrders.mockResolvedValueOnce(collection([]))
  const { wrapper } = await setup(AdminOrdersView)
  expect(wrapper.text()).toContain('Chưa có đơn hàng phù hợp')
  api.listAdminOrders.mockResolvedValueOnce(collection([order], { last_page: 2, total: 21 }))
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  await button(wrapper, 'Sau').trigger('click')
  await flushPromises()
  expect(api.listAdminOrders).toHaveBeenLastCalledWith({ page: 2, per_page: 20 })
  api.listAdminOrders.mockRejectedValueOnce({
    response: { status: 422, data: { errors: { to: ['Date range invalid'] } } },
  })
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.text()).toContain('Ngày kết thúc không hợp lệ')
})
it('all actual admin routes reject guests and ordinary users', async () => {
  const { auth } = await setup(AdminDashboardView, { role: null })
  for (const path of [
    '/admin/dashboard',
    '/admin/users',
    '/admin/reviews',
    '/admin/orders',
    '/admin/categories',
    '/admin/shops',
    '/admin/products',
  ]) {
    const route = applicationRouter.resolve(path)
    expect(route.meta.requiresAdmin).toBe(true)
    auth.user = null
    expect(await createAuthGuard(auth)(route)).toEqual({
      name: 'login',
      query: { redirect: route.fullPath },
    })
    auth.user = { id: '2', role: 'USER' }
    expect(await createAuthGuard(auth)(route)).toEqual({ name: 'forbidden' })
    auth.user = { id: '3', name: 'Seller', role: 'USER' }
    expect(await createAuthGuard(auth)(route)).toEqual({ name: 'forbidden' })
    auth.user = { id: '1', role: 'ADMIN' }
    expect(await createAuthGuard(auth)(route)).toBe(true)
  }
})
it('non-admin renders no sensitive data and logout hides in-flight results', async () => {
  const { wrapper: forbidden } = await setup(AdminUsersView, { role: 'USER' })
  expect(forbidden.find('table').exists()).toBe(false)
  expect(api.listAdminUsers).not.toHaveBeenCalled()
  let finish
  api.getAdminDashboard.mockImplementation(
    () =>
      new Promise((resolve) => {
        finish = resolve
      }),
  )
  const { wrapper, auth } = await setup(AdminDashboardView)
  auth.user = null
  await flushPromises()
  finish(dashboard)
  await flushPromises()
  expect(wrapper.find('[data-metric]').exists()).toBe(false)
})
it('admin API mutations use cookie CSRF helper and whitelisted status payload', async () => {
  vi.restoreAllMocks()
  const get = vi.spyOn(axios, 'get').mockResolvedValue({ data: { data: user } })
  const request = vi.spyOn(axios, 'request').mockResolvedValue({ data: { data: user } })
  const data = { expected_status: 'ACTIVE', status: 'SUSPENDED', reason: 'Reason' }
  expect(await api.moderateUser('2', data)).toEqual(user)
  expect(get).toHaveBeenCalledWith('/sanctum/csrf-cookie')
  expect(request).toHaveBeenCalledWith({
    method: 'patch',
    url: '/api/v1/admin/users/2/status',
    data,
  })
  await api.getAdminOrder('12')
  expect(get).toHaveBeenLastCalledWith('/api/v1/admin/orders/12', { params: undefined })
})

it('ordinary-user admin navigation reaches the forbidden page without loading admin data', async () => {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore(pinia)
  auth.initialized = true
  auth.user = { id: '2', role: 'USER', status: 'ACTIVE' }
  const dashboard = vi.fn(() => Promise.resolve({ template: '<div>Admin data</div>' }))
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div />' } },
      { path: '/account', name: 'account', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
      { path: '/admin/dashboard', component: dashboard, meta: { requiresAdmin: true } },
      { path: '/403', name: 'forbidden', component: ForbiddenView, meta: { requiresAuth: true } },
    ],
  })
  router.beforeEach(createAuthGuard(auth))
  await router.push('/admin/dashboard')
  await router.isReady()
  expect(router.currentRoute.value.path).toBe('/403')
  expect(dashboard).not.toHaveBeenCalled()
  const wrapper = mount(ForbiddenView, { global: { plugins: [pinia, router] } })
  expect(wrapper.get('h1').text()).toContain('403')
  expect(wrapper.text()).toContain('Bạn không có quyền truy cập')
  expect(wrapper.get('a[href="/account"]').text()).toContain('Tài khoản')
  wrapper.unmount()
  disposePinia(pinia)
})

it('logout makes an admin URL inaccessible and preserves its intended return', async () => {
  const pinia = createPinia()
  instances.push(pinia)
  const auth = useAuthStore(pinia)
  auth.initialized = true
  auth.user = { id: '1', role: 'ADMIN', status: 'ACTIVE' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
      {
        path: '/admin/dashboard',
        component: { template: '<div />' },
        meta: { requiresAdmin: true },
      },
    ],
  })
  router.beforeEach(createAuthGuard(auth))
  await router.push('/admin/dashboard')
  expect(router.currentRoute.value.path).toBe('/admin/dashboard')
  vi.spyOn(axios, 'get').mockResolvedValue({})
  vi.spyOn(axios, 'post').mockResolvedValue({})
  await auth.logout()
  await router.push('/')
  await router.push('/admin/dashboard')
  expect(router.currentRoute.value.name).toBe('login')
  expect(router.currentRoute.value.query.redirect).toBe('/admin/dashboard')
  expect(auth.isAdmin).toBe(false)
})
