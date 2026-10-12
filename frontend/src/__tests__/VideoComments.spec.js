import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { beforeEach, afterEach, it, expect, vi } from 'vitest'
import Comments from '../components/home/VideoComments.vue'
import Theater from '../components/home/VideoTheater.vue'
import { useAuthStore } from '../stores/auth'
import * as api from '../api/videoComment.api'
const realtime = vi.hoisted(() => ({ subscriptions: [], subscribe: vi.fn() }))
vi.mock('../composables/auctionEcho', async () => {
  const { ref } = await import('vue')
  return { realtimeConnection: ref('connected'), subscribeRealtime: realtime.subscribe }
})
const row = (id = '1', body = 'Bình luận thật', author = '10') => ({
  id,
  body,
  author: { id: author, name: 'Người mua' },
  created_at: '2026-10-09T00:00:00Z',
  can_manage: false,
})
let wrappers
beforeEach(() => {
  wrappers = []
  realtime.subscriptions = []
  realtime.subscribe.mockImplementation((kind, id, handlers, ready) => {
    const release = vi.fn()
    realtime.subscriptions.push({ kind, id, handlers, ready, release })
    return release
  })
  vi.spyOn(api, 'listVideoComments').mockResolvedValue({
    data: [row()],
    permissions: { can_moderate: false },
  })
  vi.spyOn(api, 'createVideoComment').mockResolvedValue(row('2'))
  vi.spyOn(api, 'hideVideoComment').mockResolvedValue()
})
afterEach(() => {
  wrappers.forEach((w) => w.unmount())
  vi.restoreAllMocks()
  vi.clearAllMocks()
  document.body.innerHTML = ''
})
async function setup(user = { id: '10', role: 'USER' }) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = user
  auth.initialized = true
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
    ],
  })
  await router.push('/?video=1')
  await router.isReady()
  const w = mount(Comments, {
    props: { videoId: '1' },
    attachTo: document.body,
    global: { plugins: [pinia, router] },
  })
  wrappers.push(w)
  await flushPromises()
  return { w, auth, router }
}
const button = (w, text) => w.findAll('button').find((b) => b.text() === text)
const event = (comment, video = '1') =>
  realtime.subscriptions.at(-1).handlers.VideoCommentCreated({ video_id: video, comment })
it('loads real comments as text with a labelled composer', async () => {
  api.listVideoComments.mockResolvedValue({
    data: [row('1', '<script>alert(1)</script>')],
    permissions: {},
  })
  const { w } = await setup()
  expect(api.listVideoComments).toHaveBeenCalledWith('1', { per_page: 20 })
  expect(w.text()).toContain('<script>alert(1)</script>')
  expect(w.find('script').exists()).toBe(false)
  expect(w.get('label').attributes('for')).toBe('video-comment-body')
  expect(w.get('textarea').attributes('maxlength')).toBe('500')
})
it('guests see safe return to the same Theater and cannot compose', async () => {
  const { w } = await setup(null)
  expect(w.find('textarea').exists()).toBe(false)
  expect(w.get('a').attributes('href')).toContain('redirect=/?video=1')
  expect(w.text()).toContain('Đăng nhập để bình luận')
})
it('submits trimmed text and reconciles its own realtime event once', async () => {
  api.createVideoComment.mockImplementation(async () => {
    event(row('2'))
    return row('2')
  })
  const { w } = await setup()
  await w.get('textarea').setValue('  Hello  ')
  await w.get('form').trigger('submit')
  await flushPromises()
  expect(api.createVideoComment).toHaveBeenCalledWith('1', 'Hello')
  expect(w.findAll('[data-comment="2"]')).toHaveLength(1)
  expect(w.get('textarea').element.value).toBe('')
})
it('prevents double submission and disables controls while pending', async () => {
  let resolve
  api.createVideoComment.mockImplementation(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  const { w } = await setup()
  await w.get('textarea').setValue('Hello')
  await w.get('form').trigger('submit')
  await w.get('form').trigger('submit')
  expect(api.createVideoComment).toHaveBeenCalledOnce()
  expect(w.get('textarea').attributes('disabled')).toBeDefined()
  resolve(row('2'))
  await flushPromises()
})
it('shows backend body validation and preserves draft for retry', async () => {
  api.createVideoComment.mockRejectedValue({
    response: { status: 422, data: { errors: { body: ['Maximum 500'] } } },
  })
  const { w } = await setup()
  await w.get('textarea').setValue('Draft')
  await w.get('form').trigger('submit')
  await flushPromises()
  expect(w.get('[role=alert]').text()).toContain('Nội dung không hợp lệ')
  expect(w.get('textarea').element.value).toBe('Draft')
})
it('inserts realtime messages in ascending order and ignores another video', async () => {
  const { w } = await setup()
  event(row('3'))
  event(row('2'))
  event(row('9'), '99')
  await flushPromises()
  expect(w.findAll('[data-comment]').map((n) => n.attributes('data-comment'))).toEqual([
    '1',
    '2',
    '3',
  ])
})
it('does not force scroll when reading earlier comments and offers new-comment affordance', async () => {
  const { w } = await setup()
  const node = w.get('.video-comments-region')
  Object.defineProperty(node.element, 'scrollHeight', { configurable: true, value: 500 })
  Object.defineProperty(node.element, 'clientHeight', { configurable: true, value: 100 })
  node.element.scrollTop = 100
  await node.trigger('scroll')
  event(row('2'))
  await flushPromises()
  expect(node.element.scrollTop).toBe(100)
  expect(w.text()).toContain('Có bình luận mới ↓')
  await button(w, 'Có bình luận mới ↓').trigger('click')
  await flushPromises()
  expect(node.element.scrollTop).toBe(500)
})
it('follows newest when already at the bottom', async () => {
  const { w } = await setup()
  const node = w.get('.video-comments-region')
  Object.defineProperty(node.element, 'scrollHeight', { configurable: true, value: 500 })
  Object.defineProperty(node.element, 'clientHeight', { configurable: true, value: 100 })
  node.element.scrollTop = 400
  await node.trigger('scroll')
  event(row('2'))
  await flushPromises()
  expect(node.element.scrollTop).toBe(500)
})
it('cleans subscription and ignores stale loads/events on rapid video changes', async () => {
  let resolve
  api.listVideoComments.mockImplementationOnce(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  const { w } = await setup()
  const old = realtime.subscriptions[0]
  api.listVideoComments.mockResolvedValueOnce({ data: [row('2', 'Video B')], permissions: {} })
  await w.setProps({ videoId: '2' })
  await flushPromises()
  resolve({ data: [row('1', 'Old video A')], permissions: {} })
  old.handlers.VideoCommentCreated({ video_id: '1', comment: row('8') })
  await flushPromises()
  expect(old.release).toHaveBeenCalledOnce()
  expect(w.text()).toContain('Video B')
  expect(w.text()).not.toContain('Old video A')
  w.unmount()
  expect(realtime.subscriptions[1].release).toHaveBeenCalledOnce()
})
it('reconnect refreshes persisted comments and deduplicates against events', async () => {
  const { w } = await setup()
  api.listVideoComments.mockResolvedValueOnce({
    data: [row('2', 'While offline'), row()],
    permissions: {},
  })
  realtime.subscriptions[0].ready()
  await flushPromises()
  event(row('2', 'While offline'))
  await flushPromises()
  expect(w.findAll('[data-comment="2"]')).toHaveLength(1)
})
it('requires moderation confirmation and removes hidden messages from realtime', async () => {
  const { w } = await setup()
  await button(w, 'Ẩn bình luận').trigger('click')
  expect(api.hideVideoComment).not.toHaveBeenCalled()
  await button(w, 'Giữ lại').trigger('click')
  await button(w, 'Ẩn bình luận').trigger('click')
  await button(w, 'Xác nhận ẩn').trigger('click')
  await flushPromises()
  expect(api.hideVideoComment).toHaveBeenCalledWith('1')
  event(row('1'))
  await flushPromises()
  expect(w.find('[data-comment="1"]').exists()).toBe(false)
})
it('seller/admin permission applies to realtime rows while strangers see no action', async () => {
  api.listVideoComments.mockResolvedValue({
    data: [row('1', 'Other user', '9')],
    permissions: { can_moderate: true },
  })
  const { w, auth } = await setup()
  expect(w.text()).toContain('Ẩn bình luận')
  event(row('2', 'New other user', '9'))
  await flushPromises()
  expect(w.findAll('button').filter((b) => b.text() === 'Ẩn bình luận')).toHaveLength(2)
  api.listVideoComments.mockResolvedValue({
    data: [row('1', 'Other user', '9')],
    permissions: { can_moderate: false },
  })
  auth.user = { id: '11' }
  await flushPromises()
  expect(w.text()).not.toContain('Ẩn bình luận')
})
it('hide arriving during refresh cannot resurrect text from an older REST snapshot', async () => {
  const { w } = await setup()
  let resolve
  api.listVideoComments.mockImplementationOnce(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  realtime.subscriptions[0].ready()
  await flushPromises()
  realtime.subscriptions[0].handlers.VideoCommentHidden({ video_id: '1', comment_id: '1' })
  resolve({ data: [row()], permissions: {} })
  await flushPromises()
  expect(w.find('[data-comment="1"]').exists()).toBe(false)
})
it('has retry after initial load failure without a conflicting empty state', async () => {
  api.listVideoComments.mockRejectedValueOnce(new Error('offline'))
  const { w } = await setup()
  expect(w.find('[role=alert]').exists()).toBe(true)
  expect(w.text()).not.toContain('Chưa có bình luận')
  await button(w, 'Tải lại').trigger('click')
  await flushPromises()
  expect(w.find('[data-comment="1"]').exists()).toBe(true)
})
it('comment-region arrows retain native scrolling while header arrows navigate', async () => {
  const { w } = await setup()
  vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(() => {})
  vi.spyOn(HTMLMediaElement.prototype, 'load').mockImplementation(() => {})
  const theater = mount(Theater, {
    attachTo: document.body,
    props: {
      video: {
        id: '1',
        url: '/review.webm',
        product: { name: 'Product', price: '100.00', condition: 'GOOD', slug: 'product' },
      },
    },
    global: {
      plugins: [w.vm.$pinia, w.vm.$router],
      stubs: {
        VideoComments: {
          template: '<section class="theater-comments" tabindex="0">Comments</section>',
        },
      },
    },
  })
  wrappers.push(theater)
  await flushPromises()
  document
    .querySelector('.ui-dialog .theater-comments')
    .dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }))
  expect(theater.emitted('move')).toBeUndefined()
  document
    .querySelector('.ui-dialog-header button')
    .dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }))
  expect(theater.emitted('move')).toEqual([[1]])
})
