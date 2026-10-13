import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import * as categoryApi from '../api/category.api'
import FirstTimeGuide from '../components/onboarding/FirstTimeGuide.vue'
import DefaultLayout from '../layouts/DefaultLayout.vue'
vi.mock('../composables/useNotifications', async (importOriginal) => ({
  ...(await importOriginal()),
  provideNotifications: vi.fn(),
}))
vi.mock('../composables/useMessaging', async (importOriginal) => ({
  ...(await importOriginal()),
  provideMessaging: vi.fn(),
}))
const motion = vi.hoisted(() => ({ from: vi.fn(), revert: vi.fn(), queries: [], reduced: false }))
vi.mock('gsap', () => ({
  gsap: {
    context: (build) => {
      build()
      return { revert: motion.revert }
    },
    // Like the real gsap.matchMedia: the callback runs only when the query matches.
    matchMedia: () => ({
      add: (query, build) => {
        motion.queries.push(query)
        if (!motion.reduced) build()
      },
    }),
    from: motion.from,
  },
}))

const steps = [
  { title: 'Tìm kiếm', text: 'Bước một.' },
  { title: 'Đăng bán nhanh', text: 'Bước hai.' },
  { title: 'Đấu giá', text: 'Bước ba.' },
]
const KEY = (user, name) => `passlai:first-time:${user}:${name}`
const wrappers = []
async function setup(component, { props = {}, user = '1' } = {}) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = user ? { id: user, name: 'Người dùng', role: 'USER', status: 'ACTIVE' } : null
  auth.initialized = true
  const router = createRouter({
    history: createMemoryHistory(),
    routes: ['/', '/products', '/login', '/:rest(.*)*'].map((path) => ({ path, component: { template: '<div />' } })),
  })
  await router.push('/')
  const wrapper = mount(component, {
    props,
    attachTo: document.body,
    global: {
      plugins: [pinia, router],
      stubs: { NotificationBell: true, ChatDrawer: true, ChatLauncher: true, CartLink: true, OutbidToasts: true, LogoutButton: true },
    },
  })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, auth }
}
const note = { name: 'auction-room', title: 'Lần đầu vào phòng đấu giá', steps }
beforeEach(() => {
  window.localStorage.clear()
  motion.from.mockClear()
  motion.revert.mockClear()
  motion.queries.length = 0
  motion.reduced = false
  vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: true })))
  vi.spyOn(categoryApi, 'listCategories').mockResolvedValue({ data: [] })
})
afterEach(() => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount()
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
  document.body.innerHTML = ''
})

it('shows a guide once per user: skipping stores the flag for that user only', async () => {
  const first = await setup(FirstTimeGuide, { props: note })
  expect(first.wrapper.get('.first-time-note').text()).toContain('Lần đầu vào phòng đấu giá')
  await first.wrapper.get('[data-guide-skip]').trigger('click')
  expect(first.wrapper.find('.first-time-note').exists()).toBe(false)
  expect(first.wrapper.emitted('done')).toHaveLength(1)
  expect(window.localStorage.getItem(KEY('1', 'auction-room'))).toBe('1')

  const again = await setup(FirstTimeGuide, { props: note })
  expect(again.wrapper.find('.first-time-note').exists()).toBe(false)
  const otherGuide = await setup(FirstTimeGuide, { props: { ...note, name: 'quick-listing' } })
  expect(otherGuide.wrapper.find('.first-time-note').exists()).toBe(true)
  const otherUser = await setup(FirstTimeGuide, { props: note, user: '2' })
  expect(otherUser.wrapper.find('.first-time-note').exists()).toBe(true)
  const guest = await setup(FirstTimeGuide, { props: note, user: null })
  expect(guest.wrapper.find('.first-time-note').exists()).toBe(false)
})

it('tour walks through the steps in a dialog, and both finishing and ESC store the flag', async () => {
  const { wrapper } = await setup(FirstTimeGuide, { props: { ...note, name: 'welcome', title: 'Chào mừng', tour: true } })
  const dialog = () => document.querySelector('[role="dialog"]')
  expect(dialog().getAttribute('aria-modal')).toBe('true')
  expect(dialog().textContent).toContain('Bước 1 / 3')
  expect(dialog().textContent).toContain('Tìm kiếm')
  const next = () => document.querySelector('[data-guide-next]')
  next().click()
  await flushPromises()
  expect(dialog().textContent).toContain('Đăng bán nhanh')
  next().click()
  await flushPromises()
  expect(next().textContent.trim()).toBe('Bắt đầu')
  expect(window.localStorage.getItem(KEY('1', 'welcome'))).toBeNull()
  next().click()
  await flushPromises()
  expect(dialog()).toBeNull()
  expect(window.localStorage.getItem(KEY('1', 'welcome'))).toBe('1')
  expect(wrapper.emitted('done')).toHaveLength(1)

  const escaped = await setup(FirstTimeGuide, { props: { ...note, name: 'welcome', title: 'Chào mừng', tour: true }, user: '3' })
  dialog().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
  await flushPromises()
  expect(dialog()).toBeNull()
  expect(window.localStorage.getItem(KEY('3', 'welcome'))).toBe('1')
  expect(escaped.wrapper.emitted('done')).toHaveLength(1)
  // An inactive tour never opens, even while the flag is still pending.
  const idle = await setup(FirstTimeGuide, { props: { ...note, name: 'welcome', title: 'Chào mừng', tour: true, active: false }, user: '4' })
  expect(dialog()).toBeNull()
  expect(idle.wrapper.html()).not.toContain('Bước 1')
})

it('animates through gsap.matchMedia only without reduced motion, and reverts on unmount', async () => {
  const moving = await setup(FirstTimeGuide, { props: note })
  expect(motion.queries).toEqual(['(prefers-reduced-motion: no-preference)'])
  expect(motion.from).toHaveBeenCalledTimes(1)
  const [, vars] = motion.from.mock.calls[0]
  expect(Object.keys(vars).sort()).toEqual(['clearProps', 'duration', 'ease', 'opacity', 'y'])
  expect(vars.duration).toBeLessThanOrEqual(0.6)
  moving.wrapper.unmount()
  wrappers.splice(wrappers.indexOf(moving.wrapper), 1)
  expect(motion.revert).toHaveBeenCalled()

  motion.from.mockClear()
  motion.reduced = true
  const still = await setup(FirstTimeGuide, { props: note, user: '2' })
  expect(still.wrapper.get('.first-time-note').text()).toContain('Tìm kiếm')
  expect(motion.from).not.toHaveBeenCalled()

  vi.stubGlobal('matchMedia', undefined)
  motion.queries.length = 0
  const bare = await setup(FirstTimeGuide, { props: note, user: '5' })
  expect(bare.wrapper.find('.first-time-note').exists()).toBe(true)
  expect(motion.queries).toEqual([])
})

it('treats broken storage as "already seen" and never throws', async () => {
  vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
    throw new Error('blocked')
  })
  const blocked = await setup(FirstTimeGuide, { props: note })
  expect(blocked.wrapper.find('.first-time-note').exists()).toBe(false)
  vi.restoreAllMocks()
  vi.spyOn(categoryApi, 'listCategories').mockResolvedValue({ data: [] })

  vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
    throw new Error('quota')
  })
  const full = await setup(FirstTimeGuide, { props: note, user: '6' })
  expect(full.wrapper.find('.first-time-note').exists()).toBe(true)
  await full.wrapper.get('[data-guide-skip]').trigger('click')
  expect(full.wrapper.find('.first-time-note').exists()).toBe(false)
})

it('welcome tour opens on every signed-in page view until skipped, and can be replayed', async () => {
  const dialog = () => document.querySelector('[role="dialog"]')
  const guest = await setup(DefaultLayout, { user: null })
  expect(dialog()).toBeNull()
  guest.wrapper.unmount()
  wrappers.splice(wrappers.indexOf(guest.wrapper), 1)

  // A page that loads already signed in shows the tour; so does the next page view.
  const first = await setup(DefaultLayout, { user: '9' })
  expect(dialog().textContent).toContain('Chào mừng đến pass lại')
  first.wrapper.unmount()
  wrappers.splice(wrappers.indexOf(first.wrapper), 1)
  expect(window.localStorage.getItem(KEY('9', 'welcome'))).toBeNull()

  const second = await setup(DefaultLayout, { user: '9' })
  expect(dialog().textContent).toContain('Bước 1 / 3')
  document.querySelector('[data-guide-skip]').click()
  await flushPromises()
  expect(dialog()).toBeNull()
  expect(window.localStorage.getItem(KEY('9', 'welcome'))).toBe('1')
  second.wrapper.unmount()
  wrappers.splice(wrappers.indexOf(second.wrapper), 1)

  const third = await setup(DefaultLayout, { user: '9' })
  expect(dialog()).toBeNull()
  // "Xem lại hướng dẫn" clears this user's flags and opens the tour again.
  window.localStorage.setItem(KEY('9', 'auction-room'), '1')
  const replay = third.wrapper
    .findAll('.marketplace-account-menu button')
    .find((button) => button.text() === 'Xem lại hướng dẫn')
  await replay.trigger('click')
  await flushPromises()
  expect(dialog().textContent).toContain('Bước 1 / 3')
  expect(window.localStorage.getItem(KEY('9', 'auction-room'))).toBeNull()
})
