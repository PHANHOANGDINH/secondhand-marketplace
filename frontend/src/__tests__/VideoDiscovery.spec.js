import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { beforeEach, afterEach, it, expect, vi } from 'vitest'
import * as api from '../api/productVideo.api'
import * as auctions from '../api/auction.api'
import Discovery from '../components/home/HomeDiscovery.vue'
import Theater from '../components/home/VideoTheater.vue'
const row = (id = '1', auction = null) => ({
  id,
  url: `/storage/${id}.webm`,
  title: 'Review',
  product: {
    name: 'Máy ảnh',
    slug: 'camera',
    price: '100000.00',
    condition: 'GOOD',
    description: 'Thông tin sản phẩm',
    primary_image: { url: '/cover.webp' },
    shop: { name: 'Shop', slug: 'shop' },
  },
  auction,
})
let wrappers, reduced, media
beforeEach(() => {
  wrappers = []
  reduced = false
  media = []
  vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue()
  vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(() => {})
  vi.spyOn(HTMLMediaElement.prototype, 'load').mockImplementation(() => {})
  vi.stubGlobal(
    'matchMedia',
    vi.fn((query) => {
      const item = {
        matches: query.includes('reduce') ? reduced : true,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      }
      media.push(item)
      return item
    }),
  )
  vi.spyOn(api, 'listPublicVideos').mockResolvedValue({
    data: [row(), row('2')],
    meta: { current_page: 1, last_page: 1 },
  })
  vi.spyOn(api, 'getPublicVideo').mockImplementation(async (id) => row(String(id)))
  vi.spyOn(auctions, 'listAuctions').mockResolvedValue({ data: [], meta: null })
})
afterEach(() => {
  wrappers.forEach((w) => w.unmount())
  document.body.innerHTML = ''
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
  vi.useRealTimers()
})
async function setup(path = '/') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/products/:slug', component: { template: '<div />' } },
      { path: '/auctions/:id', component: { template: '<div />' } },
    ],
  })
  await router.push(path)
  await router.isReady()
  const w = mount(Discovery, {
    attachTo: document.body,
    global: { plugins: [createPinia(), router], stubs: { VideoComments: true } },
  })
  wrappers.push(w)
  await flushPromises()
  return { w, router }
}
const button = (w, text) => w.findAll('button').find((b) => b.text() === text)
async function videos(w) {
  await button(w, 'Video đánh giá').trigger('click')
  await flushPromises()
}
it('opens the video feed from the header destination and when navigating within the homepage', async () => {
  const { w, router } = await setup('/?discovery=video#home-discovery')
  expect(w.findAll('.video-review-card')).toHaveLength(2)
  expect(auctions.listAuctions).not.toHaveBeenCalled()
  await router.push('/')
  await button(w, 'Sàn đấu giá').trigger('click')
  await flushPromises()
  await router.push('/?discovery=video#home-discovery')
  await flushPromises()
  expect(w.findAll('.video-review-card')).toHaveLength(2)
})
it('switches real feeds without preloading video bodies', async () => {
  const { w } = await setup()
  expect(auctions.listAuctions).toHaveBeenCalled()
  await videos(w)
  expect(api.listPublicVideos).toHaveBeenCalledWith({ page: 1, per_page: 12 })
  expect(w.findAll('.video-review-card')).toHaveLength(2)
  expect(w.find('video').exists()).toBe(false)
  expect(w.get('img').attributes('loading')).toBe('lazy')
})
it('plays only one intentional preview and stops on leave', async () => {
  const { w } = await setup()
  await videos(w)
  const cards = w.findAll('.video-review-cover')
  await cards[0].trigger('mouseenter')
  await flushPromises()
  expect(w.findAll('video')).toHaveLength(1)
  expect(HTMLMediaElement.prototype.play).toHaveBeenCalled()
  await cards[1].trigger('focus')
  await flushPromises()
  expect(w.findAll('video')).toHaveLength(1)
  expect(w.get('video').attributes('src')).toBe('/storage/2.webm')
  expect(HTMLMediaElement.prototype.pause).toHaveBeenCalled()
  await cards[1].trigger('mouseleave')
  expect(w.find('video').exists()).toBe(false)
})
it('respects reduced motion and coarse pointer', async () => {
  reduced = true
  const { w } = await setup()
  await videos(w)
  await w.get('.video-review-cover').trigger('mouseenter')
  expect(w.find('video').exists()).toBe(false)
  media[0].matches = false
  media[1].matches = false
  await w.get('.video-review-cover').trigger('focus')
  expect(w.find('video').exists()).toBe(false)
})
it('opens after checking visibility, ESC closes and returns focus', async () => {
  const { w, router } = await setup()
  await videos(w)
  const origin = w.get('.video-review-cover')
  origin.element.focus()
  await origin.trigger('click')
  await flushPromises()
  expect(router.currentRoute.value.query.video).toBe('1')
  expect(api.getPublicVideo).toHaveBeenCalledWith('1')
  const dialog = document.querySelector('[role=dialog]')
  expect(dialog).toBeTruthy()
  expect(document.activeElement.textContent.trim()).toBe('Đóng')
  dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
  await flushPromises()
  expect(document.querySelector('[role=dialog]')).toBeNull()
  expect(router.currentRoute.value.query.video).toBeUndefined()
  expect(document.activeElement).toBe(origin.element)
  expect(document.body.style.overflow).toBe('')
})
it('moves in feed order with disabled boundaries and stops old media', async () => {
  const { w, router } = await setup('/?video=1')
  const theater = w.getComponent(Theater)
  expect(theater.props('previous')).toBe(false)
  expect(theater.props('next')).toBe(true)
  theater.vm.$emit('move', 1)
  await flushPromises()
  expect(router.currentRoute.value.query.video).toBe('2')
  expect(HTMLMediaElement.prototype.pause).toHaveBeenCalled()
  expect(theater.props('next')).toBe(false)
  theater.vm.$emit('move', 1)
  await flushPromises()
  expect(router.currentRoute.value.query.video).toBe('2')
})
it('does not hijack native player arrow controls', async () => {
  const { router } = await setup('/?video=1')
  document
    .querySelector('video')
    .dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }))
  expect(router.currentRoute.value.query.video).toBe('1')
  expect(document.querySelector('video').hasAttribute('controls')).toBe(true)
})
it('closes deep links preserving other query parameters', async () => {
  const { w, router } = await setup('/?video=1&source=demo')
  w.getComponent(Theater).vm.$emit('close')
  await flushPromises()
  expect(router.currentRoute.value.query).toEqual({ source: 'demo' })
})
it('Back closes an opened theater', async () => {
  const { w, router } = await setup()
  await videos(w)
  await w.get('.video-review-cover').trigger('click')
  await flushPromises()
  router.back()
  await flushPromises()
  expect(w.findComponent(Theater).exists()).toBe(false)
})
it('uses existing product and auction CTAs without checkout bypass', async () => {
  const { w } = await setup('/?video=1')
  expect(document.querySelector('a[href="/products/camera"]')).toBeTruthy()
  api.getPublicVideo.mockResolvedValue(
    row('1', { id: '7', status: 'LIVE', current_price: '200000.00' }),
  )
  w.getComponent(Theater).vm.$emit('retry')
  await flushPromises()
  expect(document.querySelector('a[href="/auctions/7"]')).toBeTruthy()
  expect(document.querySelector('a[href="/checkout"]')).toBeNull()
  api.getPublicVideo.mockResolvedValue(
    row('1', { id: '7', status: 'ENDED', current_price: '200000.00' }),
  )
  w.getComponent(Theater).vm.$emit('retry')
  await flushPromises()
  expect(document.querySelector('a[href="/auctions/7"]').textContent).toBe('Xem phiên đấu giá')
})
it('unavailable deep links expose no product CTA', async () => {
  api.getPublicVideo.mockRejectedValue(new Error('404'))
  const { w } = await setup('/?video=99')
  expect(w.getComponent(Theater).props('error')).toContain('không còn công khai')
  expect(document.querySelector('.video-theater-product')).toBeNull()
})
it('media failures show cover fallback and preserve navigation', async () => {
  await setup('/?video=1')
  document.querySelector('video').dispatchEvent(new Event('error'))
  await flushPromises()
  expect(document.querySelector('.video-media-error')).toBeTruthy()
  expect(document.querySelector('a[href="/products/camera"]')).toBeTruthy()
})
it('load errors have retry without conflicting empty state', async () => {
  api.listPublicVideos.mockRejectedValueOnce(new Error('network'))
  const { w } = await setup()
  await videos(w)
  expect(w.text()).toContain('Không thể tải')
  expect(w.text()).not.toContain('Chưa có Video đánh giá')
  await button(w, 'Tải lại').trigger('click')
  await flushPromises()
  expect(w.findAll('.video-review-card')).toHaveLength(2)
})
it('cleans media, timer and listeners when unmounted', async () => {
  const clear = vi.spyOn(window, 'clearInterval')
  const { w } = await setup('/?video=1')
  w.unmount()
  expect(clear).toHaveBeenCalled()
  expect(media.every((m) => m.removeEventListener.mock.calls.length === 1)).toBe(true)
  expect(HTMLMediaElement.prototype.pause).toHaveBeenCalled()
  expect(document.querySelector('[role=dialog]')).toBeNull()
})
it('arrow shortcuts work from dialog buttons and focus wraps', async () => {
  const { router } = await setup('/?video=1')
  document.activeElement.dispatchEvent(
    new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }),
  )
  await flushPromises()
  expect(router.currentRoute.value.query.video).toBe('2')
  const close = document.querySelector('.ui-dialog-header button')
  close.focus()
  close.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true }))
  expect(document.activeElement.textContent.trim()).toBe('↑ Video trước')
})
it('ignores a stale deep-link request after closing', async () => {
  let resolve
  api.getPublicVideo.mockImplementationOnce(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  const { w, router } = await setup('/?video=1')
  w.getComponent(Theater).vm.$emit('close')
  await flushPromises()
  resolve(row())
  await flushPromises()
  expect(router.currentRoute.value.query.video).toBeUndefined()
  expect(document.querySelector('[role=dialog]')).toBeNull()
})
it('rechecks visibility and stops media when product becomes unavailable', async () => {
  vi.useFakeTimers()
  const { w } = await setup('/?video=1')
  api.getPublicVideo.mockRejectedValueOnce(new Error('404'))
  await vi.advanceTimersByTimeAsync(15000)
  await flushPromises()
  expect(w.getComponent(Theater).props('video')).toBeNull()
  expect(w.getComponent(Theater).props('error')).toContain('không còn công khai')
  expect(document.querySelector('video')).toBeNull()
})
it('supports feed pagination and coherent empty state', async () => {
  api.listPublicVideos.mockResolvedValueOnce({
    data: [row()],
    meta: { current_page: 1, last_page: 2 },
  })
  const { w } = await setup()
  await videos(w)
  api.listPublicVideos.mockResolvedValueOnce({ data: [], meta: { current_page: 2, last_page: 2 } })
  await button(w, 'Sau').trigger('click')
  await flushPromises()
  expect(api.listPublicVideos).toHaveBeenLastCalledWith({ page: 2, per_page: 12 })
  expect(w.text()).toContain('Chưa có Video đánh giá')
})
