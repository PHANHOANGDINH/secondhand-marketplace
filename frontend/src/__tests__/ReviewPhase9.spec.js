import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia, disposePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import * as reviewApi from '../api/review.api'
import * as productApi from '../api/product.api'
import * as orderApi from '../api/order.api'
import axios from '../api/axios'
import ProductReviews from '../components/ProductReviews.vue'
import ReviewForm from '../components/ReviewForm.vue'
import OrderItems from '../components/OrderItems.vue'
import ProductView from '../views/ProductView.vue'
import OrderView from '../views/OrderView.vue'
const product = {
  id: '1',
  name: 'Phone',
  slug: 'phone',
  description: 'Used phone',
  price: '10.00',
  stock_quantity: 3,
  condition: 'GOOD',
  images: [],
  review_count: 2,
  average_rating: 4.5,
}
const review = {
  id: '21',
  rating: 5,
  comment: 'Works well',
  status: 'PUBLISHED',
  author: { name: 'Buyer' },
}
const item = {
  id: '11',
  product_id: '1',
  product_name: 'Phone snapshot',
  product_image_url: null,
  product_price: '10.00',
  quantity: 1,
  subtotal: '10.00',
  can_review: true,
  can_edit_review: false,
  review: null,
}
const collection = (data, extra = {}) => ({
  data,
  meta: { current_page: 1, last_page: 1, total: data.length, ...extra },
})
const wrappers = [],
  piniaInstances = []
async function setup(component, { props = {}, guest = false, path = '/products/phone' } = {}) {
  const pinia = createPinia()
  piniaInstances.push(pinia)
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.initialized = true
  auth.user = guest ? null : { id: '4', status: 'ACTIVE', role: 'USER' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: ['/products', '/products/:slug', '/orders/:id', '/shops/:slug', '/login'].map(
      (path) => ({ path, component: { template: '<div />' } }),
    ),
  })
  await router.push(path)
  const wrapper = mount(component, { props, global: { plugins: [pinia, router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, auth, router }
}
const button = (w, text) => w.findAll('button').find((b) => b.text() === text)
beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(reviewApi, 'listProductReviews').mockResolvedValue(collection([review]))
  vi.spyOn(reviewApi, 'getReviewEligibility').mockResolvedValue(collection([]))
  vi.spyOn(reviewApi, 'createReview').mockResolvedValue(review)
  vi.spyOn(reviewApi, 'updateReview').mockResolvedValue({ ...review, rating: 3 })
  vi.spyOn(productApi, 'getProduct').mockResolvedValue(product)
})
afterEach(() => {
  wrappers.splice(0).forEach((w) => w.unmount())
  piniaInstances.splice(0).forEach(disposePinia)
})
it('guest sees public reviews and average/count without eligibility request or form', async () => {
  const { wrapper } = await setup(ProductReviews, { props: { product }, guest: true })
  expect(wrapper.get('[data-rating-summary]').text()).toContain('4.5 / 5')
  expect(wrapper.get('[data-rating-summary]').text()).toContain('2 đánh giá')
  expect(wrapper.get('[data-public-review]').text()).toContain('Works well')
  expect(reviewApi.getReviewEligibility).not.toHaveBeenCalled()
  expect(wrapper.find('[data-review-form]').exists()).toBe(false)
})
it('empty product shows no ratings and no invented aggregate', async () => {
  reviewApi.listProductReviews.mockResolvedValue(collection([]))
  const { wrapper } = await setup(ProductReviews, {
    props: { product: { ...product, review_count: 0, average_rating: null } },
    guest: true,
  })
  expect(wrapper.get('[data-rating-summary]').text()).toContain('Chưa có đánh giá')
  expect(wrapper.text()).toContain('Chưa có đánh giá công khai')
})
it('public reviews paginate with bounded page size', async () => {
  reviewApi.listProductReviews.mockResolvedValue(collection([review], { last_page: 2 }))
  const { wrapper } = await setup(ProductReviews, { props: { product }, guest: true })
  await button(wrapper, 'Sau').trigger('click')
  await flushPromises()
  expect(reviewApi.listProductReviews).toHaveBeenLastCalledWith('phone', { page: 2, per_page: 10 })
})
it('review load failure is shown with retry', async () => {
  reviewApi.listProductReviews.mockRejectedValueOnce(new Error('Offline'))
  const { wrapper } = await setup(ProductReviews, { props: { product }, guest: true })
  expect(wrapper.text()).toContain('Không thể kết nối')
  await button(wrapper, 'Tải lại đánh giá').trigger('click')
  await flushPromises()
  expect(wrapper.find('[data-public-review]').exists()).toBe(true)
})
it('eligible purchase shows review form with only rating and comment', async () => {
  reviewApi.getReviewEligibility.mockResolvedValue(collection([structuredClone(item)]))
  const { wrapper } = await setup(ProductReviews, { props: { product } })
  await button(wrapper, 'Đánh giá').trigger('click')
  expect(wrapper.get('[data-review-form]').exists()).toBe(true)
  expect(wrapper.findAll('select[name="rating"] option')).toHaveLength(5)
  expect(wrapper.get('textarea').attributes('maxlength')).toBe('2000')
  for (const field of ['user_id', 'product_id', 'status', 'order_item_id'])
    expect(wrapper.find(`[name="${field}"]`).exists()).toBe(false)
})
it('ineligible authenticated user has no create form', async () => {
  const { wrapper } = await setup(ProductReviews, { props: { product } })
  expect(wrapper.text()).toContain('đơn đã hoàn tất')
  expect(wrapper.find('[data-review-form]').exists()).toBe(false)
  expect(button(wrapper, 'Đánh giá')).toBeUndefined()
})
it('submits review derived from item and changes UI to already reviewed', async () => {
  reviewApi.getReviewEligibility.mockResolvedValue(collection([structuredClone(item)]))
  const { wrapper } = await setup(ProductReviews, { props: { product } })
  await button(wrapper, 'Đánh giá').trigger('click')
  await wrapper.get('select[name="rating"]').setValue('4')
  await wrapper.get('textarea').setValue(' Good purchase ')
  reviewApi.createReview.mockResolvedValue({ ...review, rating: 4, comment: 'Good purchase' })
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(reviewApi.createReview).toHaveBeenCalledWith('11', { rating: 4, comment: 'Good purchase' })
  expect(wrapper.text()).toContain('Đã đánh giá: 4 / 5')
  expect(wrapper.emitted('changed')).toHaveLength(1)
})
it('hidden review remains hidden after author edits and has no delete action', async () => {
  const existing = {
    ...item,
    can_review: false,
    can_edit_review: true,
    review: { ...review, status: 'HIDDEN' },
  }
  reviewApi.getReviewEligibility.mockResolvedValue(collection([structuredClone(existing)]))
  reviewApi.updateReview.mockResolvedValue({ ...review, status: 'HIDDEN', rating: 3 })
  const { wrapper } = await setup(ProductReviews, { props: { product } })
  expect(wrapper.text()).toContain('đang được ẩn')
  await button(wrapper, 'Sửa đánh giá').trigger('click')
  await wrapper.get('select').setValue('3')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(reviewApi.updateReview).toHaveBeenCalledWith('21', { rating: 3, comment: 'Works well' })
  expect(wrapper.text()).toContain('đang được ẩn')
  expect(button(wrapper, 'Xóa')).toBeUndefined()
})
it('displays validation messages without hiding unsaved form', async () => {
  reviewApi.createReview.mockRejectedValue({
    response: {
      status: 422,
      data: { errors: { rating: ['Rating invalid'], comment: ['Comment too long'] } },
    },
  })
  const { wrapper } = await setup(ReviewForm, { props: { item } })
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.text()).toContain('Rating invalid')
  expect(wrapper.text()).toContain('Comment too long')
  expect(wrapper.emitted('saved')).toBeUndefined()
})
it('duplicate/conflict response is visible and does not pretend success', async () => {
  reviewApi.createReview.mockRejectedValue({
    response: { status: 409, data: { message: 'Already reviewed' } },
  })
  const { wrapper } = await setup(ReviewForm, { props: { item } })
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.text()).toContain('Already reviewed')
  expect(wrapper.emitted('saved')).toBeUndefined()
})
it('OrderItems shows actions only for permitted Buyer items and preserves reviewed state', async () => {
  const pending = { ...item, id: '12', can_review: false }
  const done = { ...item, id: '13', can_review: false, can_edit_review: true, review }
  const { wrapper } = await setup(OrderItems, {
    props: { items: [item, pending, done], allowReviews: true },
  })
  expect(wrapper.findAll('[data-item-review]')).toHaveLength(2)
  expect(wrapper.text()).toContain('Đã đánh giá: 5 / 5')
  expect(button(wrapper, 'Đánh giá')).toBeDefined()
  const seller = await setup(OrderItems, { props: { items: [item, done], allowReviews: false } })
  expect(seller.wrapper.find('[data-item-review]').exists()).toBe(false)
})
it('Buyer Order detail review action saves and updates that item without changing snapshots', async () => {
  const order = {
    id: '7',
    order_code: 'ORDER',
    shipping_address: { recipient_name: 'Buyer' },
    overall_status: 'COMPLETED',
    payment_status: 'PAID',
    total_amount: '10.00',
    amount_due: '0.00',
    cod_collected_amount: '10.00',
    shop_orders: [
      {
        id: '8',
        status: 'COMPLETED',
        shop: { name: 'Shop' },
        total_amount: '10.00',
        items: [structuredClone(item)],
      },
    ],
  }
  vi.spyOn(orderApi, 'getOrder').mockResolvedValue(order)
  const { wrapper } = await setup(OrderView, { path: '/orders/7' })
  await button(wrapper, 'Đánh giá').trigger('click')
  await wrapper.get('[data-review-form]').trigger('submit')
  await flushPromises()
  expect(wrapper.text()).toContain('Đã đánh giá: 5 / 5')
  expect(wrapper.text()).toContain('Phone snapshot')
  expect(button(wrapper, 'Sửa đánh giá')).toBeDefined()
})
it('Product detail refreshes aggregate after successful review', async () => {
  reviewApi.getReviewEligibility.mockResolvedValue(collection([structuredClone(item)]))
  const { wrapper } = await setup(ProductView)
  await button(wrapper, 'Đánh giá').trigger('click')
  productApi.getProduct.mockResolvedValue({ ...product, review_count: 3, average_rating: 4.67 })
  reviewApi.getReviewEligibility.mockResolvedValue(
    collection([{ ...item, can_review: false, can_edit_review: true, review }]),
  )
  await wrapper.get('[data-review-form]').trigger('submit')
  await flushPromises()
  expect(productApi.getProduct).toHaveBeenCalledTimes(2)
  expect(wrapper.get('[data-rating-summary]').text()).toContain('4.67 / 5')
  expect(wrapper.get('[data-rating-summary]').text()).toContain('3 đánh giá')
})
it('logout discards a pending eligibility response', async () => {
  let resolve
  reviewApi.getReviewEligibility.mockImplementation(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  const { wrapper, auth } = await setup(ProductReviews, { props: { product } })
  auth.user = null
  await flushPromises()
  resolve(collection([item]))
  await flushPromises()
  expect(wrapper.find('[data-review-purchase]').exists()).toBe(false)
  expect(wrapper.find('[data-review-form]').exists()).toBe(false)
})
it('comments are plain text rather than executable markup', async () => {
  const comment = '<img src=x onerror="alert(1)"><script>alert(1)</script>'
  reviewApi.listProductReviews.mockResolvedValue(collection([{ ...review, comment }]))
  const { wrapper } = await setup(ProductReviews, { props: { product }, guest: true })
  expect(wrapper.text()).toContain(comment)
  expect(wrapper.find('img').exists()).toBe(false)
  expect(wrapper.find('script').exists()).toBe(false)
})
it('Review API uses CSRF and sends only the requested rating/comment payload', async () => {
  vi.restoreAllMocks()
  const get = vi.spyOn(axios, 'get').mockResolvedValue({ data: collection([review]) })
  const request = vi.spyOn(axios, 'request').mockResolvedValue({ data: { data: review } })
  await reviewApi.createReview('11', { rating: 5, comment: null })
  expect(get).toHaveBeenCalledWith('/sanctum/csrf-cookie')
  expect(request).toHaveBeenCalledWith({
    method: 'post',
    url: '/api/v1/order-items/11/review',
    data: { rating: 5, comment: null },
  })
  await reviewApi.updateReview('21', { rating: 3 })
  expect(request).toHaveBeenLastCalledWith({
    method: 'patch',
    url: '/api/v1/reviews/21',
    data: { rating: 3 },
  })
  await reviewApi.listProductReviews('a/b', { page: 2 })
  expect(get).toHaveBeenLastCalledWith('/api/v1/products/a%2Fb/reviews', { params: { page: 2 } })
})
