import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { beforeEach, afterEach, it, expect, vi } from 'vitest'
import { useAuthStore } from '../stores/auth'
import { createAuthGuard } from '../router/authGuard'
import appRouter from '../router'
import * as adminApi from '../api/admin.api'
import * as productApi from '../api/product.api'
import * as reportApi from '../api/report.api'
import { getReconciliationOutcomeLabel } from '../utils/labels'
import { safeNotificationTarget } from '../composables/useNotifications'
import ActionCenter from '../views/AdminActionCenterView.vue'
import Payments from '../views/AdminPaymentsView.vue'
import Auctions from '../views/AdminAuctionsView.vue'
import Products from '../views/AdminProductsView.vue'
import Users from '../views/AdminUsersView.vue'
import Reports from '../views/ReportsView.vue'
import AdminShell from '../components/admin/AdminShell.vue'
import NotificationList from '../components/notifications/NotificationList.vue'

vi.mock('../composables/auctionEcho', () => ({
  realtimeConnection: { value: 'disabled' },
  subscribeRealtime: () => () => {},
}))

const counts = (extra = {}) => ({
  server_time: '2026-10-12T10:00:00.000000Z',
  flagged_min_reports: 3,
  flagged_window_days: 30,
  overdue_grace_minutes: 2,
  shops_pending: 2,
  products_pending: 0,
  reports_open: 5,
  reports_pending: 4,
  reports_in_review: 1,
  reports_by_target: { PRODUCT: 1, SHOP: 3, VIDEO_COMMENT: 1 },
  users_flagged: 1,
  payments_reconciliation: 2,
  auctions_awaiting_checkout: 0,
  auctions_overdue: 0,
  video_comments_reported: 1,
  ...extra,
})
const payment = (extra = {}) => ({
  id: '7',
  attempt_number: 1,
  method: 'ZALOPAY',
  status: 'FAILED',
  amount: '250000.00',
  currency: 'VND',
  transaction_reference: '261012_abc',
  gateway_transaction_id: '99887766',
  created_at: '2026-10-12T08:00:00Z',
  reconciliation_required: true,
  reconciliation_open: true,
  reconciliation_resolved_at: null,
  reconciliation_note: null,
  reconciliation_resolved_by: null,
  can_resolve: true,
  can_keep_paid: true,
  order: {
    id: '3',
    order_code: 'SHM-D5TEST',
    total_amount: '250000.00',
    overall_status: 'PENDING',
    buyer: { id: '4', name: 'Người mua A' },
    shop_order_statuses: ['PENDING'],
    has_other_paid_payment: false,
  },
  ...extra,
})
const auction = (extra = {}) => ({
  id: '9',
  product_id: '3',
  product: { id: '3', name: 'Máy ảnh phim', slug: 'may-anh-phim' },
  seller: { id: '5', name: 'Người bán B' },
  status: 'LIVE',
  settlement_state: null,
  current_price: '120000.00',
  bid_count: 3,
  ends_at: '2026-10-12T12:00:00Z',
  overdue: false,
  can_cancel: true,
  ...extra,
})
const page = (data) => ({ data, meta: { current_page: 1, last_page: 1, total: data.length } })
const wrappers = []
async function show(component, path, { role = 'ADMIN', props = {} } = {}) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = { id: '1', name: 'Quản trị', role, status: 'ACTIVE' }
  auth.initialized = true
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }],
  })
  await router.push(path)
  await router.isReady()
  const wrapper = mount(component, {
    props,
    attachTo: document.body,
    global: { plugins: [pinia, router] },
  })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router }
}
const dialog = () => document.querySelector('[role="dialog"]')
beforeEach(() => vi.restoreAllMocks())
afterEach(() => wrappers.splice(0).forEach((w) => w.unmount()))

it('guards every new admin route and keeps the old dashboard route', async () => {
  const routes = Object.fromEntries(appRouter.options.routes.map((r) => [r.path, r]))
  for (const path of ['/admin', '/admin/payments', '/admin/auctions', '/admin/dashboard'])
    expect(routes[path].meta).toMatchObject({ requiresAuth: true, requiresAdmin: true })
  const guard = (user) =>
    createAuthGuard({
      initialize: async () => {},
      isAuthenticated: !!user,
      isAdmin: user?.role === 'ADMIN',
    })
  for (const path of ['/admin', '/admin/payments', '/admin/auctions']) {
    const to = { meta: routes[path].meta, fullPath: path }
    expect(await guard(null)(to)).toMatchObject({ name: 'login', query: { redirect: path } })
    expect(await guard({ role: 'USER' })(to)).toEqual({ name: 'forbidden' })
    expect(await guard({ role: 'ADMIN' })(to)).toBe(true)
  }
})

it('action center shows Vietnamese cards that link to prefiltered lists and highlights open work', async () => {
  vi.spyOn(adminApi, 'getAdminActionCenter').mockResolvedValue(counts())
  const { wrapper } = await show(ActionCenter, '/admin')
  const links = {
    shops_pending: '/admin/shops?status=PENDING',
    products_pending: '/admin/products?status=PENDING',
    reports_pending: '/admin/reports?status=PENDING',
    reports_in_review: '/admin/reports?status=IN_REVIEW',
    video_comments_reported: '/admin/reports?target_type=VIDEO_COMMENT',
    users_flagged: '/admin/users?flagged=1',
    payments_reconciliation: '/admin/payments?reconciliation=open',
    auctions_awaiting_checkout: '/admin/auctions?settlement=AWAITING_CHECKOUT',
    auctions_overdue: '/admin/auctions?overdue=1',
  }
  for (const [field, href] of Object.entries(links)) {
    const card = wrapper.get(`[data-card="${field}"]`)
    expect(card.attributes('href')).toBe(href)
    expect(card.get('[data-count]').text()).toBe(String(counts()[field]))
    expect(card.classes('action-card-alert')).toBe(counts()[field] > 0)
  }
  expect(wrapper.text()).toContain('Gian hàng chờ duyệt')
  expect(wrapper.text()).toContain('Từ 3 báo cáo mở trong 30 ngày.')
  expect(wrapper.get('[data-target-count="SHOP"]').text()).toBe('3')
  expect(wrapper.get('[data-count="reports_open"]').text()).toBe('5')
  expect(wrapper.find('[data-overdue-banner]').exists()).toBe(false)
  expect(wrapper.get('a[href="/admin/dashboard"]').exists()).toBe(true)
  expect(wrapper.text()).not.toMatch(/\b(PENDING|IN_REVIEW|VIDEO_COMMENT|AWAITING_CHECKOUT)\b/)
})

it('action center warns about a stopped scheduler and refreshes on demand', async () => {
  const spy = vi
    .spyOn(adminApi, 'getAdminActionCenter')
    .mockResolvedValueOnce(counts({ auctions_overdue: 2 }))
    .mockResolvedValueOnce(counts({ auctions_overdue: 0, shops_pending: 0 }))
  const { wrapper } = await show(ActionCenter, '/admin')
  expect(wrapper.get('[data-overdue-banner]').text()).toBe(
    'Có phiên đấu giá quá hạn chưa được kết thúc. Kiểm tra tiến trình schedule:work.',
  )
  expect(wrapper.get('[data-card="auctions_overdue"]').classes()).toContain('action-card-alert')
  await wrapper.get('header button, .btn-outline-primary').trigger('click')
  await flushPromises()
  expect(spy).toHaveBeenCalledTimes(2)
  expect(wrapper.find('[data-overdue-banner]').exists()).toBe(false)
  expect(wrapper.get('[data-count="shops_pending"]').text()).toBe('0')
})

it('action center reports a load failure without stale numbers', async () => {
  vi.spyOn(adminApi, 'getAdminActionCenter').mockRejectedValue({ response: { status: 403 } })
  const { wrapper } = await show(ActionCenter, '/admin')
  expect(wrapper.text()).toContain('Bạn không có quyền')
  expect(wrapper.find('[data-card]').exists()).toBe(false)
})

it('admin shell lists the new sections and marks only the exact action center link active', async () => {
  const { wrapper, router } = await show(AdminShell, '/admin')
  expect(wrapper.get('nav a[href="/admin"]').text()).toContain('Việc cần xử lý')
  expect(wrapper.get('nav a[href="/admin/payments"]').text()).toContain('Đối soát thanh toán')
  expect(wrapper.get('nav a[href="/admin/auctions"]').text()).toContain('Phiên đấu giá')
  expect(wrapper.findAll('nav a.active').map((a) => a.attributes('href'))).toEqual(['/admin'])
  await router.push('/admin/auctions?overdue=1')
  await flushPromises()
  expect(wrapper.findAll('nav a.active').map((a) => a.attributes('href'))).toEqual([
    '/admin/auctions',
  ])
})

it('payment queue requests the open filter, shows safe identifiers and Vietnamese labels', async () => {
  const list = vi.spyOn(adminApi, 'listAdminPayments').mockResolvedValue(page([payment()]))
  vi.spyOn(adminApi, 'getAdminPayment').mockResolvedValue(payment())
  const { wrapper } = await show(Payments, '/admin/payments?reconciliation=open')
  expect(list).toHaveBeenCalledWith({ reconciliation: 'open', page: 1 })
  const row = wrapper.get('[data-payment="7"]')
  expect(row.text()).toContain('SHM-D5TEST')
  expect(row.text()).toContain('Thanh toán thất bại')
  expect(row.text()).toContain('Chưa đối soát')
  await row.get('button').trigger('click')
  await flushPromises()
  const detail = wrapper.get('[data-payment-detail]')
  expect(detail.text()).toContain('99887766')
  expect(detail.text()).toContain('ZaloPay')
  expect(wrapper.text()).not.toMatch(/\b(FAILED|REFUNDED_OUTSIDE|KEEP_PAID|ZALOPAY)\b/)
  expect(wrapper.html()).not.toContain('order_url')
})

it('refund outcome needs a note and a second explicit confirmation before it is sent', async () => {
  vi.spyOn(adminApi, 'listAdminPayments').mockResolvedValue(page([payment()]))
  const resolve = vi
    .spyOn(adminApi, 'resolveReconciliation')
    .mockResolvedValue(payment({ status: 'REFUNDED', reconciliation_open: false, can_resolve: false }))
  const { wrapper } = await show(Payments, '/admin/payments')
  await wrapper.get('[data-resolve="7"]').trigger('click')
  await flushPromises()
  const box = dialog()
  expect(box.getAttribute('aria-modal')).toBe('true')
  expect(box.textContent).toContain('Đã hoàn tiền ngoài hệ thống')
  expect(box.textContent).toContain('Giữ khoản thanh toán')
  const submit = box.querySelector('#reconciliation-confirm')
  expect(submit.disabled).toBe(true)
  const note = box.querySelector('#reconciliation-note')
  note.value = '  Hoàn qua cổng, mã RF-01  '
  note.dispatchEvent(new Event('input'))
  await flushPromises()
  expect(submit.textContent.trim()).toBe('Tiếp tục')
  box.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))
  await flushPromises()
  expect(resolve).not.toHaveBeenCalled()
  expect(box.querySelector('[data-confirm-step]').textContent).toContain('không tự hoàn tiền')
  expect(submit.textContent.trim()).toBe('Tôi đã hoàn tiền, ghi nhận')
  box.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))
  await flushPromises()
  expect(resolve).toHaveBeenCalledWith('7', {
    outcome: 'REFUNDED_OUTSIDE',
    note: 'Hoàn qua cổng, mã RF-01',
    expected_status: 'FAILED',
  })
  expect(dialog()).toBe(null)
  expect(wrapper.text()).toContain('Đã ghi nhận đối soát: Đã hoàn tiền ngoài hệ thống.')
})

it('keep-paid is sent in one step, is disabled when impossible, and a conflict keeps the dialog open', async () => {
  vi.spyOn(adminApi, 'listAdminPayments').mockResolvedValue(
    page([payment(), payment({ id: '8', can_keep_paid: false })]),
  )
  const resolve = vi
    .spyOn(adminApi, 'resolveReconciliation')
    .mockRejectedValueOnce({ response: { status: 409, data: {} } })
  const { wrapper } = await show(Payments, '/admin/payments')
  await wrapper.get('[data-resolve="8"]').trigger('click')
  await flushPromises()
  expect(dialog().querySelector('#outcome-keep').disabled).toBe(true)
  expect(dialog().textContent).toContain('Không khả dụng')
  dialog().querySelector('.ui-dialog-header button').click()
  await flushPromises()
  expect(dialog()).toBe(null)

  await wrapper.get('[data-resolve="7"]').trigger('click')
  await flushPromises()
  const box = dialog()
  const keep = box.querySelector('#outcome-keep')
  keep.checked = true
  keep.dispatchEvent(new Event('change'))
  const note = box.querySelector('#reconciliation-note')
  note.value = 'Đơn còn hiệu lực'
  note.dispatchEvent(new Event('input'))
  await flushPromises()
  expect(box.querySelector('#reconciliation-confirm').textContent.trim()).toBe('Ghi nhận đối soát')
  box.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))
  await flushPromises()
  expect(resolve).toHaveBeenCalledWith('7', {
    outcome: 'KEEP_PAID',
    note: 'Đơn còn hiệu lực',
    expected_status: 'FAILED',
  })
  expect(dialog()).not.toBe(null)
  expect(dialog().querySelector('[role="alert"]').textContent).toContain('Không thể giữ khoản')
})

it('resolved payments show who settled them and offer no further action', async () => {
  const done = payment({
    status: 'REFUNDED',
    reconciliation_open: false,
    can_resolve: false,
    reconciliation_resolved_at: '2026-10-12T09:00:00Z',
    reconciliation_note: 'Đã hoàn, mã RF-01',
    reconciliation_resolved_by: { id: '1', name: 'Quản trị' },
  })
  vi.spyOn(adminApi, 'listAdminPayments').mockResolvedValue(page([done]))
  vi.spyOn(adminApi, 'getAdminPayment').mockResolvedValue(done)
  const { wrapper } = await show(Payments, '/admin/payments?reconciliation=resolved')
  expect(adminApi.listAdminPayments).toHaveBeenCalledWith({ reconciliation: 'resolved', page: 1 })
  expect(wrapper.find('[data-resolve]').exists()).toBe(false)
  await wrapper.get('[data-payment="7"] button').trigger('click')
  await flushPromises()
  expect(wrapper.get('[data-payment-detail]').text()).toContain('Đã hoàn, mã RF-01')
  expect(wrapper.get('[data-payment-detail]').text()).toContain('Đã hoàn tiền')
})

it('auction list sends its filters and renders settlement and overdue state in Vietnamese', async () => {
  const list = vi.spyOn(adminApi, 'listAdminAuctions').mockResolvedValue(
    page([
      auction({ overdue: true }),
      auction({ id: '10', status: 'ENDED', settlement_state: 'AWAITING_CHECKOUT', can_cancel: false }),
    ]),
  )
  const { wrapper, router } = await show(Auctions, '/admin/auctions?overdue=1&status=LIVE')
  expect(list).toHaveBeenCalledWith({ status: 'LIVE', overdue: 1, page: 1 })
  expect(wrapper.get('[data-auction="9"]').text()).toContain('Đang đấu giá')
  expect(wrapper.get('[data-auction="9"] [data-overdue]').text()).toContain('Quá hạn')
  expect(wrapper.get('[data-auction="10"]').text()).toContain('Chờ người thắng hoàn tất đơn')
  expect(wrapper.find('[data-cancel="10"]').exists()).toBe(false)
  expect(wrapper.text()).not.toMatch(/\b(LIVE|ENDED|AWAITING_CHECKOUT|SCHEDULED)\b/)
  await wrapper.get('[name="settlement"]').setValue('CHECKOUT_EXPIRED')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(router.currentRoute.value.query).toEqual({
    status: 'LIVE',
    settlement: 'CHECKOUT_EXPIRED',
    overdue: '1',
  })
  await router.push('/admin/auctions?auction=9')
  await flushPromises()
  expect(list).toHaveBeenLastCalledWith({ auction: '9', page: 1 })
  expect(wrapper.get('[data-highlight-note]').text()).toContain('phiên đấu giá số 9')
})

it('cancelling an auction warns about its bids and requires a reason', async () => {
  vi.spyOn(adminApi, 'listAdminAuctions').mockResolvedValue(page([auction()]))
  const cancel = vi.spyOn(adminApi, 'cancelAdminAuction').mockResolvedValue(auction({ status: 'CANCELLED' }))
  const { wrapper } = await show(Auctions, '/admin/auctions')
  await wrapper.get('[data-cancel="9"]').trigger('click')
  await flushPromises()
  const box = dialog()
  expect(box.querySelector('[data-bid-warning]').textContent).toContain('3 lượt đặt giá')
  const confirm = box.querySelector('button[type="submit"]')
  expect(confirm.disabled).toBe(true)
  box.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))
  await flushPromises()
  expect(cancel).not.toHaveBeenCalled()
  const reason = box.querySelector('#cancel-auction-reason')
  reason.value = '  Hàng giả  '
  reason.dispatchEvent(new Event('input'))
  await flushPromises()
  box.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))
  await flushPromises()
  expect(cancel).toHaveBeenCalledWith('9', { reason: 'Hàng giả', expected_status: 'LIVE' })
  expect(dialog()).toBe(null)
  expect(wrapper.text()).toContain('Đã hủy phiên đấu giá Máy ảnh phim')
})

it('a stale auction cancellation keeps the dialog open and Escape closes it', async () => {
  vi.spyOn(adminApi, 'listAdminAuctions').mockResolvedValue(page([auction({ bid_count: 0 })]))
  vi.spyOn(adminApi, 'cancelAdminAuction').mockRejectedValue({ response: { status: 409 } })
  const { wrapper } = await show(Auctions, '/admin/auctions')
  await wrapper.get('[data-cancel="9"]').trigger('click')
  await flushPromises()
  expect(dialog().querySelector('[data-bid-warning]').textContent).toContain('chưa có lượt đặt giá')
  const reason = dialog().querySelector('#cancel-auction-reason')
  reason.value = 'Vi phạm'
  reason.dispatchEvent(new Event('input'))
  await flushPromises()
  dialog().querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))
  await flushPromises()
  expect(dialog().querySelector('[role="alert"]').textContent).toContain('Phiên đã thay đổi trạng thái')
  dialog().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
  await flushPromises()
  expect(dialog()).toBe(null)
})

const managed = (extra = {}) => ({
  id: '10',
  name: 'Đồng hồ cũ',
  slug: 'dong-ho-cu',
  description: 'Mô tả',
  price: '500000.00',
  stock_quantity: 2,
  condition: 'GOOD',
  status: 'ACTIVE',
  can_moderate: true,
  shop: { id: '5', name: 'Gian hàng' },
  category: { id: '3', name: 'Đồng hồ' },
  images: [],
  ...extra,
})

it('taking down a product with a running auction explains the conflict and links to the auction', async () => {
  vi.spyOn(productApi, 'listAdminProducts').mockResolvedValue(page([managed()]))
  vi.spyOn(productApi, 'getAdminProduct').mockResolvedValue(managed())
  vi.spyOn(productApi, 'moderateProduct').mockRejectedValue({
    response: { status: 409, data: { message: 'x', auction_id: '42' } },
  })
  const { wrapper } = await show(Products, '/admin/products?status=ACTIVE&inspect=10')
  expect(productApi.listAdminProducts).toHaveBeenCalledWith(
    expect.objectContaining({ status: 'ACTIVE' }),
  )
  await wrapper.get('[name="reason"]').setValue('Vi phạm')
  await wrapper.get('[data-target="REJECTED"]').trigger('click')
  await flushPromises()
  const notice = wrapper.get('[data-auction-conflict]')
  expect(notice.text()).toContain('Sản phẩm đang có phiên đấu giá. Hãy hủy phiên trước khi gỡ tin.')
  expect(notice.get('a').attributes('href')).toBe('/admin/auctions?auction=42')
})

it('an ordinary moderation conflict shows no auction link', async () => {
  vi.spyOn(productApi, 'listAdminProducts').mockResolvedValue(page([managed()]))
  vi.spyOn(productApi, 'getAdminProduct').mockResolvedValue(managed())
  vi.spyOn(productApi, 'moderateProduct').mockRejectedValue({ response: { status: 409, data: {} } })
  const { wrapper } = await show(Products, '/admin/products?inspect=10')
  await wrapper.get('[name="reason"]').setValue('Vi phạm')
  await wrapper.get('[data-target="REJECTED"]').trigger('click')
  await flushPromises()
  expect(wrapper.find('[data-auction-conflict]').exists()).toBe(false)
})

it('users list honours the flagged link and the checkbox', async () => {
  const list = vi.spyOn(adminApi, 'listAdminUsers').mockResolvedValue(page([]))
  const { wrapper } = await show(Users, '/admin/users?flagged=1')
  expect(list).toHaveBeenLastCalledWith(expect.objectContaining({ flagged: 1 }))
  expect(wrapper.get('#users-flagged').element.checked).toBe(true)
  expect(wrapper.text()).toContain('Chỉ tài khoản bị báo cáo nhiều')
  await wrapper.get('#users-flagged').setValue(false)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(list.mock.calls.at(-1)[0]).not.toHaveProperty('flagged')
})

const report = (extra = {}) => ({
  id: '1',
  target: { type: 'SHOP', id: '5', name: 'Gian hàng X', label: 'Gian hàng', available: true },
  reason: 'SCAM',
  reason_label: 'Có dấu hiệu lừa đảo',
  description: null,
  status: 'RESOLVED',
  status_label: 'Đã xử lý',
  created_at: '2026-10-10T01:00:00Z',
  handled_at: '2026-10-11T01:00:00Z',
  admin_note: 'Ghi chú cũ',
  can_moderate: true,
  reporter: { id: '2', name: 'Người báo cáo' },
  handler: null,
  ...extra,
})

it('a closed report still lets the admin edit the internal note without changing status', async () => {
  vi.spyOn(reportApi, 'listReports').mockResolvedValue(page([report()]))
  vi.spyOn(reportApi, 'getReport').mockResolvedValue(report())
  const update = vi
    .spyOn(reportApi, 'updateReportStatus')
    .mockResolvedValue(report({ admin_note: 'Ghi chú mới' }))
  const { wrapper } = await show(Reports, '/admin/reports?status=RESOLVED&report=1', {
    props: { admin: true },
  })
  expect(reportApi.listReports).toHaveBeenCalledWith(true, expect.objectContaining({ status: 'RESOLVED' }))
  const options = wrapper.findAll('[name="next_status"] option')
  expect(options.map((o) => o.attributes('value'))).toEqual(['RESOLVED'])
  expect(options[0].text()).toContain('chỉ lưu ghi chú')
  expect(wrapper.get('article form button').text()).toBe('Lưu ghi chú')
  await wrapper.get('[name="admin_note"]').setValue('Ghi chú mới')
  await wrapper.get('article form').trigger('submit')
  await flushPromises()
  expect(update).toHaveBeenCalledWith('1', {
    expected_status: 'RESOLVED',
    status: 'RESOLVED',
    admin_note: 'Ghi chú mới',
  })
  expect(wrapper.text()).toContain('Đã lưu ghi chú nội bộ.')
})

it('reports against the admin own content hide every moderation control', async () => {
  const own = report({ status: 'PENDING', status_label: 'Chờ xử lý', can_moderate: false })
  vi.spyOn(reportApi, 'listReports').mockResolvedValue(page([own]))
  vi.spyOn(reportApi, 'getReport').mockResolvedValue(own)
  const update = vi.spyOn(reportApi, 'updateReportStatus')
  const { wrapper } = await show(Reports, '/admin/reports?report=1', { props: { admin: true } })
  expect(wrapper.get('[data-own-report]').text()).toContain('nội dung của chính bạn')
  expect(wrapper.find('article form').exists()).toBe(false)
  expect(wrapper.find('[name="next_status"]').exists()).toBe(false)
  expect(wrapper.text()).toContain('Ghi chú cũ')
  expect(update).not.toHaveBeenCalled()
})

it('labels reconciliation outcomes and new notification targets safely', () => {
  expect(getReconciliationOutcomeLabel('REFUNDED_OUTSIDE')).toBe('Đã hoàn tiền ngoài hệ thống')
  expect(getReconciliationOutcomeLabel('KEEP_PAID')).toBe('Giữ khoản thanh toán')
  expect(getReconciliationOutcomeLabel('SOMETHING')).toBe('Không xác định')
  expect(safeNotificationTarget('/auctions')).toBe('/auctions')
  expect(safeNotificationTarget('/auctions/9')).toBe('/auctions/9')
  expect(safeNotificationTarget('/auctions/../admin')).toBe('/notifications')
  const list = mount(NotificationList, {
    props: {
      rows: [
        {
          id: 'a',
          type: 'AUCTION_CHECKOUT_UNAVAILABLE',
          message: 'Phiên đấu giá không thể hoàn tất.',
          created_at: '2026-10-12T08:00:00Z',
          read_at: null,
        },
      ],
    },
  })
  wrappers.push(list)
  expect(list.text()).toContain('Xem các phiên khác')
})
