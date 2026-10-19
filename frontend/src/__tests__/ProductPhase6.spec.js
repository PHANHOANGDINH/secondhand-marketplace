import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { createAuthGuard } from '../router/authGuard'
import * as productApi from '../api/product.api'
import * as reviewApi from '../api/review.api'
import * as optionsApi from '../api/catalogOptions'
import * as shopApi from '../api/shop.api'
import api from '../api/axios'
import { formatPrice, sellerActions } from '../composables/productPresentation'
import ProductsView from '../views/ProductsView.vue'
import ProductView from '../views/ProductView.vue'
import MyAdsView from '../views/MyAdsView.vue'
import SellerProductEditView from '../views/SellerProductEditView.vue'
import AdminProductsView from '../views/AdminProductsView.vue'
import ProductImagesEditor from '../components/ProductImagesEditor.vue'
import ShopProducts from '../components/ShopProducts.vue'
const product = {
  id: '10',
  name: 'Used phone',
  slug: 'used-phone',
  description: 'Honest description',
  price: '999999.99',
  stock_quantity: 3,
  condition: 'GOOD',
  status: 'DRAFT',
  can_edit: true,
  can_moderate: false,
  archived: false,
  shop_status: 'ACTIVE',
  updated_at: '2026-09-27T01:00:00.000000Z',
  created_at: '2026-09-27T01:00:00Z',
  shop: { id: '5', name: 'Example Shop', slug: 'example-shop' },
  category: { id: '3', name: 'Phones', slug: 'phones' },
  images: [
    { id: '20', url: '/storage/products/10/first.png', is_primary: true, sort_order: 0 },
    { id: '21', url: '/storage/products/10/second.png', is_primary: false, sort_order: 1 },
  ],
  primary_image: { url: '/storage/products/10/first.png' },
}
const collection = (data, extra = {}) => ({
  data,
  meta: { current_page: 1, last_page: 1, total: data.length, ...extra },
})
const wrappers = []
async function setup(component, path = '/products', props = {}, role = 'USER') {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = { id: '1', name: 'Test User', role, status: 'ACTIVE' }
  auth.initialized = true
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      '/',
      '/products',
      '/products/:slug',
      '/shops/:slug',
      '/seller/products',
      '/seller/products/create',
      '/seller/products/:id/edit',
      '/admin/products',
    ].map((path) => ({ path, component: { template: '<div />' } })),
  })
  await router.push(path)
  const wrapper = mount(component, { props, global: { plugins: [pinia, router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router, auth }
}
function button(wrapper, text) {
  return wrapper.findAll('button').find((node) => node.text() === text)
}
async function choose(wrapper, files) {
  const input = wrapper.get('input[type=file]')
  Object.defineProperty(input.element, 'files', { configurable: true, value: files })
  await input.trigger('change')
}
beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(reviewApi, 'listProductReviews').mockResolvedValue(collection([]))
  vi.spyOn(reviewApi, 'getReviewEligibility').mockResolvedValue(collection([]))
  vi.spyOn(optionsApi, 'categoryOptions').mockResolvedValue([{ value: '3', label: 'Phones' }])
  vi.spyOn(optionsApi, 'shopOptions').mockResolvedValue([{ value: '5', label: 'Example Shop' }])
  vi.spyOn(shopApi, 'getSellerShop').mockResolvedValue({ id: '5', status: 'ACTIVE' })
  URL.createObjectURL = vi.fn(() => 'blob:preview')
  URL.revokeObjectURL = vi.fn()
})
afterEach(() => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount()
})
it('restores catalog filters from URL and renders product cards', async () => {
  vi.spyOn(productApi, 'listProducts').mockResolvedValue(
    collection([product], { current_page: 2, last_page: 3 }),
  )
  const { wrapper } = await setup(
    ProductsView,
    '/products?search=phone&category=3&condition=GOOD&min_price=0.10&max_price=999999.99&shop=5&sort=price_asc&page=2',
  )
  expect(productApi.listProducts).toHaveBeenCalledWith({
    search: 'phone',
    category: '3',
    condition: 'GOOD',
    min_price: '0.10',
    max_price: '999999.99',
    shop: '5',
    sort: 'price_asc',
    page: '2',
  })
  expect(wrapper.get('input[name=search]').element.value).toBe('phone')
  expect(wrapper.get('[data-product="10"]').text()).toContain('Used phone')
  expect(wrapper.text()).toContain('999.999,99 ₫')
})
it('catalog submission changes query and sends filters only on submit', async () => {
  vi.spyOn(productApi, 'listProducts').mockResolvedValue(collection([product]))
  const { wrapper, router } = await setup(ProductsView)
  await wrapper.get('input[name=search]').setValue('updated')
  await wrapper.get('select[name=category]').setValue('3')
  await wrapper.get('select[name=condition]').setValue('GOOD')
  // Typed the Vietnamese way; the query keeps plain numbers.
  await wrapper.get('input[name=min_price]').setValue('100.000')
  await wrapper.get('input[name=max_price]').setValue('500.000')
  expect(wrapper.get('input[name=min_price]').element.value).toBe('100.000')
  await wrapper.get('select[name=sort]').setValue('price_desc')
  expect(productApi.listProducts).toHaveBeenCalledTimes(1)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(router.currentRoute.value.query).toMatchObject({
    search: 'updated',
    category: '3',
    condition: 'GOOD',
    min_price: '100000',
    max_price: '500000',
    sort: 'price_desc',
  })
  expect(productApi.listProducts).toHaveBeenLastCalledWith(
    expect.objectContaining({ search: 'updated', sort: 'price_desc', page: '1' }),
  )
})
it('catalog pagination preserves filters in URL and requests the next page', async () => {
  vi.spyOn(productApi, 'listProducts').mockResolvedValue(collection([product], { last_page: 2 }))
  const { wrapper, router } = await setup(ProductsView, '/products?condition=GOOD')
  await button(wrapper, 'Sau').trigger('click')
  await flushPromises()
  expect(router.currentRoute.value.query).toMatchObject({ condition: 'GOOD', page: '2' })
  expect(productApi.listProducts).toHaveBeenLastCalledWith(
    expect.objectContaining({ condition: 'GOOD', page: '2' }),
  )
})
it('catalog handles empty, validation and connection error states', async () => {
  vi.spyOn(productApi, 'listProducts').mockResolvedValue(collection([]))
  const { wrapper } = await setup(ProductsView)
  expect(wrapper.text()).toContain('Không tìm thấy sản phẩm')
  productApi.listProducts.mockRejectedValue({
    response: { status: 422, data: { errors: { max_price: ['Invalid price range'] } } },
  })
  await button(wrapper, 'Tải lại').trigger('click')
  await flushPromises()
  expect(wrapper.get('#max_price-errors').text()).toContain('Giá cao nhất không hợp lệ')
  productApi.listProducts.mockRejectedValue(new Error('offline'))
  await button(wrapper, 'Tải lại').trigger('click')
  await flushPromises()
  expect(wrapper.get('[role=alert]').text()).toContain('kết nối')
})
it('product detail retains gallery, category and shop alongside cart entry', async () => {
  vi.spyOn(productApi, 'getProduct').mockResolvedValue(product)
  const { wrapper } = await setup(ProductView, '/products/used-phone')
  expect(productApi.getProduct).toHaveBeenCalledWith('used-phone')
  expect(wrapper.text()).toContain('Honest description')
  expect(wrapper.text()).toContain('Phones')
  expect(wrapper.find('a[href="/shops/example-shop"]').exists()).toBe(true)
  expect(wrapper.findAll('img')).toHaveLength(3)
  await wrapper.get('[aria-label="Xem ảnh 2"]').trigger('click')
  expect(wrapper.findAll('img')[0].attributes('src')).toBe(product.images[1].url)
  expect(wrapper.text()).toContain('Thêm vào giỏ')
})
it('detail handles 404 and uses a local placeholder when images are missing', async () => {
  vi.spyOn(productApi, 'getProduct').mockRejectedValue({ response: { status: 404 } })
  const { wrapper } = await setup(ProductView, '/products/missing')
  expect(wrapper.get('[role=alert]').text()).toContain('Không tìm thấy')
  productApi.getProduct.mockResolvedValue({ ...product, images: [] })
  await button(wrapper, 'Tải lại').trigger('click')
  await flushPromises()
  expect(wrapper.get('img').attributes('src')).toBe('/product-placeholder.svg')
})
it('shop catalog uses the canonical product query and current shop ID', async () => {
  vi.spyOn(productApi, 'listProducts').mockResolvedValue(collection([product]))
  const { wrapper } = await setup(ShopProducts, '/shops/example-shop', { shopId: '5' })
  expect(productApi.listProducts).toHaveBeenCalledWith({ shop: '5', page: 1 })
  expect(wrapper.text()).toContain('Used phone')
})
it('seller list loads and renders valid submit/hide actions for each status', async () => {
  vi.spyOn(productApi, 'listSellerProducts').mockResolvedValue(
    collection([
      product,
      { ...product, id: '11', status: 'ACTIVE' },
      { ...product, id: '12', status: 'PENDING' },
    ]),
  )
  vi.spyOn(productApi, 'productAction').mockResolvedValue({ ...product, status: 'PENDING' })
  const { wrapper } = await setup(MyAdsView, '/seller/products')
  expect(wrapper.get('[data-product="10"] [data-action=submit]').exists()).toBe(true)
  expect(wrapper.get('[data-product="11"] [data-action=hide]').exists()).toBe(true)
  expect(wrapper.find('[data-product="12"] [data-action=submit]').exists()).toBe(false)
  await wrapper.get('[data-product="10"] [data-action=submit]').trigger('click')
  await flushPromises()
  expect(productApi.productAction).toHaveBeenCalledWith('10', 'submit')
  await wrapper.get('[data-product="11"] [data-action=hide]').trigger('click')
  await flushPromises()
  expect(productApi.productAction).toHaveBeenCalledWith('11', 'hide')
})
it('seller creates metadata without privileged fields and redirects to image editing', async () => {
  vi.spyOn(productApi, 'createProduct').mockResolvedValue({ ...product, images: [] })
  vi.spyOn(productApi, 'getSellerProduct').mockResolvedValue(product)
  const { wrapper, router } = await setup(SellerProductEditView, '/seller/products/create')
  expect(wrapper.find('[name=status]').exists()).toBe(false)
  expect(wrapper.find('[name=shop_id]').exists()).toBe(false)
  expect(wrapper.find('[name=slug]').exists()).toBe(false)
  await wrapper.get('input[name=name]').setValue('Used phone')
  await wrapper.get('textarea[name=description]').setValue('Honest description')
  await wrapper.get('select[name=category_id]').setValue('3')
  await wrapper.get('input[name=price]').setValue('999999.99')
  await wrapper.get('input[name=stock_quantity]').setValue('3')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(productApi.createProduct).toHaveBeenCalledWith({
    name: 'Used phone',
    description: 'Honest description',
    category_id: '3',
    price: '999999.99',
    stock_quantity: '3',
    condition: 'GOOD',
  })
  expect(router.currentRoute.value.path).toBe('/seller/products/10/edit')
  expect(wrapper.find('input[type=file]').exists()).toBe(true)
})
it('seller editing includes version for stock and refreshes returned status', async () => {
  vi.spyOn(productApi, 'getSellerProduct').mockResolvedValue({ ...product, status: 'ACTIVE' })
  vi.spyOn(productApi, 'updateProduct').mockResolvedValue({
    ...product,
    status: 'PENDING',
    name: 'Updated',
  })
  const { wrapper } = await setup(SellerProductEditView, '/seller/products/10/edit')
  await wrapper.get('input[name=name]').setValue('Updated')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(productApi.updateProduct).toHaveBeenCalledWith(
    '10',
    expect.objectContaining({
      name: 'Updated',
      expected_updated_at: product.updated_at,
      price: '999999.99',
    }),
  )
  expect(wrapper.text()).toContain('Chờ xem xét')
})
it('seller form displays backend validation errors and retains inputs', async () => {
  vi.spyOn(productApi, 'getSellerProduct').mockResolvedValue(product)
  vi.spyOn(productApi, 'updateProduct').mockRejectedValue({
    response: { status: 422, data: { errors: { price: ['Use a decimal string'] } } },
  })
  const { wrapper } = await setup(SellerProductEditView, '/seller/products/10/edit')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.get('#price-errors').text()).toContain('Giá không hợp lệ')
  expect(wrapper.get('input[name=name]').element.value).toBe(product.name)
})
it('seller restrictions remove actions and disable metadata and image controls', async () => {
  vi.spyOn(productApi, 'getSellerProduct').mockResolvedValue({
    ...product,
    can_edit: false,
    shop_status: 'SUSPENDED',
  })
  const { wrapper } = await setup(SellerProductEditView, '/seller/products/10/edit')
  expect(wrapper.get('fieldset').attributes('disabled')).toBeDefined()
  expect(wrapper.find('[data-action]').exists()).toBe(false)
  expect(wrapper.get('input[type=file]').attributes('disabled')).toBeDefined()
  expect(sellerActions({ ...product, shop_status: 'PENDING' })).not.toContain('submit')
})
it('image editor previews and uploads selected files then releases object URLs', async () => {
  vi.spyOn(productApi, 'uploadProductImages').mockResolvedValue(product.images)
  const { wrapper } = await setup(ProductImagesEditor, '/seller/products/10/edit', { product })
  const file = new File(['png'], 'image.png', { type: 'image/png' })
  await choose(wrapper, [file])
  expect(URL.createObjectURL).toHaveBeenCalledWith(file)
  await button(wrapper, 'Tải lên 1 ảnh').trigger('click')
  await flushPromises()
  expect(productApi.uploadProductImages).toHaveBeenCalledWith('10', [file])
  expect(wrapper.emitted('changed')).toHaveLength(1)
  expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:preview')
})
it('image editor blocks invalid files and excessive count before request', async () => {
  vi.spyOn(productApi, 'uploadProductImages').mockResolvedValue([])
  const { wrapper } = await setup(ProductImagesEditor, '/seller/products/10/edit', { product })
  await choose(wrapper, [new File(['svg'], 'bad.svg', { type: 'image/svg+xml' })])
  expect(wrapper.get('[role=alert]').text()).toContain('JPEG/PNG/WebP')
  const large = new File(['png'], 'large.png', { type: 'image/png' })
  Object.defineProperty(large, 'size', { value: 5 * 1024 * 1024 + 1 })
  await choose(wrapper, [large])
  expect(wrapper.get('[role=alert]').text()).toContain('5 MiB')
  await choose(
    wrapper,
    Array.from({ length: 7 }, () => new File(['png'], 'image.png', { type: 'image/png' })),
  )
  expect(wrapper.get('[role=alert]').text()).toContain('8 ảnh')
  expect(productApi.uploadProductImages).not.toHaveBeenCalled()
})
it('image editor takes several photos at once, adds later picks and drops, and removes one before upload', async () => {
  vi.spyOn(productApi, 'uploadProductImages').mockResolvedValue(product.images)
  const empty = { ...product, images: [] }
  const { wrapper } = await setup(ProductImagesEditor, '/seller/products/10/edit', { product: empty })
  const png = (name) => new File(['png'], name, { type: 'image/png' })
  const [a, b, c, d] = ['a.png', 'b.png', 'c.png', 'd.png'].map(png)
  await choose(wrapper, [a, b])
  expect(wrapper.findAll('[data-staged]')).toHaveLength(2)
  // A second pick adds to the first instead of replacing it.
  await choose(wrapper, [c, new File(['svg'], 'bad.svg', { type: 'image/svg+xml' })])
  expect(wrapper.findAll('[data-staged]')).toHaveLength(3)
  expect(wrapper.get('[role=alert]').text()).toContain('bỏ qua 1 ảnh')
  // Drag and drop onto the zone.
  await wrapper.get('[data-upload-zone]').trigger('drop', { dataTransfer: { files: [d] } })
  expect(wrapper.findAll('[data-staged]')).toHaveLength(4)
  expect(wrapper.text()).toContain('Đã chọn 4 ảnh · còn chọn được 4 ảnh')
  await wrapper.findAll('[data-staged] button')[1].trigger('click')
  expect(wrapper.get('[data-upload]').text()).toBe('Tải lên 3 ảnh')
  await wrapper.get('[data-upload]').trigger('click')
  await flushPromises()
  expect(productApi.uploadProductImages).toHaveBeenCalledWith('10', [a, c, d])
})
it('image editor keeps only as many new photos as the 8-photo limit allows', async () => {
  const { wrapper } = await setup(ProductImagesEditor, '/seller/products/10/edit', { product })
  await choose(
    wrapper,
    Array.from({ length: 9 }, (_, i) => new File(['png'], `p${i}.png`, { type: 'image/png' })),
  )
  // Two photos already exist, so six of the nine are kept.
  expect(wrapper.findAll('[data-staged]')).toHaveLength(6)
  expect(wrapper.get('[role=alert]').text()).toContain('đã bỏ bớt 3 ảnh')
  expect(wrapper.findAll('button').find((node) => node.text().includes('Chọn')).attributes('disabled')).toBeDefined()
})
it('image editor sets primary, deletes and sends the complete reordered ID set', async () => {
  vi.spyOn(productApi, 'setPrimaryImage').mockResolvedValue(product.images[1])
  vi.spyOn(productApi, 'deleteProductImage').mockResolvedValue(undefined)
  vi.spyOn(productApi, 'reorderProductImages').mockResolvedValue(product.images)
  const { wrapper } = await setup(ProductImagesEditor, '/seller/products/10/edit', { product })
  await button(wrapper, 'Đặt ảnh chính').trigger('click')
  await flushPromises()
  expect(productApi.setPrimaryImage).toHaveBeenCalledWith('10', '21')
  await wrapper
    .get('[data-image="20"]')
    .findAll('button')
    .find((node) => node.text() === 'Xuống')
    .trigger('click')
  await flushPromises()
  expect(productApi.reorderProductImages).toHaveBeenCalledWith('10', ['21', '20'])
  await wrapper
    .get('[data-image="21"]')
    .findAll('button')
    .find((node) => node.text() === 'Xóa ảnh')
    .trigger('click')
  await flushPromises()
  expect(productApi.deleteProductImage).toHaveBeenCalledWith('10', '21')
})
it('admin lists and filters products then approves with expected status and reason', async () => {
  const pending = { ...product, status: 'PENDING', can_moderate: true, can_edit: false }
  vi.spyOn(productApi, 'listAdminProducts').mockResolvedValue(collection([pending]))
  vi.spyOn(productApi, 'getAdminProduct').mockResolvedValue(pending)
  vi.spyOn(productApi, 'moderateProduct').mockResolvedValue({ ...pending, status: 'ACTIVE' })
  const { wrapper } = await setup(AdminProductsView, '/admin/products', {}, 'ADMIN')
  await wrapper.get('select[name=status_filter]').setValue('PENDING')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(productApi.listAdminProducts).toHaveBeenLastCalledWith({ page: 1, status: 'PENDING' })
  await button(wrapper, 'Xem').trigger('click')
  await flushPromises()
  expect(wrapper.find('[data-target=REJECTED]').exists()).toBe(true)
  await wrapper.get('textarea[name=reason]').setValue('Verified condition')
  await wrapper.get('[data-target=ACTIVE]').trigger('click')
  await flushPromises()
  expect(productApi.moderateProduct).toHaveBeenCalledWith('10', {
    expected_status: 'PENDING',
    status: 'ACTIVE',
    reason: 'Verified condition',
  })
  // A live listing can only be taken down.
  expect(wrapper.find('[data-target=REJECTED]').exists()).toBe(true)
  expect(wrapper.find('[data-target=ACTIVE]').exists()).toBe(false)
})
it('admin rejection displays stale conflict without changing the displayed state', async () => {
  const pending = { ...product, status: 'PENDING', can_moderate: true }
  vi.spyOn(productApi, 'listAdminProducts').mockResolvedValue(collection([pending]))
  vi.spyOn(productApi, 'getAdminProduct').mockResolvedValue(pending)
  vi.spyOn(productApi, 'moderateProduct').mockRejectedValue({
    response: { status: 409, data: { message: 'Product status changed.' } },
  })
  const { wrapper } = await setup(AdminProductsView, '/admin/products', {}, 'ADMIN')
  await button(wrapper, 'Xem').trigger('click')
  await flushPromises()
  await wrapper.get('textarea[name=reason]').setValue('Wrong description')
  await wrapper.get('[data-target=REJECTED]').trigger('click')
  await flushPromises()
  expect(productApi.moderateProduct).toHaveBeenCalledWith('10', {
    expected_status: 'PENDING',
    status: 'REJECTED',
    reason: 'Wrong description',
  })
  expect(wrapper.get('[role=alert]').text()).toContain('Dữ liệu đã thay đổi')
  expect(wrapper.find('[data-target=ACTIVE]').exists()).toBe(true)
})
it('USER is redirected away from admin product pages', async () => {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.initialized = true
  auth.user = { role: 'USER' }
  expect(
    await createAuthGuard(auth)({ meta: { requiresAdmin: true, requiresAuth: true } }),
  ).toEqual({ name: 'forbidden' })
})
it('price display retains decimals and large integer values without monetary float arithmetic', () => {
  expect(formatPrice('0.10')).toBe('0,10 ₫')
  expect(formatPrice('9999999999999.99')).toBe('9.999.999.999.999,99 ₫')
  expect(formatPrice('1250000.00')).toBe('1.250.000 ₫')
})
it('upload module sends multipart FormData through shared CSRF Axios client', async () => {
  vi.spyOn(api, 'get').mockResolvedValue({})
  vi.spyOn(api, 'request').mockResolvedValue({ data: { data: product.images } })
  const file = new File(['png'], 'sample.png', { type: 'image/png' })
  await productApi.uploadProductImages('10', [file])
  expect(api.get).toHaveBeenCalledWith('/sanctum/csrf-cookie')
  const config = api.request.mock.calls[0][0]
  expect(config.method).toBe('post')
  expect(config.url).toBe('/api/v1/seller/products/10/images')
  expect(config.data).toBeInstanceOf(FormData)
  expect(config.data.get('images[]')).toBe(file)
  expect(config.headers).toBeUndefined()
})
