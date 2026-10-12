import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import api from '../api/axios'
import ForgotPasswordView from '../views/ForgotPasswordView.vue'
import ResetPasswordView from '../views/ResetPasswordView.vue'
async function setup(component, query = {}) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordView },
      { path: '/reset-password', name: 'reset-password', component: ResetPasswordView },
      { path: '/login', name: 'login', component: { template: '<div />' } },
    ],
  })
  await router.push({
    path: component === ForgotPasswordView ? '/forgot-password' : '/reset-password',
    query,
  })
  const wrapper = mount(component, { global: { plugins: [router] } })
  return { wrapper, router }
}
beforeEach(() => {
  document.querySelector('meta[name=referrer]')?.remove()
  vi.restoreAllMocks()
  vi.spyOn(api, 'get').mockResolvedValue({})
  vi.spyOn(api, 'post').mockResolvedValue({ data: { message: 'Generic success' } })
})
it('requests CSRF before recovery and shows a generic success with a login link', async () => {
  const { wrapper } = await setup(ForgotPasswordView)
  await wrapper.get('input[name=email]').setValue('unknown@example.test')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(api.get).toHaveBeenCalledWith('/sanctum/csrf-cookie')
  expect(api.get.mock.invocationCallOrder[0]).toBeLessThan(api.post.mock.invocationCallOrder[0])
  expect(api.post).toHaveBeenCalledWith('/api/v1/auth/forgot-password', {
    email: 'unknown@example.test',
  })
  expect(wrapper.get('[role=status]').text()).toContain('Nếu địa chỉ thư điện tử khớp')
  expect(wrapper.get('a').attributes('href')).toBe('/login')
  wrapper.unmount()
})
it.each([422, 429, 419, 500])(
  'handles recovery failure %s and restores the submit button',
  async (status) => {
    api.post.mockRejectedValue({
      response: { status, data: { errors: status === 422 ? { email: ['Invalid email'] } : {} } },
    })
    const { wrapper } = await setup(ForgotPasswordView)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('[role=alert]').exists()).toBe(true)
    expect(wrapper.find('[role=status]').exists()).toBe(false)
    expect(wrapper.get('button').attributes('disabled')).toBeUndefined()
    wrapper.unmount()
  },
)
it('disables duplicate submissions while a recovery request is in flight', async () => {
  let resolve
  api.post.mockImplementation(
    () =>
      new Promise((done) => {
        resolve = done
      }),
  )
  const { wrapper } = await setup(ForgotPasswordView)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  await wrapper.get('form').trigger('submit')
  expect(api.post).toHaveBeenCalledTimes(1)
  expect(wrapper.get('button').attributes('disabled')).toBeDefined()
  resolve({ data: {} })
  await flushPromises()
  wrapper.unmount()
})
it('shows a useful missing-link state and never sends an empty token', async () => {
  const { wrapper } = await setup(ResetPasswordView)
  expect(wrapper.find('form').exists()).toBe(false)
  expect(wrapper.get('a').attributes('href')).toBe('/forgot-password')
  expect(api.post).not.toHaveBeenCalled()
  wrapper.unmount()
})
it('checks confirmation before posting', async () => {
  const { wrapper } = await setup(ResetPasswordView, {
    email: 'buyer@example.test',
    token: 'test-token',
  })
  await wrapper.get('input[name=password]').setValue('NewPassword123')
  await wrapper.get('input[name=password_confirmation]').setValue('Different123')
  await wrapper.get('form').trigger('submit')
  expect(wrapper.text()).toContain('chưa khớp')
  expect(api.post).not.toHaveBeenCalled()
  wrapper.unmount()
})
it('resets through CSRF, clears passwords and token URL, and guides back to login', async () => {
  const { wrapper, router } = await setup(ResetPasswordView, {
    email: 'buyer@example.test',
    token: 'test-token',
  })
  await wrapper.get('input[name=password]').setValue('NewPassword123')
  await wrapper.get('input[name=password_confirmation]').setValue('NewPassword123')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(api.post).toHaveBeenCalledWith('/api/v1/auth/reset-password', {
    email: 'buyer@example.test',
    token: 'test-token',
    password: 'NewPassword123',
    password_confirmation: 'NewPassword123',
  })
  expect(router.currentRoute.value.query).toEqual({})
  expect(wrapper.find('input[type=password]').exists()).toBe(false)
  expect(wrapper.get('[role=status]').text()).toContain('Đã đặt lại')
  expect(wrapper.get('a').attributes('href')).toBe('/login')
  wrapper.unmount()
})
it('renders invalid-token errors without displaying the token and clears passwords', async () => {
  api.post.mockRejectedValue({
    response: { status: 422, data: { errors: { token: ['Invalid token'] } } },
  })
  const { wrapper } = await setup(ResetPasswordView, {
    email: 'buyer@example.test',
    token: 'test-token',
  })
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.get('[role=alert]').text()).toContain('hết hạn')
  expect(wrapper.text()).not.toContain('test-token')
  expect(wrapper.get('input[name=password]').element.value).toBe('')
  wrapper.unmount()
})
it('applies and restores a no-referrer policy while the token page is mounted', async () => {
  const { wrapper } = await setup(ResetPasswordView)
  expect(document.querySelector('meta[name=referrer]').content).toBe('no-referrer')
  wrapper.unmount()
  expect(document.querySelector('meta[name=referrer]').content).toBe(
    'strict-origin-when-cross-origin',
  )
})
