import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import appRouter from '../router'
import * as domainApi from '../api/domain.api'
import * as shopApi from '../api/shop.api'
import * as catalog from '../api/catalogOptions'
import DefaultLayout from '../layouts/DefaultLayout.vue'
import StaticPageView from '../views/StaticPageView.vue'
import { areaPickerOpen, useArea } from '../composables/useArea'
vi.mock('../composables/useNotifications', async (importOriginal) => ({
  ...(await importOriginal()),
  provideNotifications: vi.fn(),
}))
vi.mock('../composables/useMessaging', async (importOriginal) => ({
  ...(await importOriginal()),
  provideMessaging: vi.fn(),
}))
const provinces = [
  { code: '01', name: 'Hà Nội', featured: true },
  { code: '31', name: 'Hải Phòng', featured: false },
  { code: '48', name: 'Đà Nẵng', featured: true },
  { code: '79', name: 'TP. Hồ Chí Minh', featured: true },
]
const wrappers = []
async function setup({ role = null, id = '5', path = '/' } = {}) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = role ? { id, name: 'Người dùng', role, status: 'ACTIVE' } : null
  auth.initialized = true
  if (role) for (const key of ['welcome', 'area']) localStorage.setItem(`passlai:first-time:${id}:${key}`, '1')
  const router = createRouter({ history: createMemoryHistory(), routes: appRouter.options.routes })
  await router.push(path)
  const wrapper = mount(DefaultLayout, {
    attachTo: document.body,
    global: {
      plugins: [pinia, router],
      stubs: {
        NotificationBell: true,
        ChatDrawer: true,
        ChatLauncher: true,
        CartLink: true,
        OutbidToasts: true,
        LogoutButton: true,
        AdminShell: { template: '<div><slot /></div>' },
        SellerShell: { template: '<div><slot /></div>' },
      },
    },
  })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router, auth }
}
const submitArea = async () => {
  document.querySelector('#area-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
  await flushPromises()
}
beforeEach(() => {
  vi.restoreAllMocks()
  localStorage.clear()
  areaPickerOpen.value = false
  useArea().setArea('')
  vi.spyOn(domainApi, 'read').mockImplementation(async (path) => (path === 'provinces' ? { data: provinces } : { data: [] }))
  vi.spyOn(shopApi, 'getSellerShop').mockResolvedValue({ id: '2', name: 'Tiệm', status: 'ACTIVE' })
  vi.spyOn(catalog, 'categoryOptions').mockResolvedValue([
    { value: '3', label: 'Điện tử' },
    { value: '4', label: '— Điện thoại' },
  ])
})
afterEach(() => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount()
  document.body.innerHTML = ''
})

it('home header shows page links; other pages show the compact search with the area button', async () => {
  const home = await setup()
  expect(home.wrapper.findAll('.marketplace-directory a').map((a) => a.text())).toEqual(['Trang chủ', 'Đấu giá', 'Video'])
  expect(home.wrapper.find('.marketplace-compact').exists()).toBe(false)
  expect(home.wrapper.find('.mode-switcher').exists()).toBe(false)
  const list = await setup({ path: '/products' })
  expect(list.wrapper.find('.marketplace-directory').exists()).toBe(false)
  expect(list.wrapper.get('[role="search"]').exists()).toBe(true)
  expect(list.wrapper.get('[data-area-open]').text()).toBe('Chọn khu vực')
})

it('menu button opens a drawer with every category and the main pages, closed by a link', async () => {
  const { wrapper, router } = await setup()
  const toggle = wrapper.get('.marketplace-menu-toggle')
  expect(toggle.attributes('aria-label')).toBe('Mở danh mục')
  await toggle.trigger('click')
  // The drawer is loaded on demand.
  await vi.waitFor(() => expect(document.querySelector('#nav-drawer nav[aria-label="Tất cả danh mục"]')).not.toBeNull())
  const drawer = document.querySelector('#nav-drawer')
  expect(drawer.getAttribute('role')).toBe('dialog')
  expect(
    [...drawer.querySelectorAll('nav[aria-label="Tất cả danh mục"] a')].map((a) => [a.textContent.trim(), a.getAttribute('href')]),
  ).toEqual([
    ['Điện tử', '/products?category=3'],
    ['Điện thoại', '/products?category=4'],
  ])
  drawer.querySelector('a[href="/auctions"]').click()
  // The target view is a lazy route.
  await vi.waitFor(() => expect(router.currentRoute.value.path).toBe('/auctions'), { timeout: 4000 })
  await flushPromises()
  expect(document.querySelector('#nav-drawer')).toBeNull()
})

it('mode switcher offers buyer and seller shortcuts, remembers the choice per user and never grants admin', async () => {
  const { wrapper } = await setup({ role: 'USER' })
  // The switcher sits open inside the account menu.
  expect(wrapper.get('.marketplace-account-menu .mode-switcher.is-inline').exists()).toBe(true)
  expect(wrapper.get('[data-mode-option][aria-pressed="true"]').attributes('aria-label')).toBe('Dành cho người mua')
  expect(wrapper.findAll('[data-mode-option]').map((b) => b.attributes('data-mode-option'))).toEqual(['buyer', 'seller'])
  // Buyer pages are the account menu's own links, so the buyer mode adds no duplicate shortcuts.
  expect(wrapper.find('.mode-switcher-links').exists()).toBe(false)
  expect(wrapper.findAll('.marketplace-account-links a').map((a) => a.attributes('href'))).toEqual(
    expect.arrayContaining(['/orders', '/wishlist', '/reports', '/account/addresses']),
  )
  await wrapper.get('[data-mode-option="seller"]').trigger('click')
  await flushPromises()
  expect(wrapper.get('[data-mode-option][aria-pressed="true"]').attributes('aria-label')).toBe('Dành cho người bán')
  expect(wrapper.findAll('.mode-switcher-links a').map((a) => a.attributes('href'))).toEqual([
    '/my-ads',
    '/seller/orders',
    '/seller/inventory',
    '/seller/auctions',
    '/seller/vouchers',
    '/seller/dashboard',
  ])
  expect(localStorage.getItem('passlai:mode:5')).toBe('seller')
  // A stored "admin" for a normal user is ignored.
  localStorage.setItem('passlai:mode:6', 'admin')
  const other = await setup({ role: 'USER', id: '6' })
  expect(other.wrapper.get('[data-mode-option][aria-pressed="true"]').attributes('aria-label')).toBe('Dành cho người mua')
  // The same browser, the first user again: the choice is still theirs.
  const again = await setup({ role: 'USER', id: '5' })
  expect(again.wrapper.get('[data-mode-option][aria-pressed="true"]').attributes('aria-label')).toBe('Dành cho người bán')
})

it('seller mode offers "Tạo cửa hàng" when the user has no shop, and admins get the admin mode', async () => {
  shopApi.getSellerShop.mockRejectedValue({ response: { status: 404 } })
  localStorage.setItem('passlai:mode:5', 'seller')
  const { wrapper } = await setup({ role: 'USER' })
  await flushPromises()
  expect(wrapper.findAll('.mode-switcher-links a').map((a) => [a.text(), a.attributes('href')])).toEqual([
    ['Tạo cửa hàng', '/seller/shop'],
  ])

  const admin = await setup({ role: 'ADMIN', id: '9' })
  expect(admin.wrapper.findAll('[data-mode-option]')).toHaveLength(3)
  await admin.wrapper.get('[data-mode-option="admin"]').trigger('click')
  expect(admin.wrapper.get('[data-mode-option][aria-pressed="true"]').attributes('aria-label')).toBe('Quản trị')
  const links = admin.wrapper.findAll('.mode-switcher-links a').map((a) => a.attributes('href'))
  expect(links).toEqual(['/admin', '/admin/products', '/admin/shops', '/admin/users', '/admin/reports', '/admin/payments', '/admin/vouchers'])
  for (const href of links) expect(admin.router.resolve(href).name).not.toBe('not-found')
})

it('area dialog: featured cities, search without diacritics, confirm stores the area and filters the product list', async () => {
  const { wrapper, router } = await setup({ path: '/products?q=sony&page=3' })
  await wrapper.get('[data-area-open]').trigger('click')
  await vi.waitFor(() => expect(document.querySelector('#area-dialog .area-option')).not.toBeNull())
  await flushPromises()
  const dialog = () => document.querySelector('#area-dialog')
  expect([...dialog().querySelectorAll('.area-card-name')].map((n) => n.textContent)).toEqual(['Hà Nội', 'Đà Nẵng', 'TP. Hồ Chí Minh'])
  expect(dialog().querySelectorAll('.area-option')).toHaveLength(5)
  const search = dialog().querySelector('#area-search')
  search.value = 'hai phong'
  search.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
  expect([...dialog().querySelectorAll('.area-option span')].map((n) => n.textContent)).toEqual(['Toàn quốc', 'Hải Phòng'])
  dialog().querySelector('.area-option input[value="31"]').click()
  await submitArea()
  expect(dialog()).toBeNull()
  expect(localStorage.getItem('passlai:area')).toBe('31')
  expect(router.currentRoute.value.query).toEqual({ q: 'sony', province: '31' })
  expect(wrapper.get('[data-area-open]').text()).toBe('Hải Phòng')
  // Header search keeps the area.
  await wrapper.get('[role="search"]').trigger('submit')
  await flushPromises()
  expect(router.currentRoute.value.query.province).toBe('31')
  // Choosing "Toàn quốc" removes it again.
  await wrapper.get('[data-area-open]').trigger('click')
  await vi.waitFor(() => expect(document.querySelector('#area-dialog .area-option')).not.toBeNull())
  await flushPromises()
  dialog().querySelector('.area-option input[value=""]').click()
  await submitArea()
  expect(localStorage.getItem('passlai:area')).toBeNull()
  expect('province' in router.currentRoute.value.query).toBe(false)
})

it('footer has four columns whose links all lead to real pages, and the static pages render', async () => {
  const { wrapper, router } = await setup()
  const footer = wrapper.get('footer.site-footer')
  expect(footer.findAll('h2').map((h) => h.text())).toEqual(['Hỗ trợ', 'Về chúng tôi', 'Tài khoản', 'Liên kết'])
  const links = footer.findAll('a').map((a) => a.attributes('href'))
  expect(links).toEqual(
    expect.arrayContaining(['/help', '/safety', '/contact', '/about', '/terms', '/privacy', '/login', '/register', '/products', '/shops', '/auctions']),
  )
  for (const href of links) expect(router.resolve(href).name).not.toBe('not-found')
  const signedIn = await setup({ role: 'USER' })
  expect(signedIn.wrapper.get('footer').findAll('a').map((a) => a.attributes('href'))).toEqual(
    expect.arrayContaining(['/account', '/orders', '/wishlist']),
  )
  for (const page of ['help', 'safety', 'contact', 'about', 'terms', 'privacy']) {
    const view = mount(StaticPageView, { props: { page } })
    expect(view.get('h1').text().length).toBeGreaterThan(3)
    expect(view.findAll('article').length).toBeGreaterThan(1)
    view.unmount()
  }
  // Workspaces keep their own shell: no marketplace header or footer there.
  const seller = await setup({ role: 'USER', path: '/seller/shop' })
  expect(seller.wrapper.find('footer.site-footer').exists()).toBe(false)
  expect(seller.wrapper.find('.marketplace-header').exists()).toBe(false)
})
