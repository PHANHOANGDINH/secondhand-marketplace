import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import DefaultLayout from '../layouts/DefaultLayout.vue'
import QuickListingDialog from '../components/seller/QuickListingDialog.vue'
import AccessibleDialog from '../components/ui/AccessibleDialog.vue'
import SellerProductEditor from '../components/seller/SellerProductEditor.vue'
import SkeletonState from '../components/ui/SkeletonState.vue'
import DomainField from '../components/DomainField.vue'
import ProductsView from '../views/ProductsView.vue'
import * as products from '../api/product.api'
import * as shops from '../api/shop.api'
import * as catalog from '../api/catalogOptions'
import * as messaging from '../api/messaging.api'
const wrappers = []
const product = {
  id: '10',
  name: 'Máy ảnh',
  description: 'Máy hoạt động tốt.',
  price: '500000.00',
  stock_quantity: 1,
  condition: 'GOOD',
  category: { id: '3', name: 'Điện tử' },
  shop: { id: '5', name: 'Gian hàng demo' },
  shop_status: 'ACTIVE',
  status: 'DRAFT',
  can_edit: true,
  images: [],
  updated_at: '2026-10-07T08:00:00Z',
}
beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(shops, 'getSellerShop').mockResolvedValue({
    id: '5',
    name: 'Gian hàng demo',
    status: 'ACTIVE',
  })
  vi.spyOn(catalog, 'categoryOptions').mockResolvedValue([{ value: '3', label: 'Điện tử' }])
  vi.spyOn(products, 'createProduct').mockResolvedValue(structuredClone(product))
  vi.spyOn(products, 'getSellerProduct').mockResolvedValue(structuredClone(product))
  vi.spyOn(products, 'updateProduct').mockResolvedValue(structuredClone(product))
  vi.spyOn(products, 'productAction').mockResolvedValue({ ...product, status: 'ACTIVE' })
  vi.spyOn(products, 'uploadProductImages').mockResolvedValue([])
  vi.spyOn(messaging, 'listConversations').mockResolvedValue({
    data: [],
    unread_total: 0,
    links: { next: null },
  })
  vi.stubGlobal(
    'URL',
    Object.assign(URL, {
      createObjectURL: vi.fn(() => 'blob:quick-preview'),
      revokeObjectURL: vi.fn(),
    }),
  )
})
afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})
async function setup(
  component = QuickListingDialog,
  { role = 'USER', path = '/products?quick-listing=1', props = {} } = {},
) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.initialized = true
  auth.user = role ? { id: '9', name: 'Demo Account', role, status: 'ACTIVE' } : null
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/login',
        name: 'login',
        component: { template: '<div />' },
        meta: { guestOnly: true },
      },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } },
    ],
  })
  await router.push(path)
  const wrapper = mount(component, {
    props,
    attachTo: document.body,
    global: { plugins: [pinia, router], stubs: { CartLink: true, ChatDrawer: true } },
  })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router, auth }
}
const click = async (text) => {
  ;[...document.querySelectorAll('#quick-listing-dialog button')]
    .find((button) => button.textContent.trim() === text)
    .click()
  await flushPromises()
}
async function fill(wrapper) {
  await wrapper.get('input[name=name]').setValue('Máy ảnh')
  await wrapper.get('textarea[name=description]').setValue('Máy hoạt động tốt.')
  await wrapper.get('select[name=category_id]').setValue('3')
  await wrapper.get('input[name=price]').setValue('500000.00')
}
it('anonymous Quick Sell goes to login with a safe internal dialog return URL', async () => {
  const { wrapper, router } = await setup(DefaultLayout, {
    role: null,
    path: '/products?search=camera',
  })
  await wrapper.get('.marketplace-sell').trigger('click')
  await flushPromises()
  expect(router.currentRoute.value.name).toBe('login')
  expect(router.currentRoute.value.query.redirect).toBe('/products?search=camera&quick-listing=1')
  expect(products.createProduct).not.toHaveBeenCalled()
})
it('sign-in, sign-up and the password pages show no pinned menu, so Quick Sell cannot loop back to sign-in', async () => {
  for (const path of ['/login', '/register', '/forgot-password', '/reset-password']) {
    const { wrapper } = await setup(DefaultLayout, { role: null, path })
    expect(wrapper.find('.marketplace-header').exists()).toBe(false)
    expect(wrapper.find('.marketplace-sell').exists()).toBe(false)
  }
})
it('embedded creation uses the existing product API, stays in the dialog and preserves submit rules', async () => {
  const { wrapper, router } = await setup(SellerProductEditor, { props: { embedded: true } })
  expect(wrapper.find('[data-action=submit]').exists()).toBe(false)
  await fill(wrapper)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(products.createProduct).toHaveBeenCalledWith({
    name: 'Máy ảnh',
    description: 'Máy hoạt động tốt.',
    category_id: '3',
    price: '500000.00',
    stock_quantity: '1',
    condition: 'GOOD',
  })
  expect(router.currentRoute.value.fullPath).toBe('/products?quick-listing=1')
  expect(wrapper.get('[data-action=submit]').text()).toBe('Đăng bán')
  expect(wrapper.find('[name=status]').exists()).toBe(false)
  await wrapper.get('[data-action=submit]').trigger('click')
  await flushPromises()
  expect(products.productAction).toHaveBeenCalledWith('10', 'submit')
  // Publishing needs no review: the listing is live straight away.
  expect(wrapper.text()).toContain('Đang hoạt động')
})
it('images chosen before a draft retain previews and upload through the existing image API after saving', async () => {
  const { wrapper } = await setup(SellerProductEditor, { props: { embedded: true } })
  const input = wrapper.get('input[type=file]'),
    file = new File(['png'], 'camera.png', { type: 'image/png' })
  Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
  await input.trigger('change')
  expect(products.uploadProductImages).not.toHaveBeenCalled()
  expect(
    wrapper
      .findAll('button')
      .find((node) => node.text() === 'Tải ảnh lên')
      .attributes('disabled'),
  ).toBeDefined()
  await fill(wrapper)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.find('img[src="blob:quick-preview"]').exists()).toBe(true)
  await wrapper
    .findAll('button')
    .find((node) => node.text() === 'Tải ảnh lên')
    .trigger('click')
  await flushPromises()
  expect(products.uploadProductImages).toHaveBeenCalledWith('10', [file])
  expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:quick-preview')
})
it('pending shops can create drafts but do not receive an unsupported moderation submit action', async () => {
  shops.getSellerShop.mockResolvedValue({ id: '5', status: 'PENDING' })
  products.createProduct.mockResolvedValue({ ...product, shop_status: 'PENDING' })
  const { wrapper } = await setup(SellerProductEditor, { props: { embedded: true } })
  await fill(wrapper)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.find('[data-action=submit]').exists()).toBe(false)
})
it('missing shops get recovery navigation and do not expose an enabled create form', async () => {
  shops.getSellerShop.mockRejectedValue({ response: { status: 404 } })
  const { wrapper } = await setup(SellerProductEditor, { props: { embedded: true } })
  expect(wrapper.get('a[href="/seller/shop"]').text()).toContain('gian hàng')
  expect(wrapper.find('form').exists()).toBe(false)
  expect(products.createProduct).not.toHaveBeenCalled()
})
it('Quick Sell confirms unsaved changes on Escape and retains edits when cancellation is chosen', async () => {
  const { wrapper } = await setup()
  const editor = wrapper.getComponent(SellerProductEditor)
  await editor.get('input[name=name]').setValue('Unsaved name')
  document
    .querySelector('[role=dialog]')
    .dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
  await flushPromises()
  expect(document.activeElement.textContent).toContain('Tiếp tục chỉnh sửa')
  expect(wrapper.emitted('close')).toBeUndefined()
  await click('Tiếp tục chỉnh sửa')
  expect(editor.get('input[name=name]').element.value).toBe('Unsaved name')
  document
    .querySelector('[role=dialog]')
    .dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
  await flushPromises()
  await click('Bỏ thay đổi chưa lưu')
  expect(wrapper.emitted('close')).toHaveLength(1)
})
it('dialog traps focus, makes background inert and restores focus and scroll state on unmount', async () => {
  const outside = document.createElement('button')
  outside.textContent = 'Trigger'
  document.body.append(outside)
  outside.focus()
  const { wrapper } = await setup(AccessibleDialog, {
    props: { id: 'accessibility-dialog', title: 'Đăng bán nhanh' },
  })
  const close = document.querySelector('#accessibility-dialog button')
  expect(document.activeElement).toBe(close)
  expect(outside.hasAttribute('inert')).toBe(true)
  expect(document.body.style.overflow).toBe('hidden')
  document
    .querySelector('[role=dialog]')
    .dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true }))
  expect(document.activeElement).toBe(close)
  wrapper.unmount()
  expect(outside.hasAttribute('inert')).toBe(false)
  expect(document.activeElement).toBe(outside)
  expect(document.body.style.overflow).toBe('')
  outside.remove()
})
it('busy dialogs receive panel focus and do not dismiss on Escape', async () => {
  const { wrapper } = await setup(AccessibleDialog, {
    props: { id: 'busy-dialog', title: 'Đăng bán nhanh', busy: true },
  })
  const panel = document.querySelector('#busy-dialog')
  expect(document.activeElement).toBe(panel)
  panel.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
  expect(wrapper.emitted('dismiss')).toBeUndefined()
})
it('prefixed modal fields retain semantic labels, backend names and matching error IDs', async () => {
  const { wrapper } = await setup(DomainField, {
    props: { idPrefix: 'quick-', name: 'price', label: 'Giá', errors: ['Invalid price'] },
  })
  expect(wrapper.get('label').attributes('for')).toBe('quick-price')
  expect(wrapper.get('input').attributes('id')).toBe('quick-price')
  expect(wrapper.get('input').attributes('name')).toBe('price')
  expect(wrapper.get('input').attributes('aria-describedby')).toBe('quick-price-errors')
  expect(wrapper.get('#quick-price-errors').text()).toBe('Invalid price')
})
it.each(['cards', 'detail', 'cart', 'summary'])(
  'skeleton layout %s retains loading semantics and hides decorative blocks',
  async (layout) => {
    const { wrapper } = await setup(SkeletonState, { props: { layout, label: 'Đang tải' } })
    expect(wrapper.get('[role=status]').attributes('aria-label')).toBe('Đang tải')
    expect(wrapper.findAll('.ui-skeleton').length).toBeGreaterThan(0)
    expect(
      wrapper.findAll('.ui-skeleton').every((block) => block.attributes('aria-hidden') === 'true'),
    ).toBe(true)
  },
)

it('dialog query stays out of visible filters and catalog API parameters', async () => {
  vi.spyOn(catalog, 'shopOptions').mockResolvedValue([])
  const listing = vi.spyOn(products, 'listProducts').mockResolvedValue({
    data: [], meta: { current_page: 1, last_page: 1, total: 0 },
  })
  const { wrapper } = await setup(ProductsView, {
    path: '/products?search=camera&quick-listing=1',
  })
  expect(wrapper.get('.buyer-filter-active').text()).toContain('camera')
  expect(wrapper.get('.buyer-filter-active').text()).not.toContain('quick-listing')
  expect(listing).toHaveBeenCalledWith({ search: 'camera', sort: 'newest', page: '1' })
})
