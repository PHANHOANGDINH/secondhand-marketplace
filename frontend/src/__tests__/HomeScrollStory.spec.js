import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import HomeScrollStory from '../components/home/HomeScrollStory.vue'

const animation = vi.hoisted(() => ({
  contextRevert: vi.fn(),
  mediaRevert: vi.fn(),
  add: vi.fn(),
  register: vi.fn(),
  refresh: vi.fn(),
}))
vi.mock('gsap', () => ({
  gsap: {
    registerPlugin: animation.register,
    context: (callback) => {
      callback()
      return { revert: animation.contextRevert }
    },
    matchMedia: () => ({ add: animation.add, revert: animation.mediaRevert }),
  },
}))
vi.mock('gsap/ScrollTrigger', () => ({ ScrollTrigger: { refresh: animation.refresh } }))

async function renderStory() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: HomeScrollStory },
      { path: '/products', component: { template: '<p>Products</p>' } },
      { path: '/seller/shop', component: { template: '<p>Shop</p>' } },
    ],
  })
  await router.push('/')
  await router.isReady()
  return { wrapper: mount(HomeScrollStory, { global: { plugins: [router] } }), router }
}

afterEach(() => {
  vi.unstubAllGlobals()
  vi.clearAllMocks()
})

it('keeps all five scenes readable before animation and uses existing CTA routes', async () => {
  vi.stubGlobal('matchMedia', undefined)
  const { wrapper, router } = await renderStory()
  expect(wrapper.findAll('h2')).toHaveLength(5)
  expect(wrapper.text()).toContain('Đồ cũ không có nghĩa là hết giá trị.')
  expect(wrapper.text()).toContain('Trao đồ cũ. Tạo giá trị mới.')
  expect(wrapper.classes()).not.toContain('is-animated')
  expect(wrapper.findAll('img').every((image) => image.attributes('src').startsWith('/'))).toBe(
    true,
  )
  await wrapper.get('a[href="/products"]').trigger('click')
  await flushPromises()
  expect(router.currentRoute.value.path).toBe('/products')
  await wrapper.get('a[href="/seller/shop"]').trigger('click')
  await flushPromises()
  expect(router.currentRoute.value.path).toBe('/seller/shop')
  wrapper.unmount()
})

it('limits pinned animation to larger screens without reduced motion and reverts on unmount', async () => {
  vi.stubGlobal('matchMedia', vi.fn())
  const { wrapper } = await renderStory()
  await flushPromises()
  expect(animation.register).toHaveBeenCalledOnce()
  expect(animation.add).toHaveBeenCalledWith(
    '(min-width: 768px) and (prefers-reduced-motion: no-preference)',
    expect.any(Function),
    wrapper.element,
  )
  wrapper.unmount()
  expect(animation.mediaRevert).toHaveBeenCalledOnce()
  expect(animation.contextRevert).toHaveBeenCalledOnce()
})
