import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import AuthForm from '../components/AuthForm.vue'
import DefaultLayout from '../layouts/DefaultLayout.vue'
import { createAuthGuard } from '../router/authGuard'
import api from '../api/axios'
import { useAuthStore } from '../stores/auth'
async function setup(registration = false, redirect) {
  const pinia = createPinia()
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div />' } },
      { path: '/account', name: 'account', component: { template: '<div />' } },
      { path: '/verify-email', name: 'verify-email', component: { template: '<div />' } },
      { path: '/admin/dashboard', name: 'admin-dashboard', component: { template: '<div />' } },
      { path: '/admin/orders', component: { template: '<div />' } },
      { path: '/payment/result', component: { template: '<div />' } },
    ],
  })
  await router.push({ path: '/', query: redirect ? { redirect } : {} })
  const wrapper = mount(AuthForm, { props: { registration }, global: { plugins: [pinia, router] } })
  return { wrapper, router, store: useAuthStore(pinia) }
}
beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(api, 'get').mockResolvedValue({})
  vi.spyOn(api, 'post')
})
it('returns a signed-in buyer to the requested payment result with its order query', async () => {
  api.post.mockResolvedValue({ data: { data: { id: '9', role: 'USER' } } })
  const { wrapper, router } = await setup(false, '/payment/result?order=7')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(router.currentRoute.value.fullPath).toBe('/payment/result?order=7')
  wrapper.unmount()
})
it.each(['https://evil.example', '//evil.example', '/\\evil.example'])(
  'keeps unsafe login destination %s inside the homepage',
  async (redirect) => {
    api.post.mockResolvedValue({ data: { data: { id: '9', role: 'USER' } } })
    const { wrapper, router } = await setup(false, redirect)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('home')
    wrapper.unmount()
  },
)
it('renders backend field errors and releases the loading button', async () => {
  api.post.mockRejectedValue({
    response: {
      status: 422,
      data: { errors: { email: ['The provided credentials are incorrect.'] } },
    },
  })
  const { wrapper } = await setup()
  await wrapper.get('input[name=email]').setValue('user@example.test')
  await wrapper.get('input[name=password]').setValue('test-only-form-input')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.get('#email-errors').text()).toContain('Thông tin đăng nhập không chính xác')
  expect(wrapper.get('input[name=password]').element.value).toBe('')
  expect(wrapper.get('button[type=submit]').attributes('disabled')).toBeUndefined()
  wrapper.unmount()
})
it('registers through the server then asks to check the mail, stays signed out and clears password fields', async () => {
  api.post.mockResolvedValue({ data: { data: { id: '9', name: 'Form Test', role: 'USER' } } })
  const { wrapper, router, store } = await setup(true)
  await wrapper.get('input[name=name]').setValue('Form Test')
  await wrapper.get('input[name=email]').setValue('form@example.test')
  await wrapper.get('input[name=password]').setValue('test-only-form-input')
  await wrapper.get('input[name=password_confirmation]').setValue('test-only-form-input')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(router.currentRoute.value.name).toBe('verify-email')
  expect(store.user).toBeNull()
  expect(wrapper.get('input[name=password]').element.value).toBe('')
  expect(wrapper.get('input[name=password_confirmation]').element.value).toBe('')
  wrapper.unmount()
})

it.each([
  ['ADMIN', undefined, '/admin/dashboard'],
  ['USER', undefined, '/'],
  ['ADMIN', '/admin/orders?order=12', '/admin/orders?order=12'],
  ['ADMIN', 'https://evil.example', '/admin/dashboard'],
])(
  'login role %s preserves a safe intended destination or uses the role default',
  async (role, redirect, expected) => {
    api.post.mockResolvedValue({ data: { data: { id: '9', role, status: 'ACTIVE' } } })
    const { wrapper, router } = await setup(false, redirect)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(router.currentRoute.value.fullPath).toBe(expected)
    wrapper.unmount()
  },
)
it.each([
  ['Admin', 'ADMIN', true],
  ['Buyer', 'USER', false],
  ['Seller', 'USER', false],
])(
  'only an ADMIN sees the visible management navigation entry: %s',
  async (name, role, allowed) => {
    const pinia = createPinia()
    const auth = useAuthStore(pinia)
    auth.initialized = true
    auth.user = { id: '1', name, role, status: 'ACTIVE' }
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }],
    })
    await router.push('/')
    const wrapper = mount(DefaultLayout, {
      global: { plugins: [pinia, router], stubs: { CartLink: true, LogoutButton: true } },
    })
    // The entry lives only in the account menu's mode switcher, not as a duplicate header link.
    const entry = wrapper.find('.marketplace-account [data-mode-option="admin"]')
    expect(entry.exists() && entry.text()).toBe(allowed && 'Quản trị')
    expect(wrapper.find('.marketplace-links > a[href^="/admin"]').exists()).toBe(false)
    expect(wrapper.find('.marketplace-links > a[href="/my-ads"]').exists()).toBe(false)
    wrapper.unmount()
  },
)

it('a logged-out admin returns to the protected admin URL after login through the route guard', async () => {
  const pinia = createPinia()
  const auth = useAuthStore(pinia)
  auth.initialized = true
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: AuthForm, meta: { guestOnly: true } },
      {
        path: '/admin/dashboard',
        name: 'admin-dashboard',
        component: { template: '<div />' },
        meta: { requiresAdmin: true },
      },
      { path: '/admin/orders', component: { template: '<div />' }, meta: { requiresAdmin: true } },
    ],
  })
  router.beforeEach(createAuthGuard(auth))
  await router.push('/admin/orders?order=12')
  expect(router.currentRoute.value.name).toBe('login')
  expect(router.currentRoute.value.query.redirect).toBe('/admin/orders?order=12')
  const wrapper = mount(AuthForm, { global: { plugins: [pinia, router] } })
  api.post.mockResolvedValue({ data: { data: { id: '1', role: 'ADMIN', status: 'ACTIVE' } } })
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(router.currentRoute.value.fullPath).toBe('/admin/orders?order=12')
  wrapper.unmount()
})
