import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { useAuthStore } from '../stores/auth'
import * as shopApi from '../api/shop.api'
import ShopReviews from '../components/ShopReviews.vue'
import StarPicker from '../components/ui/StarPicker.vue'

const shop = { id: '20', slug: 'goc-tao-cu', name: 'Góc Táo Cũ' }
const summary = { average: 4.3, count: 3, distribution: { 5: 2, 4: 0, 3: 1, 2: 0, 1: 0 } }
const page = (data, extra = {}) => ({
  data,
  summary,
  meta: { current_page: 1, last_page: 1 },
  ...extra,
})
const review = {
  id: '1',
  rating: 5,
  comment: 'Giao nhanh',
  author: { name: 'Lan' },
  updated_at: '2026-10-18T10:00:00Z',
}
const wrappers = []

async function setup(user = { id: '4', role: 'USER', status: 'ACTIVE' }) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.initialized = true
  auth.user = user
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/shops/:slug', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
    ],
  })
  await router.push('/shops/goc-tao-cu')
  const wrapper = mount(ShopReviews, {
    props: { shop },
    attachTo: document.body,
    global: { plugins: [pinia, router] },
  })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router }
}
beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(shopApi, 'listShopReviews').mockResolvedValue(page([review]))
  vi.spyOn(shopApi, 'getMyShopReview').mockResolvedValue({
    can_review: true,
    own_shop: false,
    pending_count: 1,
    reviews: [],
  })
  vi.spyOn(shopApi, 'createShopReview').mockResolvedValue({ data: review })
  vi.spyOn(shopApi, 'updateShopReview').mockResolvedValue({ data: review })
})
afterEach(() => {
  wrappers.splice(0).forEach((w) => w.unmount())
  document.body.innerHTML = ''
})

it('shows the star summary, the 5→1 breakdown and the newest ratings', async () => {
  const { wrapper } = await setup()
  expect(shopApi.listShopReviews).toHaveBeenCalledWith('goc-tao-cu', { page: 1 })
  expect(wrapper.get('[data-review-average]').text()).toBe('4.3')
  expect(wrapper.text()).toContain('3 đánh giá')
  expect(wrapper.get('[data-bar="5"]').text()).toContain('2')
  expect(wrapper.get('[data-bar="5"] .shop-reviews-bar > span').attributes('style')).toContain(
    '67%',
  )
  expect(wrapper.get('[data-shop-review]').text()).toContain('Lan')
  expect(wrapper.get('[data-shop-review]').text()).toContain('Giao nhanh')
  expect(wrapper.emitted('summary')[0]).toEqual([summary])
})

it('an eligible buyer picks stars and sends the rating with a trimmed comment', async () => {
  const { wrapper } = await setup()
  await wrapper.get('[data-review-shop]').trigger('click')
  const dialog = document.querySelector('#shop-review-dialog')
  expect(dialog).not.toBeNull()
  // Sending without stars asks for them first.
  document.querySelector('#shop-review-form').dispatchEvent(new Event('submit'))
  await flushPromises()
  expect(shopApi.createShopReview).not.toHaveBeenCalled()
  expect(document.body.textContent).toContain('Hãy chọn số sao.')
  document.querySelector('[data-star="4"]').click()
  const comment = document.querySelector('#shop-review-comment')
  comment.value = '  Đóng gói kỹ  '
  comment.dispatchEvent(new Event('input'))
  document.querySelector('#shop-review-form').dispatchEvent(new Event('submit'))
  await flushPromises()
  expect(shopApi.createShopReview).toHaveBeenCalledWith('goc-tao-cu', {
    rating: 4,
    comment: 'Đóng gói kỹ',
  })
  expect(document.querySelector('#shop-review-dialog')).toBeNull()
  expect(shopApi.listShopReviews).toHaveBeenCalledTimes(2)
})

it('a buyer edits one of their ratings and is told how many completed orders are left to rate', async () => {
  shopApi.getMyShopReview.mockResolvedValue({
    can_review: true,
    own_shop: false,
    pending_count: 2,
    reviews: [{ ...review, rating: 3, comment: 'Ổn' }],
  })
  const { wrapper } = await setup()
  expect(wrapper.get('[data-review-shop]').text()).toBe('Đánh giá gian hàng')
  expect(wrapper.get('[data-review-note]').text()).toBe('Bạn còn 2 đơn hoàn tất chưa đánh giá.')
  await wrapper.get('[data-edit-shop-review]').trigger('click')
  expect(document.querySelector('#shop-review-dialog').textContent).toContain('Sửa đánh giá gian hàng')
  expect(document.querySelector('[data-star="3"]').getAttribute('aria-checked')).toBe('true')
  expect(document.querySelector('#shop-review-comment').value).toBe('Ổn')
  document.querySelector('[data-star="5"]').click()
  document.querySelector('#shop-review-form').dispatchEvent(new Event('submit'))
  await flushPromises()
  expect(shopApi.updateShopReview).toHaveBeenCalledWith('1', { rating: 5, comment: 'Ổn' })
  expect(shopApi.createShopReview).not.toHaveBeenCalled()
})

it('explains when every order is rated, when none is completed, and hides the button from the owner', async () => {
  shopApi.getMyShopReview.mockResolvedValue({
    can_review: false,
    own_shop: false,
    pending_count: 0,
    reviews: [review],
  })
  const done = (await setup()).wrapper
  expect(done.find('[data-review-shop]').exists()).toBe(false)
  expect(done.get('[data-review-note]').text()).toContain('Bạn đã đánh giá mọi đơn hoàn tất')
  expect(done.get('[data-edit-shop-review]').exists()).toBe(true)
  done.unmount()
  wrappers.splice(0)

  shopApi.getMyShopReview.mockResolvedValue({ can_review: false, own_shop: false, pending_count: 0, reviews: [] })
  const notYet = (await setup()).wrapper
  expect(notYet.find('[data-review-shop]').exists()).toBe(false)
  expect(notYet.text()).toContain('sau khi có đơn hàng hoàn tất')
  expect(notYet.find('[data-edit-shop-review]').exists()).toBe(false)

  shopApi.getMyShopReview.mockResolvedValue({ can_review: false, own_shop: true, pending_count: 0, reviews: [] })
  const owner = (await setup()).wrapper
  expect(owner.find('[data-review-shop]').exists()).toBe(false)
  expect(owner.text()).not.toContain('sau khi có đơn hàng hoàn tất')
})
it('a guest sees the ratings and is sent to sign in to rate', async () => {
  const { wrapper, router } = await setup(null)
  expect(shopApi.getMyShopReview).not.toHaveBeenCalled()
  await wrapper.get('[data-review-shop]').trigger('click')
  await flushPromises()
  expect(router.currentRoute.value.name).toBe('login')
  expect(router.currentRoute.value.query.redirect).toBe('/shops/goc-tao-cu')
})

it('shows the server refusal when the purchase is not completed', async () => {
  shopApi.createShopReview.mockRejectedValue({
    response: {
      status: 403,
      data: { message: 'Chỉ người đã mua và nhận hàng hoàn tất từ gian hàng mới đánh giá được.' },
    },
  })
  const { wrapper } = await setup()
  await wrapper.get('[data-review-shop]').trigger('click')
  document.querySelector('[data-star="5"]').click()
  document.querySelector('#shop-review-form').dispatchEvent(new Event('submit'))
  await flushPromises()
  expect(document.querySelector('#shop-review-dialog').textContent).toContain('nhận hàng hoàn tất')
})

it('star picker is a radio group moved with the arrow keys', async () => {
  const wrapper = mount(StarPicker, { props: { modelValue: 2 }, attachTo: document.body })
  wrappers.push(wrapper)
  expect(wrapper.get('[role=radiogroup]').attributes('aria-label')).toBe('Số sao')
  expect(wrapper.findAll('[role=radio]')).toHaveLength(5)
  expect(wrapper.get('[data-star="2"]').attributes('tabindex')).toBe('0')
  await wrapper.get('[role=radiogroup]').trigger('keydown', { key: 'ArrowRight' })
  expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([3])
  await wrapper.get('[data-star="5"]').trigger('click')
  expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([5])
  // The word for the current value (2 stars) is announced next to the stars.
  expect(wrapper.get('.star-picker-word').text()).toBe('Tệ')
})
