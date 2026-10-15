import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia, disposePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { createAuthGuard } from '../router/authGuard'
import applicationRouter from '../router'
import * as dashboardApi from '../api/sellerDashboard.api'
import axios from '../api/axios'
import SellerDashboardView from '../views/SellerDashboardView.vue'
import RevenueChart from '../components/RevenueChart.vue'
import { reportPreset } from '../composables/dashboardPresentation'
import { formatPrice } from '../composables/productPresentation'

const charts = vi.hoisted(() => [])
vi.mock('chart.js', () => ({
  Chart: class {
    static register = vi.fn()
    constructor(canvas, config) {
      this.canvas = canvas
      this.config = config
      this.data = config.data
      this.update = vi.fn()
      this.destroy = vi.fn()
      charts.push(this)
    }
  },
  BarController: {},
  BarElement: {},
  CategoryScale: {},
  LinearScale: {},
  Tooltip: {},
}))
const range = { from: '2026-09-01', to: '2026-09-30', timezone: 'Asia/Ho_Chi_Minh' }
const stats = {
  shop: { id: '2', name: 'My shop', status: 'ACTIVE' },
  range,
  total_products: 8,
  active_products: 3,
  pending_products: 2,
  sold_products: 1,
  product_counts: { DRAFT: 1, ACTIVE: 3, PENDING: 2, SOLD: 1, HIDDEN: 1, REJECTED: 0 },
  total_orders: 10,
  pending_orders: 2,
  completed_orders: 3,
  cancelled_orders: 1,
  order_counts: {
    PENDING: 2,
    CONFIRMED: 1,
    PROCESSING: 1,
    SHIPPING: 2,
    COMPLETED: 3,
    CANCELLED: 1,
  },
  total_revenue: '1250000.10',
  completed_in_range: 2,
  recent_orders: [
    {
      id: '17',
      code: 'SH-123',
      buyer: { name: 'Buyer' },
      total_amount: '100.10',
      status: 'COMPLETED',
      created_at: '2026-09-20T00:00:00Z',
    },
  ],
}
const revenue = {
  range,
  group_by: 'day',
  total_revenue: '1250000.10',
  completed_orders: 2,
  series: [
    { period: '2026-09-01', revenue: '0.10', completed_orders: 1 },
    { period: '2026-09-02', revenue: '1250000.00', completed_orders: 1 },
  ],
}
const wrappers = [],
  instances = []
async function setup({ guest = false } = {}) {
  const pinia = createPinia()
  instances.push(pinia)
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.initialized = true
  auth.user = guest ? null : { id: '4', status: 'ACTIVE', role: 'USER' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: ['/seller/dashboard', '/seller/orders/:id', '/seller/shop'].map((path) => ({
      path,
      component: { template: '<div />' },
    })),
  })
  await router.push('/seller/dashboard')
  const wrapper = mount(SellerDashboardView, { global: { plugins: [pinia, router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, auth }
}
beforeEach(() => {
  vi.restoreAllMocks()
  charts.length = 0
  vi.spyOn(dashboardApi, 'getSellerDashboard').mockResolvedValue(structuredClone(stats))
  vi.spyOn(dashboardApi, 'getSellerRevenue').mockResolvedValue(structuredClone(revenue))
})
afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  instances.splice(0).forEach(disposePinia)
})
it('loads metrics and explains inventory, creation and completion scopes', async () => {
  const { wrapper } = await setup()
  for (const field of [
    'total_products',
    'active_products',
    'pending_products',
    'sold_products',
    'total_orders',
    'pending_orders',
    'completed_orders',
    'cancelled_orders',
  ])
    expect(wrapper.get(`[data-metric="${field}"]`).text()).toBe(String(stats[field]))
  expect(wrapper.get('[data-total-revenue]').text()).toBe(formatPrice(revenue.total_revenue))
  expect(wrapper.text()).toContain('Sản phẩm hiện tại')
  expect(wrapper.text()).toContain('Đơn được tạo trong kỳ')
  expect(wrapper.text()).toContain('Doanh thu từ đơn hoàn tất trong kỳ')
  expect(wrapper.text()).toContain('2026-09-01 → 2026-09-30')
  expect(wrapper.get('[data-recent-order] a').attributes('href')).toBe('/seller/orders/17')
  expect(wrapper.get('[data-recent-order]').text()).toContain('Buyer')
})
it('renders Chart.js series and exact decimal tooltip/table, updates and destroys chart', async () => {
  const series = [{ period: '2026-09', revenue: '20000000000000.08', completed_orders: 3 }]
  const wrapper = mount(RevenueChart, { props: { series } })
  const chart = charts[0]
  expect(chart.canvas.tagName).toBe('CANVAS')
  expect(chart.config.type).toBe('bar')
  expect(chart.data.labels).toEqual(['2026-09'])
  expect(chart.data.datasets[0].data).toEqual([20000000000000.08])
  expect(chart.data.datasets[0].backgroundColor).toBeTruthy()
  expect(chart.config.options.scales.y.grid.color).toBeTruthy()
  expect(chart.config.options.plugins.tooltip.callbacks.label({ dataIndex: 0 })).toBe(
    formatPrice(series[0].revenue),
  )
  expect(wrapper.get('table').text()).toContain(formatPrice(series[0].revenue))
  await wrapper.setProps({ series: revenue.series })
  expect(chart.update).toHaveBeenCalledWith('none')
  expect(chart.data.labels).toEqual(['2026-09-01', '2026-09-02'])
  expect(chart.config.options.plugins.tooltip.callbacks.label({ dataIndex: 0 })).toBe('0,10 ₫')
  wrapper.unmount()
  expect(chart.destroy).toHaveBeenCalledOnce()
})
it('computes presets using Vietnam calendar across UTC midnight and month boundaries', () => {
  const clock = new Date('2026-09-30T18:00:00Z')
  expect(reportPreset('7', clock)).toEqual({ from: '2026-09-25', to: '2026-10-01' })
  expect(reportPreset('30', clock)).toEqual({ from: '2026-09-02', to: '2026-10-01' })
  expect(reportPreset('month', clock)).toEqual({ from: '2026-10-01', to: '2026-10-01' })
})
it('applies seven day and current month presets to both endpoints', async () => {
  const { wrapper } = await setup()
  for (const preset of ['7', 'month']) {
    await wrapper.get('#dashboard-preset').setValue(preset)
    await flushPromises()
    expect(dashboardApi.getSellerDashboard).toHaveBeenLastCalledWith(reportPreset(preset))
    expect(dashboardApi.getSellerRevenue).toHaveBeenLastCalledWith({
      ...reportPreset(preset),
      group_by: 'day',
    })
  }
})
it('submits custom range and month grouping without shop/user fields', async () => {
  const { wrapper } = await setup()
  await wrapper.get('#dashboard-from').setValue('2026-01-01')
  await wrapper.get('#dashboard-to').setValue('2026-03-31')
  await wrapper.get('#dashboard-group').setValue('month')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(dashboardApi.getSellerDashboard).toHaveBeenLastCalledWith({
    from: '2026-01-01',
    to: '2026-03-31',
  })
  expect(dashboardApi.getSellerRevenue).toHaveBeenLastCalledWith({
    from: '2026-01-01',
    to: '2026-03-31',
    group_by: 'month',
  })
  expect(wrapper.get('#dashboard-preset').element.value).toBe('custom')
})
it('shows zero revenue and empty orders without inventing chart income', async () => {
  dashboardApi.getSellerDashboard.mockResolvedValue({ ...stats, recent_orders: [] })
  dashboardApi.getSellerRevenue.mockResolvedValue({
    ...revenue,
    total_revenue: '0.00',
    completed_orders: 0,
    series: [{ period: '2026-09-01', revenue: '0.00', completed_orders: 0 }],
  })
  const { wrapper } = await setup()
  expect(wrapper.get('[data-total-revenue]').text()).toBe('0 ₫')
  expect(wrapper.text()).toContain('Chưa có đơn hoàn tất')
  expect(wrapper.text()).toContain('Chưa có đơn hàng')
  expect(charts[0].data.datasets[0].data).toEqual([0])
})
it('shows API failure and retries via the filter form', async () => {
  dashboardApi.getSellerRevenue.mockRejectedValueOnce(new Error('Offline'))
  const { wrapper } = await setup()
  expect(wrapper.text()).toContain('Không thể kết nối')
  expect(wrapper.find('[data-total-revenue]').exists()).toBe(false)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.find('[data-total-revenue]').exists()).toBe(true)
})
it('shows date validation messages and hides previous metrics after failed refresh', async () => {
  const { wrapper } = await setup()
  dashboardApi.getSellerRevenue.mockRejectedValueOnce({
    response: { status: 422, data: { errors: { to: ['Range exceeds 366 days'] } } },
  })
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.text()).toContain('Ngày kết thúc không hợp lệ')
  expect(wrapper.find('[data-metric]').exists()).toBe(false)
})
it('guides a user without a shop to the existing shop page', async () => {
  dashboardApi.getSellerDashboard.mockRejectedValue({ response: { status: 404 } })
  const { wrapper } = await setup()
  expect(wrapper.text()).toContain('Không tìm thấy gian hàng của bạn')
  expect(wrapper.get('a').attributes('href')).toBe('/seller/shop')
})
it('does not request reports for guests and protects the actual seller route', async () => {
  const { auth } = await setup({ guest: true })
  expect(dashboardApi.getSellerDashboard).not.toHaveBeenCalled()
  expect(dashboardApi.getSellerRevenue).not.toHaveBeenCalled()
  const route = applicationRouter.resolve('/seller/dashboard')
  expect(route.meta.requiresAuth).toBe(true)
  expect(await createAuthGuard(auth)(route)).toEqual({
    name: 'login',
    query: { redirect: route.fullPath },
  })
  auth.user = { id: '4', role: 'USER', status: 'ACTIVE' }
  expect(await createAuthGuard(auth)(route)).toBe(true)
})
it('discards an in-flight previous account report after logout', async () => {
  let finish
  dashboardApi.getSellerDashboard.mockImplementationOnce(
    () =>
      new Promise((resolve) => {
        finish = resolve
      }),
  )
  const { wrapper, auth } = await setup()
  expect(wrapper.get('button').attributes('disabled')).toBeDefined()
  auth.user = null
  await flushPromises()
  finish(stats)
  await flushPromises()
  expect(wrapper.find('[data-metric]').exists()).toBe(false)
  expect(wrapper.text()).not.toContain('My shop')
  expect(charts).toHaveLength(0)
})
it('replaces reports on account switch and never renders the old response', async () => {
  let finish
  dashboardApi.getSellerDashboard.mockImplementationOnce(
    () =>
      new Promise((resolve) => {
        finish = resolve
      }),
  )
  const { wrapper, auth } = await setup()
  dashboardApi.getSellerDashboard.mockResolvedValue({
    ...stats,
    shop: { ...stats.shop, name: 'Second shop' },
  })
  auth.user = { id: '9', role: 'USER', status: 'ACTIVE' }
  await flushPromises()
  finish(stats)
  await flushPromises()
  expect(wrapper.text()).toContain('Second shop')
  expect(wrapper.text()).not.toContain('My shop')
})
it('API adapter sends read-only filter params to the authenticated endpoints', async () => {
  vi.restoreAllMocks()
  const get = vi.spyOn(axios, 'get').mockResolvedValue({ data: { data: stats } })
  const params = { from: range.from, to: range.to }
  expect(await dashboardApi.getSellerDashboard(params)).toEqual(stats)
  expect(get).toHaveBeenCalledWith('/api/v1/seller/dashboard', { params })
  await dashboardApi.getSellerRevenue({ ...params, group_by: 'month' })
  expect(get).toHaveBeenLastCalledWith('/api/v1/seller/dashboard/revenue', {
    params: { ...params, group_by: 'month' },
  })
})
