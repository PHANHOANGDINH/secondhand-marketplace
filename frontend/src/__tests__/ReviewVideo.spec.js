import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { useAuthStore } from '../stores/auth'
import * as reviewApi from '../api/review.api'
import ReviewForm from '../components/ReviewForm.vue'
import ProductReviews from '../components/ProductReviews.vue'

const item = { id: '11', can_review: true, can_edit_review: false, review: null }
const review = { id: '21', rating: 5, comment: null, status: 'PUBLISHED', video: null }
const clip = { url: 'http://localhost/storage/review-videos/21/a.mp4', mime_type: 'video/mp4', size: 1000 }
const wrappers = []
const file = (type = 'video/mp4', size = 1000) => {
  const value = new File(['x'], type === 'video/mp4' ? 'clip.mp4' : 'clip.bin', { type })
  Object.defineProperty(value, 'size', { value: size })
  return value
}
async function choose(wrapper, chosen) {
  const input = wrapper.get('input[type=file]')
  Object.defineProperty(input.element, 'files', { value: [chosen], configurable: true })
  await input.trigger('change')
}
beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(reviewApi, 'createReview').mockResolvedValue(review)
  vi.spyOn(reviewApi, 'updateReview').mockResolvedValue(review)
  vi.spyOn(reviewApi, 'uploadReviewVideo').mockResolvedValue({ ...review, video: clip })
  vi.spyOn(reviewApi, 'removeReviewVideo').mockResolvedValue(review)
})
afterEach(() => wrappers.splice(0).forEach((w) => w.unmount()))

it('video is optional: a review without one never calls the upload', async () => {
  const wrapper = mount(ReviewForm, { props: { item } })
  wrappers.push(wrapper)
  expect(wrapper.get('input[type=file]').attributes('accept')).toBe('video/mp4,video/webm')
  expect(wrapper.text()).toContain('tối đa 50 MB')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(reviewApi.createReview).toHaveBeenCalledWith('11', { rating: 5, comment: null })
  expect(reviewApi.uploadReviewVideo).not.toHaveBeenCalled()
  expect(wrapper.emitted('saved')[0]).toEqual([review])
})

it('uploads the chosen video after saving and reports the review with it', async () => {
  const wrapper = mount(ReviewForm, { props: { item } })
  wrappers.push(wrapper)
  const chosen = file()
  await choose(wrapper, chosen)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(reviewApi.uploadReviewVideo).toHaveBeenCalledWith('21', chosen)
  expect(wrapper.emitted('saved')[0]).toEqual([{ ...review, video: clip }])
})

it('rejects a wrong type or a file over 50 MB before sending anything', async () => {
  const wrapper = mount(ReviewForm, { props: { item } })
  wrappers.push(wrapper)
  await choose(wrapper, file('video/quicktime'))
  expect(wrapper.get('[data-video-error]').text()).toBe('Chỉ nhận video MP4 hoặc WebM.')
  await choose(wrapper, file('video/mp4', reviewApi.REVIEW_VIDEO_MAX_BYTES + 1))
  expect(wrapper.get('[data-video-error]').text()).toBe('Video tối đa 50 MB.')
  expect(wrapper.get('button[type=submit]').attributes('disabled')).toBeDefined()
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(reviewApi.createReview).not.toHaveBeenCalled()
})

it('a failed upload keeps the saved review: retrying edits it instead of creating a second one', async () => {
  reviewApi.uploadReviewVideo.mockRejectedValueOnce({ response: { status: 500, data: {} } })
  const wrapper = mount(ReviewForm, { props: { item } })
  wrappers.push(wrapper)
  await choose(wrapper, file())
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.emitted('saved')).toBeUndefined()
  expect(wrapper.get('button[type=submit]').text()).toBe('Lưu đánh giá')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(reviewApi.createReview).toHaveBeenCalledTimes(1)
  expect(reviewApi.updateReview).toHaveBeenCalledWith('21', { rating: 5, comment: null })
  expect(reviewApi.uploadReviewVideo).toHaveBeenCalledTimes(2)
  expect(wrapper.emitted('saved')).toHaveLength(1)
})

it('an existing video is shown and can be removed', async () => {
  const wrapper = mount(ReviewForm, {
    props: { item: { ...item, can_review: false, can_edit_review: true, review: { ...review, video: clip } } },
  })
  wrappers.push(wrapper)
  expect(wrapper.get('video').attributes('src')).toBe(clip.url)
  await wrapper.get('input[name=remove_video]').setValue(true)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(reviewApi.removeReviewVideo).toHaveBeenCalledWith('21')
  expect(reviewApi.uploadReviewVideo).not.toHaveBeenCalled()
})

it('public reviews play their video', async () => {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.initialized = true
  auth.user = null
  vi.spyOn(reviewApi, 'listProductReviews').mockResolvedValue({
    data: [{ ...review, comment: 'Đúng mô tả', author: { name: 'Lan' }, video: clip }],
    meta: { current_page: 1, last_page: 1, total: 1 },
  })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:p(.*)*', component: { template: '<div />' } }],
  })
  await router.push('/products/phone')
  const wrapper = mount(ProductReviews, {
    props: { product: { id: '1', slug: 'phone', review_count: 1, average_rating: 5 } },
    global: { plugins: [pinia, router] },
  })
  wrappers.push(wrapper)
  await flushPromises()
  const video = wrapper.get('[data-review-video]')
  expect(video.attributes('src')).toBe(clip.url)
  expect(video.attributes('aria-label')).toBe('Video đánh giá của Lan')
  expect(video.attributes('controls')).toBeDefined()
})
