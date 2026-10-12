import { mount, flushPromises } from '@vue/test-utils'
import { beforeEach, afterEach, it, expect, vi } from 'vitest'
import * as api from '../api/productVideo.api'
import Editor from '../components/ProductVideosEditor.vue'
import PublicVideos from '../components/ProductVideos.vue'
const product = { id: '10', can_edit: true, status: 'ACTIVE' }
const video = (id = '1') => ({
  id,
  title: 'Video sản phẩm',
  description: 'Mô tả trung thực',
  url: `/storage/product-videos/10/${id}.webm`,
  poster_url: null,
  duration_seconds: null,
  is_primary: id === '1',
  sort_order: Number(id) - 1,
  status: 'READY',
  file_size: 4098,
})
let rows,
  wrappers = []
beforeEach(() => {
  vi.restoreAllMocks()
  rows = [video(), video('2')]
  vi.stubGlobal(
    'URL',
    class extends URL {
      static createObjectURL = vi.fn(() => 'blob:video-preview')
      static revokeObjectURL = vi.fn()
    },
  )
  vi.spyOn(api, 'listSellerVideos').mockImplementation(async () => ({
    data: structuredClone(rows),
    limits: { max_files: 3, max_bytes: 50 * 1024 * 1024 },
  }))
  vi.spyOn(api, 'listProductVideos').mockImplementation(async () => ({
    data: structuredClone(rows),
  }))
  vi.spyOn(api, 'uploadVideo').mockImplementation(async (id, file, metadata, progress) => {
    progress(100)
    rows.push({ ...video('3'), ...metadata })
    return { data: rows.at(-1) }
  })
  vi.spyOn(api, 'updateVideo').mockImplementation(async (id, key, data) => {
    Object.assign(
      rows.find((r) => r.id === key),
      data,
    )
    return { data: rows.find((r) => r.id === key) }
  })
  vi.spyOn(api, 'deleteVideo').mockImplementation(async (id, key) => {
    rows = rows.filter((r) => r.id !== key)
  })
})
afterEach(() => {
  wrappers.splice(0).forEach((w) => w.unmount())
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})
async function setup(props = { product }) {
  const w = mount(Editor, { props })
  wrappers.push(w)
  await flushPromises()
  return w
}
async function choose(w, file) {
  const node = w.get('input[type=file]')
  Object.defineProperty(node.element, 'files', { value: [file], configurable: true })
  await node.trigger('change')
}
const button = (w, text) => w.findAll('button').find((b) => b.text() === text)
it('loads owned videos and shows primary/status without autoplay or preload', async () => {
  const w = await setup()
  expect(api.listSellerVideos).toHaveBeenCalledWith('10')
  expect(w.findAll('video')).toHaveLength(2)
  expect(w.text()).toContain('Video chính')
  for (const v of w.findAll('video')) {
    expect(v.attributes('preload')).toBe('none')
    expect(v.attributes('autoplay')).toBeUndefined()
    expect(v.attributes('controls')).toBeDefined()
    expect(v.attributes('aria-label')).toBeTruthy()
  }
})
it('new drafts and read-only products cannot upload', async () => {
  const w = await setup({ product: { id: null, can_edit: true } })
  expect(w.get('fieldset').attributes('disabled')).toBeDefined()
  expect(api.listSellerVideos).not.toHaveBeenCalled()
  await w.setProps({ product: { ...product, can_edit: false } })
  await flushPromises()
  expect(w.get('fieldset').attributes('disabled')).toBeDefined()
})
it('accepts a video preview, uploads metadata and reports progress/busy', async () => {
  const w = await setup()
  const file = new File(['video'], 'demo.webm', { type: 'video/webm' })
  await choose(w, file)
  expect(w.get('video[src="blob:video-preview"]').attributes('preload')).toBe('none')
  await w.get('#product-video-title').setValue('Đánh giá máy')
  await w.get('form').trigger('submit')
  await flushPromises()
  expect(api.uploadVideo).toHaveBeenCalledWith(
    '10',
    file,
    { title: 'Đánh giá máy', description: '' },
    expect.any(Function),
  )
  expect(w.emitted('changed')).toHaveLength(1)
  expect(w.emitted('busy-change')).toEqual([[true], [false]])
  expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:video-preview')
})
it('rejects invalid type, wrong extension and oversized video before upload', async () => {
  const w = await setup()
  for (const file of [
    new File(['php'], 'bad.php', { type: 'application/x-php' }),
    new File(['webm'], 'bad.exe', { type: 'video/webm' }),
  ]) {
    await choose(w, file)
    expect(w.get('[role=alert]').text()).toContain('MP4 hoặc WebM')
  }
  const large = new File(['a'], 'large.mp4', { type: 'video/mp4' })
  Object.defineProperty(large, 'size', { value: 50 * 1024 * 1024 + 1 })
  await choose(w, large)
  expect(w.get('[role=alert]').text()).toContain('50 MiB')
  expect(api.uploadVideo).not.toHaveBeenCalled()
})
it('shows server validation messages and retains selected preview for retry', async () => {
  api.uploadVideo.mockRejectedValue({
    response: { status: 422, data: { errors: { video: ['Invalid container'] } } },
  })
  const w = await setup()
  await choose(w, new File(['x'], 'review.mp4', { type: 'video/mp4' }))
  await w.get('form').trigger('submit')
  await flushPromises()
  expect(w.text()).toContain('Video không hợp lệ')
  expect(w.find('video[src="blob:video-preview"]').exists()).toBe(true)
})
it('respects the three-video maximum from server limits', async () => {
  rows.push(video('3'))
  const w = await setup()
  expect(w.get('fieldset').attributes('disabled')).toBeDefined()
  expect(w.text()).toContain('Đã đạt giới hạn video')
})
it('metadata save/cancel are explicit and unsaved edits are reported', async () => {
  const w = await setup()
  await button(w.get('[data-video="1"]'), 'Sửa thông tin').trigger('click')
  await w.get('#product-video-edit-1').setValue('New title')
  expect(w.emitted('dirty-change').at(-1)).toEqual([true])
  await w.get('[data-video="1"] form').trigger('submit')
  await flushPromises()
  expect(api.updateVideo).toHaveBeenCalledWith('10', '1', {
    title: 'New title',
    description: 'Mô tả trung thực',
  })
  expect(w.emitted('dirty-change').at(-1)).toEqual([false])
  await button(w.get('[data-video="1"]'), 'Sửa thông tin').trigger('click')
  await button(w.get('[data-video="1"]'), 'Hủy').trigger('click')
  expect(w.find('#product-video-edit-1').exists()).toBe(false)
})
it('primary, move and hide use existing child IDs and safe actions', async () => {
  const w = await setup()
  await button(w.get('[data-video="2"]'), 'Đặt video chính').trigger('click')
  await flushPromises()
  expect(api.updateVideo).toHaveBeenCalledWith('10', '2', { is_primary: true })
  await button(w.get('[data-video="2"]'), 'Lên').trigger('click')
  await flushPromises()
  expect(api.updateVideo).toHaveBeenCalledWith('10', '2', { sort_order: 0 })
  await button(w.get('[data-video="1"]'), 'Ẩn video').trigger('click')
  await flushPromises()
  expect(api.updateVideo).toHaveBeenCalledWith('10', '1', { status: 'HIDDEN' })
})
it('delete requires explicit confirmation and can be cancelled', async () => {
  const w = await setup()
  await button(w.get('[data-video="1"]'), 'Xóa video').trigger('click')
  expect(api.deleteVideo).not.toHaveBeenCalled()
  await button(w.get('[data-video="1"]'), 'Giữ lại').trigger('click')
  expect(w.find('[aria-label="Xác nhận xóa video"]').exists()).toBe(false)
  await button(w.get('[data-video="1"]'), 'Xóa video').trigger('click')
  await button(w.get('[data-video="1"]'), 'Xác nhận xóa video').trigger('click')
  await flushPromises()
  expect(api.deleteVideo).toHaveBeenCalledWith('10', '1')
  expect(w.find('[data-video="1"]').exists()).toBe(false)
})
it('network load errors offer retry rather than a conflicting empty view', async () => {
  api.listSellerVideos.mockRejectedValueOnce(new Error('network'))
  const w = await setup()
  expect(w.find('[role=alert]').exists()).toBe(true)
  expect(w.find('input[type=file]').exists()).toBe(false)
  await button(w, 'Tải lại video').trigger('click')
  await flushPromises()
  expect(w.find('[data-video="1"]').exists()).toBe(true)
})
it('cleans preview URLs on replacement and unmount', async () => {
  const w = await setup()
  await choose(w, new File(['a'], 'a.webm', { type: 'video/webm' }))
  await choose(w, new File(['b'], 'b.webm', { type: 'video/webm' }))
  expect(URL.revokeObjectURL).toHaveBeenCalledTimes(1)
  w.unmount()
  expect(URL.revokeObjectURL).toHaveBeenCalledTimes(2)
})
it('old product responses cannot appear after switching to a different product', async () => {
  let resolve
  api.listSellerVideos.mockImplementationOnce(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  const w = await setup()
  rows = []
  await w.setProps({ product: { ...product, id: '11' } })
  await flushPromises()
  resolve({ data: [video()] })
  await flushPromises()
  expect(w.find('[data-video="1"]').exists()).toBe(false)
})
it('public detail displays controls and fallback, with plain-text metadata', async () => {
  rows[0].description = '<img src=x onerror=alert(1)>'
  const w = mount(PublicVideos, { props: { slug: 'demo' } })
  wrappers.push(w)
  await flushPromises()
  expect(api.listProductVideos).toHaveBeenCalledWith('demo')
  expect(w.text()).toContain('Nhấn phát')
  expect(w.text()).toContain('<img src=x onerror=alert(1)>')
  expect(w.find('img').exists()).toBe(false)
  expect(w.get('video').attributes('autoplay')).toBeUndefined()
  expect(w.get('video').attributes('preload')).toBe('none')
})
it('public section disappears when no READY videos exist', async () => {
  rows = []
  const w = mount(PublicVideos, { props: { slug: 'demo' } })
  wrappers.push(w)
  await flushPromises()
  expect(w.find('section').exists()).toBe(false)
})
it('public video errors have a retry and never block product behavior', async () => {
  api.listProductVideos.mockRejectedValueOnce(new Error('offline'))
  const w = mount(PublicVideos, { props: { slug: 'demo' } })
  wrappers.push(w)
  await flushPromises()
  expect(w.find('[role=alert]').exists()).toBe(true)
  await button(w, 'Tải lại video').trigger('click')
  await flushPromises()
  expect(w.findAll('video')).toHaveLength(2)
})
