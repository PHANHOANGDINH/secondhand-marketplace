import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AuthForm from '../components/AuthForm.vue'

const motion = vi.hoisted(() => {
  const timeline = { from: vi.fn(() => timeline) }
  return { timeline, to: vi.fn(), fromTo: vi.fn(), revert: vi.fn(), reduced: false }
})
vi.mock('gsap', () => ({
  gsap: {
    context: (build) => {
      build()
      return { revert: motion.revert }
    },
    matchMedia: () => ({
      add: (query, build) => {
        if (!motion.reduced) build()
      },
    }),
    timeline: () => motion.timeline,
    to: motion.to,
    fromTo: motion.fromTo,
    quickTo: () => vi.fn(),
    utils: { toArray: (selector) => [...document.querySelectorAll(selector)] },
  },
}))

const wrappers = []
async function setup(props = {}) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.initialized = true
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:any(.*)', name: 'home', component: { template: '<div />' } }] })
  await router.push('/login')
  const wrapper = mount(AuthForm, { props, global: { plugins: [pinia, router] }, attachTo: document.body })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, auth }
}
beforeEach(() => {
  for (const spy of [motion.timeline.from, motion.to, motion.fromTo, motion.revert]) spy.mockClear()
  motion.reduced = false
  vi.stubGlobal('matchMedia', () => ({ matches: true }))
})
afterEach(() => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount()
  vi.unstubAllGlobals()
})

it('the page has a welcome panel next to the form and the password can be shown and hidden', async () => {
  const { wrapper } = await setup()
  expect(wrapper.get('.auth-showcase').attributes('aria-label')).toBe('Giới thiệu sàn mua bán')
  expect(wrapper.findAll('.auth-perk')).toHaveLength(3)
  expect(wrapper.get('.auth-tiles').attributes('aria-hidden')).toBe('true')
  const password = wrapper.get('#auth-password')
  const toggle = wrapper.get('.auth-password-toggle')
  expect(password.attributes('type')).toBe('password')
  expect(toggle.attributes('aria-label')).toBe('Hiện mật khẩu')
  await toggle.trigger('click')
  expect(password.attributes('type')).toBe('text')
  expect(toggle.attributes('aria-pressed')).toBe('true')
  expect(toggle.attributes('aria-label')).toBe('Ẩn mật khẩu')
  // The toggle never submits the form.
  expect(toggle.attributes('type')).toBe('button')
})

it('GSAP brings the panels in, floats the tiles and shakes the form after a failed sign-in', async () => {
  const { wrapper, auth } = await setup()
  expect(motion.timeline.from).toHaveBeenCalledWith('.auth-form', expect.objectContaining({ autoAlpha: 0 }), expect.anything())
  expect(motion.to).toHaveBeenCalledWith(expect.anything(), expect.objectContaining({ repeat: -1, yoyo: true }))
  vi.spyOn(auth, 'login').mockRejectedValue({ response: { status: 422, data: { errors: { email: ['Sai thông tin đăng nhập.'] } } } })
  await wrapper.get('#auth-email').setValue('a@b.vn')
  await wrapper.get('#auth-password').setValue('sai-mat-khau-123')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(motion.fromTo).toHaveBeenCalledWith(wrapper.get('form').element, { x: 0 }, expect.objectContaining({ keyframes: expect.anything() }))
  expect(wrapper.get('#auth-password').element.value).toBe('')
  wrapper.unmount()
  wrappers.splice(0)
  expect(motion.revert).toHaveBeenCalled()
})

it('with reduced motion nothing moves and an error does not shake the form', async () => {
  motion.reduced = true
  const { wrapper, auth } = await setup({ registration: true })
  expect(motion.timeline.from).not.toHaveBeenCalled()
  vi.spyOn(auth, 'register').mockRejectedValue({ response: { status: 422, data: { errors: {} } } })
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(motion.fromTo).not.toHaveBeenCalled()
})
