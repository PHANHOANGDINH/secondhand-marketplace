import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { ref } from 'vue'
import { useAuthStore } from '../stores/auth'
import { createAuthGuard } from '../router/authGuard'
import NotificationList from '../components/notifications/NotificationList.vue'
import ReportDialog from '../components/ReportDialog.vue'
import ReportsView from '../views/ReportsView.vue'
import ProductView from '../views/ProductView.vue'
import ShopView from '../views/ShopView.vue'
import VideoComments from '../components/home/VideoComments.vue'
import * as api from '../api/report.api'
import * as products from '../api/product.api'
import * as shops from '../api/shop.api'
import * as comments from '../api/videoComment.api'
import { safeNotificationTarget } from '../composables/useNotifications'
vi.mock('../composables/useVideoComments', () => ({
  useVideoComments: () => ({
    comments: ref([
      {
        id: '9',
        body: 'Nội dung bình luận',
        author: { name: 'Người mua' },
        created_at: '2026-10-09T10:00:00Z',
      },
    ]),
    loading: ref(false),
    error: ref(''),
    actionError: ref(''),
    change: ref(0),
    connection: ref('connected'),
  }),
}))
const row = (extra = {}) => ({
  id: '1',
  target: {
    id: '5',
    type: 'PRODUCT',
    label: 'Sản phẩm',
    name: 'Máy ảnh',
    available: true,
    route: '/admin/products?inspect=5',
    status: 'ACTIVE',
  },
  reason: 'SCAM',
  reason_label: 'Có dấu hiệu lừa đảo',
  status: 'PENDING',
  status_label: 'Chờ xử lý',
  description: 'Thông tin cần kiểm tra',
  created_at: '2026-10-09T10:00:00Z',
  handled_at: null,
  ...extra,
})
const collection = (data) => ({ data, meta: { total: data.length, current_page: 1, last_page: 1 } })
const wrappers = []
async function setup(
  component = ReportDialog,
  props = { targetType: 'PRODUCT', targetId: '5', label: 'Báo cáo sản phẩm' },
  user = { id: '10', role: 'USER' },
  path = '/products/may-anh?q=demo',
  stubs = {},
) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = user
  auth.initialized = true
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
    ],
  })
  await router.push(path)
  await router.isReady()
  const wrapper = mount(component, {
    props,
    attachTo: document.body,
    global: { plugins: [pinia, router], stubs },
  })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, auth, router }
}
beforeEach(() => {
  for (const name of ['showModal', 'close'])
    if (!HTMLDialogElement.prototype[name])
      Object.defineProperty(HTMLDialogElement.prototype, name, {
        configurable: true,
        writable: true,
        value() {},
      })
  vi.spyOn(HTMLDialogElement.prototype, 'showModal').mockImplementation(function () {
    this.setAttribute('open', '')
  })
  vi.spyOn(HTMLDialogElement.prototype, 'close').mockImplementation(function () {
    this.removeAttribute('open')
  })
  vi.spyOn(api, 'createReport').mockResolvedValue(row())
  vi.spyOn(api, 'listReports').mockResolvedValue(collection([row()]))
  vi.spyOn(api, 'getReport').mockResolvedValue(row())
  vi.spyOn(api, 'updateReportStatus').mockResolvedValue(
    row({ status: 'IN_REVIEW', status_label: 'Đang xem xét' }),
  )
  vi.spyOn(comments, 'hideVideoComment').mockResolvedValue()
})
afterEach(() => {
  wrappers.splice(0).forEach((w) => w.unmount())
  vi.restoreAllMocks()
  document.body.innerHTML = ''
})
it('sends the correct target and controlled reason, trims text, shows Vietnamese success', async () => {
  const { wrapper } = await setup()
  await wrapper.get('.report-trigger').trigger('click')
  expect(wrapper.get('dialog').attributes('open')).toBeDefined()
  expect(wrapper.get('dialog').attributes('aria-labelledby')).toBeTruthy()
  await wrapper.get('select').setValue('OTHER')
  expect(wrapper.get('textarea').attributes('required')).toBeDefined()
  await wrapper.get('textarea').setValue('  Chi tiết cần kiểm tra  ')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(api.createReport).toHaveBeenCalledWith({
    target_type: 'PRODUCT',
    target_id: '5',
    reason: 'OTHER',
    description: 'Chi tiết cần kiểm tra',
  })
  expect(wrapper.text()).toContain('Đã gửi báo cáo.')
  expect(wrapper.get('a[href="/reports"]').text()).toBe('Báo cáo của tôi')
})
it('does not send guest reports and preserves the current intended destination', async () => {
  const { wrapper, router } = await setup(undefined, undefined, null)
  await wrapper.get('.report-trigger').trigger('click')
  await flushPromises()
  expect(router.currentRoute.value.name).toBe('login')
  expect(router.currentRoute.value.query.redirect).toBe('/products/may-anh?q=demo')
  expect(api.createReport).not.toHaveBeenCalled()
})
it.each([
  [409, 'Bạn đã có báo cáo'],
  [429, 'Bạn thao tác quá nhanh'],
  [500, 'Không thể kết nối'],
])('presents safe Vietnamese report error for %s', async (status, message) => {
  api.createReport.mockRejectedValue({
    response: { status, data: { message: 'private raw error' } },
  })
  const { wrapper } = await setup()
  await wrapper.get('.report-trigger').trigger('click')
  await wrapper.get('select').setValue('SCAM')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.get('[role="alert"]').text()).toContain(message)
  expect(wrapper.text()).not.toContain('private raw error')
})
it('shows field validation and blocks duplicate requests while pending', async () => {
  let reject
  api.createReport.mockImplementation(
    () =>
      new Promise((_, fail) => {
        reject = fail
      }),
  )
  const { wrapper } = await setup()
  await wrapper.get('.report-trigger').trigger('click')
  await wrapper.get('select').setValue('OTHER')
  await wrapper.get('textarea').setValue('Test')
  await wrapper.get('form').trigger('submit')
  await wrapper.get('form').trigger('submit')
  expect(api.createReport).toHaveBeenCalledTimes(1)
  expect(wrapper.text()).toContain('Đang gửi…')
  reject({ response: { status: 422, data: { errors: { description: ['English validation'] } } } })
  await flushPromises()
  expect(wrapper.get('textarea').attributes('aria-invalid')).toBe('true')
  expect(wrapper.text()).toContain('tối đa 1.000 ký tự')
  expect(wrapper.text()).not.toContain('English validation')
})
it('uses the reusable dialog at Product, Shop, and Theater comment entry points', async () => {
  vi.spyOn(products, 'getProduct').mockResolvedValue({
    id: '5',
    name: 'Máy ảnh',
    price: '100000',
    stock_quantity: 1,
  })
  vi.spyOn(shops, 'getShop').mockResolvedValue({ id: '7', name: 'Gian hàng', description: 'Mô tả' })
  const product = await setup(ProductView, {}, undefined, '/products/demo', {
    ProductGallery: true,
    ProductReviews: true,
    ProductVideos: true,
    FavoriteButton: true,
    AddToCart: true,
    ChatSellerButton: true,
  })
  expect(product.wrapper.findComponent(ReportDialog).props()).toMatchObject({
    targetType: 'PRODUCT',
    targetId: '5',
    label: 'Báo cáo sản phẩm',
  })
  const shop = await setup(ShopView, {}, undefined, '/shops/demo', { ShopProducts: true })
  expect(shop.wrapper.findComponent(ReportDialog).props()).toMatchObject({
    targetType: 'SHOP',
    targetId: '7',
  })
  const comment = await setup(VideoComments, { videoId: '3' })
  expect(comment.wrapper.findComponent(ReportDialog).props()).toMatchObject({
    targetType: 'VIDEO_COMMENT',
    targetId: '9',
    label: 'Báo cáo bình luận',
  })
})
it('shows owner reports with Vietnamese states and no admin controls or notes', async () => {
  api.getReport.mockResolvedValue(row({ admin_note: 'Confidential internal note' }))
  const { wrapper } = await setup(ReportsView, {}, undefined, '/reports?report=1')
  expect(api.listReports).toHaveBeenCalledWith(
    false,
    expect.objectContaining({ page: 1, per_page: 20 }),
  )
  expect(wrapper.text()).toContain('Báo cáo của tôi')
  expect(wrapper.text()).toContain('Chờ xử lý')
  expect(wrapper.text()).not.toContain('Confidential internal note')
  expect(wrapper.find('[name="next_status"]').exists()).toBe(false)
})
it('shows loading, empty and errors without conflicting empty/error states', async () => {
  let resolve
  api.listReports.mockImplementationOnce(
    () =>
      new Promise((done) => {
        resolve = done
      }),
  )
  const { wrapper } = await setup(ReportsView, {}, undefined, '/reports')
  expect(wrapper.text()).toContain('Đang xử lý…')
  resolve(collection([]))
  await flushPromises()
  expect(wrapper.text()).toContain('Chưa có báo cáo')
  api.listReports.mockRejectedValueOnce({ response: { status: 500 } })
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.text()).toContain('Không thể kết nối')
  expect(wrapper.text()).not.toContain('Chưa có báo cáo')
})
it('filters admin reports and executes only valid transitions with private note', async () => {
  const { wrapper, router } = await setup(
    ReportsView,
    { admin: true },
    { id: '11', role: 'ADMIN' },
    '/admin/reports',
  )
  await wrapper.get('[name="target_type"]').setValue('SHOP')
  await wrapper.get('[name="status"]').setValue('PENDING')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(api.listReports).toHaveBeenLastCalledWith(
    true,
    expect.objectContaining({ target_type: 'SHOP', status: 'PENDING' }),
  )
  await router.replace('/admin/reports?report=1')
  await flushPromises()
  expect(wrapper.get('a[href="/admin/products?inspect=5"]').text()).toContain(
    'Mở kiểm duyệt sản phẩm',
  )
  expect(wrapper.findAll('[name="next_status"] option').map((o) => o.attributes('value'))).toEqual([
    'IN_REVIEW',
    'DISMISSED',
  ])
  await wrapper.get('[name="admin_note"]').setValue('Ghi chú nội bộ')
  await wrapper.get('article form').trigger('submit')
  await flushPromises()
  expect(api.updateReportStatus).toHaveBeenCalledWith('1', {
    expected_status: 'PENDING',
    status: 'IN_REVIEW',
    admin_note: 'Ghi chú nội bộ',
  })
  expect(wrapper.text()).toContain('Đã cập nhật kết quả')
})
it('reuses explicit comment hide confirmation without changing report status', async () => {
  const comment = row({
    target: {
      type: 'VIDEO_COMMENT',
      id: '9',
      name: 'Bình luận video #9',
      label: 'Bình luận video',
      available: true,
      status: 'PUBLISHED',
      context: { body: '<script>unsafe</script>', author: { name: 'Người mua' } },
    },
  })
  api.getReport.mockResolvedValue(comment)
  const { wrapper } = await setup(
    ReportsView,
    { admin: true },
    { id: '11', role: 'ADMIN' },
    '/admin/reports?report=1',
  )
  expect(wrapper.find('article script').exists()).toBe(false)
  await wrapper
    .findAll('button')
    .find((b) => b.text() === 'Ẩn bình luận')
    .trigger('click')
  expect(comments.hideVideoComment).not.toHaveBeenCalled()
  await wrapper
    .findAll('button')
    .find((b) => b.text() === 'Xác nhận ẩn')
    .trigger('click')
  await flushPromises()
  expect(comments.hideVideoComment).toHaveBeenCalledWith('9')
  expect(api.updateReportStatus).not.toHaveBeenCalled()
  expect(wrapper.text()).toContain('Trạng thái báo cáo chưa thay đổi')
})
it('protects new admin routes and accepts only the exact safe report notification target', async () => {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.initialized = true
  const target = { fullPath: '/admin/reports', meta: { requiresAuth: true, requiresAdmin: true } }
  expect(await createAuthGuard(auth)(target)).toMatchObject({ name: 'login' })
  auth.user = { id: '1', role: 'USER' }
  expect(await createAuthGuard(auth)(target)).toMatchObject({ name: 'forbidden' })
  auth.user = { id: '2', role: 'ADMIN' }
  expect(await createAuthGuard(auth)(target)).toBe(true)
  expect(safeNotificationTarget('/reports')).toBe('/reports')
  expect(safeNotificationTarget('//evil.test/reports')).toBe('/notifications')
  expect(safeNotificationTarget('/reports?redirect=evil')).toBe('/notifications')
})
it('ignores pending list results after account logout', async () => {
  let resolve
  api.listReports.mockImplementationOnce(
    () =>
      new Promise((done) => {
        resolve = done
      }),
  )
  const { wrapper, auth } = await setup(ReportsView, {}, undefined, '/reports')
  auth.user = null
  resolve(collection([row()]))
  await flushPromises()
  expect(wrapper.text()).not.toContain('Máy ảnh')
})

it('keeps Tab and Shift+Tab within the report dialog and restores trigger focus on cancel', async () => {
  const { wrapper } = await setup()
  await wrapper.get('.report-trigger').trigger('click')
  const controls = wrapper.findAll('dialog button').filter((button) => !button.element.disabled)
  const last = controls.at(-1),
    first = controls[0]
  last.element.focus()
  await last.trigger('keydown', { key: 'Tab' })
  expect(document.activeElement).toBe(first.element)
  await first.trigger('keydown', { key: 'Tab', shiftKey: true })
  expect(document.activeElement).toBe(last.element)
  await wrapper.get('dialog').trigger('cancel')
  expect(document.activeElement).toBe(wrapper.get('.report-trigger').element)
})

it('uses a report-specific notification action and meaningful mark-read label', () => {
  const wrapper = mount(NotificationList, {
    props: {
      rows: [
        {
          id: 'report-notification',
          type: 'REPORT_RESULT',
          message: 'Báo cáo của bạn đã được xử lý.',
          target_route: '/reports',
          created_at: '2026-10-09T10:00:00Z',
          read_at: null,
        },
      ],
    },
  })
  wrappers.push(wrapper)
  expect(wrapper.text()).toContain('Xem báo cáo')
  expect(wrapper.text()).not.toContain('Xem phiên')
  expect(wrapper.get('.notification-read').attributes('aria-label')).toContain(
    'Báo cáo của bạn đã được xử lý.',
  )
})
