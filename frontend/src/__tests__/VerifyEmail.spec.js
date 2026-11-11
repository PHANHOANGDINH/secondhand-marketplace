import { beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import VerifyEmailView from '../views/VerifyEmailView.vue'
import AuthForm from '../components/AuthForm.vue'
import * as authApi from '../api/auth.api'

vi.mock('gsap', () => ({
  gsap: { context: () => ({ revert() {} }), matchMedia: () => ({ add() {} }), timeline: () => ({}) },
}))

const blank = { template: '<div />' }
const makeRouter = () =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: blank },
      { path: '/login', name: 'login', component: blank },
      { path: '/register', name: 'register', component: blank },
      { path: '/forgot-password', name: 'forgot-password', component: blank },
      { path: '/verify-email', name: 'verify-email', component: blank },
    ],
  })
const open = async (path) => {
  const router = makeRouter()
  await router.push(path)
  await router.isReady()
  const wrapper = mount(VerifyEmailView, { global: { plugins: [createPinia(), router] } })
  await flushPromises()
  return { wrapper, router }
}
const linkPath = '/verify-email?id=7&hash=abc&expires=123&signature=def'

beforeEach(() => {
  setActivePinia(createPinia())
  vi.restoreAllMocks()
  window.history.replaceState({}, '')
})

it('posts the link once, says it worked, offers sign-in and removes the signature from the address', async () => {
  const verify = vi.spyOn(authApi, 'verifyEmail').mockResolvedValue({})
  const { wrapper, router } = await open(linkPath)

  expect(verify).toHaveBeenCalledTimes(1)
  expect(verify).toHaveBeenCalledWith({ id: '7', hash: 'abc', expires: '123', signature: 'def' })
  expect(wrapper.text()).toContain('Đã xác minh thư điện tử')
  expect(wrapper.find('a[href="/login"]').exists()).toBe(true)
  expect(wrapper.find('input').exists()).toBe(false)
  expect(router.currentRoute.value.fullPath).toBe('/verify-email')
})

it('a refused link explains itself and offers a new code', async () => {
  vi.spyOn(authApi, 'verifyEmail').mockRejectedValue({
    response: { status: 422, data: { code: 'EMAIL_VERIFICATION_LINK_INVALID' } },
  })
  const resend = vi.spyOn(authApi, 'resendVerification').mockResolvedValue({})
  const { wrapper } = await open(linkPath)

  expect(wrapper.text()).toContain('Liên kết không dùng được')
  await wrapper.get('input[type="email"]').setValue('lan@example.test')
  await wrapper.get('[data-resend]').trigger('click')
  await flushPromises()
  expect(resend).toHaveBeenCalledWith({ email: 'lan@example.test' })
  expect(wrapper.text()).toContain('chúng tôi đã gửi thư mới')
})

it('the code from the mail verifies the address and offers sign-in', async () => {
  const submit = vi.spyOn(authApi, 'verifyEmailCode').mockResolvedValue({})
  const { wrapper } = await open('/verify-email')

  await wrapper.get('input[type="email"]').setValue('lan@example.test')
  await wrapper.get('input[name="code"]').setValue('12a 34-56789')
  expect(wrapper.get('input[name="code"]').element.value).toBe('123456')
  await wrapper.get('form').trigger('submit')
  await flushPromises()

  expect(submit).toHaveBeenCalledWith({ email: 'lan@example.test', code: '123456' })
  expect(wrapper.text()).toContain('Đã xác minh thư điện tử')
  expect(wrapper.find('a[href="/login"]').exists()).toBe(true)
})

it('a wrong code explains itself, empties the box and leaves the form open', async () => {
  vi.spyOn(authApi, 'verifyEmailCode').mockRejectedValue({
    response: { status: 422, data: { code: 'EMAIL_VERIFICATION_CODE_INVALID' } },
  })
  const { wrapper } = await open('/verify-email')

  await wrapper.get('input[name="code"]').setValue('000000')
  await wrapper.get('form').trigger('submit')
  await flushPromises()

  expect(wrapper.text()).toContain('Mã không đúng hoặc đã hết hạn')
  expect(wrapper.get('input[name="code"]').element.value).toBe('')
})

it('asking for a new code needs an address and does not post without one', async () => {
  const resend = vi.spyOn(authApi, 'resendVerification').mockResolvedValue({})
  const { wrapper } = await open('/verify-email')

  await wrapper.get('[data-resend]').trigger('click')
  await flushPromises()

  expect(resend).not.toHaveBeenCalled()
  expect(wrapper.text()).toContain('Vui lòng nhập thư điện tử')
})

it('an incomplete link is not posted and only asks to check the mailbox', async () => {
  const verify = vi.spyOn(authApi, 'verifyEmail')
  const { wrapper } = await open('/verify-email?id=7&hash=abc')

  expect(verify).not.toHaveBeenCalled()
  expect(wrapper.text()).toContain('Kiểm tra hộp thư của bạn')
})

it('the address from the history state fills the resend form', async () => {
  window.history.replaceState({ email: 'lan@example.test' }, '')
  const wrapper = mount(VerifyEmailView, { global: { plugins: [createPinia(), makeRouter()] } })
  await flushPromises()

  expect(wrapper.get('input[type="email"]').element.value).toBe('lan@example.test')
})

const mountForm = async (props) => {
  const router = makeRouter()
  await router.push(props.registration ? '/register' : '/login')
  await router.isReady()
  const push = vi.spyOn(router, 'push')
  const wrapper = mount(AuthForm, { props, global: { plugins: [createPinia(), router] } })
  return { wrapper, push }
}

it('registering goes to the check-your-mail page without signing in', async () => {
  vi.spyOn(authApi, 'register').mockResolvedValue({ id: '7', email: 'lan@example.test' })
  const { wrapper, push } = await mountForm({ registration: true })

  await wrapper.get('input[type="email"]').setValue('lan@example.test')
  await wrapper.get('form').trigger('submit')
  await flushPromises()

  expect(push).toHaveBeenCalledWith({ name: 'verify-email', state: { email: 'lan@example.test' } })
})

it('a sign-in blocked for an unverified address goes to the same page', async () => {
  vi.spyOn(authApi, 'login').mockRejectedValue({
    response: { status: 403, data: { code: 'EMAIL_NOT_VERIFIED' } },
  })
  const { wrapper, push } = await mountForm({ registration: false })

  await wrapper.get('input[type="email"]').setValue('lan@example.test')
  await wrapper.get('form').trigger('submit')
  await flushPromises()

  expect(push).toHaveBeenCalledWith({ name: 'verify-email', state: { email: 'lan@example.test' } })
  expect(wrapper.text()).not.toContain('không hoạt động')
})
