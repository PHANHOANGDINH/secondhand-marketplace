import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import Inventory from '../views/SellerInventoryView.vue'
import ActionCenter from '../components/seller/SellerActionCenter.vue'
import InventoryBadge from '../components/seller/InventoryBadge.vue'
import * as inventory from '../api/inventory.api'
import * as products from '../api/product.api'
vi.mock('../api/inventory.api', () => ({ listInventory: vi.fn(), getActionCenter: vi.fn() }))
vi.mock('../api/product.api', () => ({ updateProduct: vi.fn() }))
const item = (extra = {}) => ({
  id: '1',
  name: 'Bàn phím cơ',
  status: 'ACTIVE',
  stock_quantity: 3,
  inventory_state: 'LOW_STOCK',
  stock_editable: true,
  updated_at: '2026-10-10T01:00:00Z',
  ...extra,
})
const collection = (data = [item()]) => ({
  data,
  low_stock_threshold: 5,
  meta: { current_page: 1, last_page: 2, total: 21 },
})
const wrappers = []
async function setup(component = Inventory, path = '/seller/inventory') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }],
  })
  await router.push(path)
  await router.isReady()
  const wrapper = mount(component, { global: { plugins: [router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router }
}
beforeEach(() => {
  vi.resetAllMocks()
  inventory.listInventory.mockResolvedValue(collection())
  inventory.getActionCenter.mockResolvedValue({
    low_stock_products: 2,
    out_of_stock_products: 1,
    pending_products: 3,
    rejected_products: 1,
    orders_requiring_action: 4,
    live_auctions: 2,
    auctions_ending_soon: 1,
    low_stock_threshold: 5,
    ending_soon_minutes: 60,
  })
  products.updateProduct.mockResolvedValue(item({ status: 'PENDING' }))
})
afterEach(() => wrappers.splice(0).forEach((w) => w.unmount()))
it('renders Vietnamese current action counts and exact filtered destinations', async () => {
  const { wrapper } = await setup(ActionCenter)
  expect(wrapper.text()).toContain('Cần xử lý')
  expect(wrapper.text()).toContain('Đơn cần xử lý')
  expect(wrapper.findAll('a')).toHaveLength(7)
  expect(wrapper.find('a[href="/seller/orders?action_required=1"]').exists()).toBe(true)
  expect(wrapper.find('a[href="/seller/auctions?ending_soon=1"]').exists()).toBe(true)
})
it('action center shows errors and retries without fake metrics', async () => {
  inventory.getActionCenter.mockRejectedValueOnce({ response: { status: 500 } })
  const { wrapper } = await setup(ActionCenter)
  expect(wrapper.findAll('a')).toHaveLength(0)
  await wrapper.find('button').trigger('click')
  await flushPromises()
  expect(wrapper.findAll('a')).toHaveLength(7)
})
it('initializes filter search sort and pagination from URL', async () => {
  await setup(Inventory, '/seller/inventory?filter=low_stock&sort=stock_asc&search=phím&page=2')
  expect(inventory.listInventory).toHaveBeenCalledWith({
    filter: 'low_stock',
    sort: 'stock_asc',
    search: 'phím',
    page: 2,
  })
})
it('updates URL on filter submit and restores state on navigation', async () => {
  const { wrapper, router } = await setup()
  await wrapper.find('[aria-label="Lọc tồn kho"]').setValue('pending')
  await wrapper.find('[aria-label="Tìm tên sản phẩm"]').setValue('máy')
  await wrapper.find('form').trigger('submit')
  await flushPromises()
  expect(router.currentRoute.value.query.filter).toBe('pending')
  expect(inventory.listInventory).toHaveBeenLastCalledWith({
    filter: 'pending',
    sort: 'updated',
    search: 'máy',
    page: 1,
  })
  await router.push('/seller/inventory?filter=hidden')
  await flushPromises()
  expect(wrapper.find('select').element.value).toBe('hidden')
})
it('paginates while preserving current filters', async () => {
  const { wrapper, router } = await setup(Inventory, '/seller/inventory?filter=low_stock')
  const next = wrapper.findAll('button').find((b) => b.text().includes('Sau'))
  expect(next).toBeTruthy()
  await next.trigger('click')
  await flushPromises()
  expect(router.currentRoute.value.query.page).toBe('2')
  expect(router.currentRoute.value.query.filter).toBe('low_stock')
})
it('saves stock with the current version and shows moderation guidance', async () => {
  const { wrapper } = await setup()
  await wrapper.find('[aria-label^="Cập nhật tồn kho:"]').trigger('click')
  await wrapper.find('#inventory-quantity').setValue('7')
  await wrapper.find('form[aria-label="Cập nhật tồn kho"]').trigger('submit')
  await flushPromises()
  expect(products.updateProduct).toHaveBeenCalledWith('1', {
    stock_quantity: 7,
    expected_updated_at: '2026-10-10T01:00:00Z',
  })
  expect(wrapper.text()).toContain('chờ duyệt theo quy định hiện có')
  expect(wrapper.find('#inventory-quantity').exists()).toBe(false)
})
it.each(['-1', '1.5', '4294967296'])('prevents invalid stock %s', async (value) => {
  const { wrapper } = await setup()
  await wrapper.find('[aria-label^="Cập nhật tồn kho:"]').trigger('click')
  await wrapper.find('#inventory-quantity').setValue(value)
  expect(
    wrapper.find('form[aria-label="Cập nhật tồn kho"] button').attributes('disabled'),
  ).toBeDefined()
  await wrapper.find('form[aria-label="Cập nhật tồn kho"]').trigger('submit')
  expect(products.updateProduct).not.toHaveBeenCalled()
})
it('prevents duplicate submission while saving', async () => {
  let resolve
  products.updateProduct.mockImplementation(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  const { wrapper } = await setup()
  await wrapper.find('[aria-label^="Cập nhật tồn kho:"]').trigger('click')
  await wrapper.find('form[aria-label="Cập nhật tồn kho"]').trigger('submit')
  await wrapper.find('form[aria-label="Cập nhật tồn kho"]').trigger('submit')
  expect(products.updateProduct).toHaveBeenCalledTimes(1)
  expect(wrapper.text()).toContain('Đang lưu')
  resolve(item())
  await flushPromises()
})
it('displays safe auction reservation reason from backend on conflict', async () => {
  products.updateProduct.mockRejectedValue({
    response: { status: 409, data: { message: 'Sản phẩm đang được giữ cho đấu giá.' } },
  })
  const { wrapper } = await setup()
  await wrapper.find('[aria-label^="Cập nhật tồn kho:"]').trigger('click')
  await wrapper.find('form[aria-label="Cập nhật tồn kho"]').trigger('submit')
  await flushPromises()
  expect(wrapper.find('[role="alert"]').text()).toContain('được giữ cho đấu giá')
})
it('does not expose quick editing for locked inventory', async () => {
  inventory.listInventory.mockResolvedValue(
    collection([
      item({ stock_editable: false, stock_edit_reason: 'Sản phẩm đang được giữ cho đấu giá.' }),
    ]),
  )
  const { wrapper } = await setup()
  expect(wrapper.find('[aria-label^="Cập nhật tồn kho:"]').exists()).toBe(false)
  expect(wrapper.text()).toContain('được giữ cho đấu giá')
})
it('keeps error and empty states mutually exclusive and allows retry', async () => {
  inventory.listInventory.mockRejectedValueOnce({ response: { status: 404 } })
  const { wrapper } = await setup()
  expect(wrapper.text()).not.toContain('Không có sản phẩm phù hợp')
  await wrapper
    .findAll('button')
    .find((b) => b.text() === 'Tải lại tồn kho')
    .trigger('click')
  await flushPromises()
  expect(wrapper.text()).toContain('Bàn phím cơ')
})
it('renders readable empty state', async () => {
  inventory.listInventory.mockResolvedValue(collection([]))
  const { wrapper } = await setup()
  expect(wrapper.text()).toContain('Không có sản phẩm phù hợp')
})
it('suppresses stale list responses after URL navigation', async () => {
  let resolve
  inventory.listInventory.mockImplementationOnce(
    () =>
      new Promise((r) => {
        resolve = r
      }),
  )
  const { wrapper, router } = await setup()
  await router.push('/seller/inventory?filter=rejected')
  await flushPromises()
  resolve(collection([item({ name: 'Kết quả cũ' })]))
  await flushPromises()
  expect(wrapper.text()).not.toContain('Kết quả cũ')
})
it.each([
  ['NORMAL', 12, 'Còn 12'],
  ['LOW_STOCK', 3, 'Sắp hết: 3'],
  ['OUT_OF_STOCK', 0, 'Hết hàng'],
])('uses inventory semantics %s', async (state, stock, label) => {
  const wrapper = mount(InventoryBadge, {
    props: { product: item({ inventory_state: state, stock_quantity: stock }) },
  })
  wrappers.push(wrapper)
  expect(wrapper.text()).toBe(label)
})
it('has accessible edit names, labels and responsive cell headings', async () => {
  const { wrapper } = await setup()
  expect(wrapper.findAll('td[data-label]')).toHaveLength(5)
  await wrapper.find('[aria-label^="Cập nhật tồn kho:"]').trigger('click')
  expect(wrapper.find('label[for="inventory-quantity"]').text()).toContain('Số lượng mới')
  expect(wrapper.find('#inventory-quantity').attributes('aria-describedby')).toContain(
    'inventory-help',
  )
})
