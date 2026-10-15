import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { createAuthGuard } from '../router/authGuard'
import * as authApi from '../api/auth.api'
import ChangePasswordView from '../views/ChangePasswordView.vue'

const user = (extra = {}) => ({ id: '3', name: 'Lan', role: 'USER', status: 'ACTIVE', must_change_password: false, ...extra })
const wrappers = []
async function setup({ forced = false, path = '/account/password' } = {}) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = user({ must_change_password: forced })
  auth.initialized = true
  vi.spyOn(auth, 'initialize').mockResolvedValue()
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div />' } },
      { path: '/orders', name: 'orders', component: { template: '<div />' }, meta: { requiresAuth: true } },
      { path: '/account/password', name: 'account-password', component: ChangePasswordView, meta: { requiresAuth: true } },
    ],
  })
  router.beforeEach((to) => createAuthGuard(auth)(to))
  await router.push(path)
  const wrapper = mount({ template: '<RouterView />' }, { global: { plugins: [pinia, router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router, auth }
}
afterEach(() => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount()
  vi.restoreAllMocks()
})

it('after a temporary password every page leads to the change-password page until a new one is set', async () => {
  const changed = vi.spyOn(authApi, 'changePassword').mockResolvedValue(user({ must_change_password: false }))
  const { wrapper, router } = await setup({ forced: true, path: '/orders' })
  expect(router.currentRoute.value.fullPath).toBe('/account/password?redirect=/orders')
  expect(wrapper.get('[data-forced]').text()).toContain('mật khẩu tạm thời')
  // The current password is not asked for in this case.
  expect(wrapper.find('#change-current_password').exists()).toBe(false)
  await wrapper.get('#change-password').setValue('BrandNewPassword456')
  await wrapper.get('#change-password_confirmation').setValue('BrandNewPassword456')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(changed).toHaveBeenCalledWith({ current_password: undefined, password: 'BrandNewPassword456', password_confirmation: 'BrandNewPassword456' })
  expect(router.currentRoute.value.fullPath).toBe('/orders')
  await router.push('/')
  expect(router.currentRoute.value.fullPath).toBe('/')
})

it('a normal change asks for the current password and checks the confirmation before sending', async () => {
  const changed = vi.spyOn(authApi, 'changePassword').mockRejectedValue({ response: { status: 422, data: { errors: { current_password: ['The current password is incorrect.'] } } } })
  const { wrapper, router } = await setup()
  expect(wrapper.find('[data-forced]').exists()).toBe(false)
  expect(wrapper.find('#change-current_password').exists()).toBe(true)
  await wrapper.get('#change-current_password').setValue('OldSecurePassword123')
  await wrapper.get('#change-password').setValue('BrandNewPassword456')
  await wrapper.get('#change-password_confirmation').setValue('Different456789')
  await wrapper.get('form').trigger('submit')
  expect(changed).not.toHaveBeenCalled()
  expect(wrapper.text()).toContain('Mật khẩu xác nhận chưa khớp.')
  await wrapper.get('#change-password_confirmation').setValue('BrandNewPassword456')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(changed).toHaveBeenCalledWith({ current_password: 'OldSecurePassword123', password: 'BrandNewPassword456', password_confirmation: 'BrandNewPassword456' })
  expect(wrapper.get('#change-current_password').attributes('aria-invalid')).toBe('true')
  expect(router.currentRoute.value.fullPath).toBe('/account/password')
  // Without the flag the rest of the site stays open.
  await router.push('/orders')
  expect(router.currentRoute.value.fullPath).toBe('/orders')
})
