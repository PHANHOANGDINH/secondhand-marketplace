import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import AuthForm from '../components/AuthForm.vue'
import api from '../api/axios'
import { useAuthStore } from '../stores/auth'
async function setup(registration = false, redirect) {
  const pinia = createPinia()
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/account', name: 'account', component: { template: '<div />' } },
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
  'keeps unsafe login destination %s inside the account page',
  async (redirect) => {
    api.post.mockResolvedValue({ data: { data: { id: '9', role: 'USER' } } })
    const { wrapper, router } = await setup(false, redirect)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('account')
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
  expect(wrapper.get('#email-errors').text()).toContain('credentials are incorrect')
  expect(wrapper.get('input[name=password]').element.value).toBe('')
  expect(wrapper.get('button[type=submit]').attributes('disabled')).toBeUndefined()
  wrapper.unmount()
})
it('registers through the server then navigates to account and clears password fields', async () => {
  api.post.mockResolvedValue({ data: { data: { id: '9', name: 'Form Test', role: 'USER' } } })
  const { wrapper, router, store } = await setup(true)
  await wrapper.get('input[name=name]').setValue('Form Test')
  await wrapper.get('input[name=email]').setValue('form@example.test')
  await wrapper.get('input[name=password]').setValue('test-only-form-input')
  await wrapper.get('input[name=password_confirmation]').setValue('test-only-form-input')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(router.currentRoute.value.name).toBe('account')
  expect(store.user.id).toBe('9')
  expect(wrapper.get('input[name=password]').element.value).toBe('')
  expect(wrapper.get('input[name=password_confirmation]').element.value).toBe('')
  wrapper.unmount()
})
