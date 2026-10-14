import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import HomePromoBanner from '../components/home/HomePromoBanner.vue'

const motion = vi.hoisted(() => ({ from: vi.fn(), fromTo: vi.fn(), to: vi.fn(() => Promise.resolve()), revert: vi.fn(), reduced: false, cleanup: null }))
vi.mock('gsap', () => ({
  gsap: {
    context: (build) => {
      build()
      return { revert: motion.revert }
    },
    // Like the real gsap.matchMedia: the callback runs only when the query matches.
    matchMedia: () => ({
      add: (query, build) => {
        if (!motion.reduced) motion.cleanup = build()
      },
    }),
    from: motion.from,
    fromTo: motion.fromTo,
    to: motion.to,
  },
}))

const wrappers = []
async function setup({ user = null } = {}) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = user
  auth.initialized = true
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:any(.*)', component: { template: '<div />' } }] })
  await router.push('/')
  const wrapper = mount(HomePromoBanner, { global: { plugins: [pinia, router] }, attachTo: document.body })
  wrappers.push(wrapper)
  await flushPromises()
  return wrapper
}
beforeEach(() => {
  for (const spy of [motion.from, motion.fromTo, motion.to, motion.revert]) spy.mockClear()
  motion.reduced = false
})
afterEach(() => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount()
  vi.unstubAllGlobals()
  vi.useRealTimers()
})

it('without matchMedia it is a plain banner: three messages, dots to switch, no animation', async () => {
  const wrapper = await setup()
  expect(wrapper.get('[data-promo]').attributes('aria-roledescription')).toBe('carousel')
  expect(wrapper.get('[data-slide]').attributes('data-slide')).toBe('post')
  expect(wrapper.text()).toContain('Đăng là hiện ngay, không chờ duyệt')
  // A guest who wants to post signs in first and comes back to the quick listing.
  expect(wrapper.get('[data-promo-cta]').attributes('href')).toBe('/login?redirect=/?quick-listing=1')
  const dots = wrapper.findAll('.promo-dot')
  expect(dots).toHaveLength(3)
  expect(dots[0].attributes('aria-current')).toBe('true')
  await dots[1].trigger('click')
  expect(wrapper.get('[data-slide]').attributes('data-slide')).toBe('auction')
  expect(wrapper.get('[data-promo-cta]').attributes('href')).toBe('/auctions')
  expect(wrapper.findAll('.promo-dot')[1].attributes('aria-current')).toBe('true')
  await wrapper.findAll('.promo-dot')[2].trigger('click')
  expect(wrapper.get('[data-promo-cta]').attributes('href')).toBe('/products?sort=newest')
  expect(motion.from).not.toHaveBeenCalled()
})

it('a signed-in user posts straight from the banner', async () => {
  const wrapper = await setup({ user: { id: '1', name: 'Lan', role: 'USER', status: 'ACTIVE' } })
  expect(wrapper.get('[data-promo-cta]').attributes('href')).toBe('/?quick-listing=1')
})

it('with motion allowed GSAP animates it in and it moves on by itself, pausing on hover; reduce motion stops all that', async () => {
  vi.useFakeTimers()
  vi.stubGlobal('matchMedia', () => ({ matches: true }))
  const wrapper = await setup()
  await flushPromises()
  expect(motion.from).toHaveBeenCalled()
  expect(motion.to).toHaveBeenCalledWith(expect.anything(), expect.objectContaining({ repeat: -1, yoyo: true }))
  await vi.advanceTimersByTimeAsync(5000)
  await flushPromises()
  expect(wrapper.get('[data-slide]').attributes('data-slide')).toBe('auction')
  await wrapper.get('[data-promo]').trigger('mouseenter')
  await vi.advanceTimersByTimeAsync(10000)
  expect(wrapper.get('[data-slide]').attributes('data-slide')).toBe('auction')
  await wrapper.get('[data-promo]').trigger('mouseleave')
  await vi.advanceTimersByTimeAsync(5000)
  await flushPromises()
  expect(wrapper.get('[data-slide]').attributes('data-slide')).toBe('safe')
  // Choosing a message restarts the wait, so it is not replaced a moment later.
  await vi.advanceTimersByTimeAsync(4000)
  await wrapper.findAll('.promo-dot')[1].trigger('click')
  await flushPromises()
  await vi.advanceTimersByTimeAsync(4000)
  expect(wrapper.get('[data-slide]').attributes('data-slide')).toBe('auction')
  wrapper.unmount()
  wrappers.splice(0)
  expect(motion.revert).toHaveBeenCalled()

  motion.reduced = true
  motion.from.mockClear()
  const still = await setup()
  await vi.advanceTimersByTimeAsync(15000)
  expect(still.get('[data-slide]').attributes('data-slide')).toBe('post')
  expect(motion.from).not.toHaveBeenCalled()
})
