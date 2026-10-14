import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import ProductCard from '../components/ProductCard.vue'
import { auctionNote, compactMoney, timeUntil, voucherNote } from '../composables/productPresentation'

const NOW = Date.parse('2026-10-15T08:00:00Z')
const product = (extra = {}) => ({
  id: '5',
  name: 'Máy ảnh Fujifilm',
  slug: 'may-anh-fujifilm',
  price: '750000.00',
  compare_at_price: null,
  discount_percent: null,
  condition: 'GOOD',
  is_wishlisted: false,
  shop: { id: '2', name: 'Tiệm Cũ', slug: 'tiem-cu' },
  ...extra,
})
function card(value) {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:any(.*)', component: { template: '<div />' } }] })
  return mount(ProductCard, { props: { product: value }, global: { plugins: [createPinia(), router] } })
}
beforeEach(() => {
  vi.useFakeTimers()
  vi.setSystemTime(NOW)
})
afterEach(() => vi.useRealTimers())

it('formats short amounts and time left for the card lines', () => {
  expect([compactMoney('50000.00'), compactMoney('1500000'), compactMoney('1000000'), compactMoney('900')]).toEqual(['50K', '1,5tr', '1tr', '900'])
  expect(timeUntil('2026-10-17T09:00:00Z', NOW)).toBe('2 ngày')
  expect(timeUntil('2026-10-15T11:30:00Z', NOW)).toBe('3 giờ')
  expect(timeUntil('2026-10-15T08:14:10Z', NOW)).toBe('15 phút')
  expect(timeUntil('2026-10-15T07:00:00Z', NOW)).toBe('')
  expect(auctionNote({ status: 'LIVE', ends_at: '2026-10-17T09:00:00Z' }, NOW)).toBe('Đang đấu giá · còn 2 ngày')
  expect(auctionNote({ status: 'SCHEDULED', starts_at: '2026-10-15T11:30:00Z' }, NOW)).toBe('Sắp đấu giá · mở sau 3 giờ')
  expect(auctionNote({ status: 'ENDED' }, NOW)).toBe('Đấu giá đã kết thúc · chờ người thắng thanh toán')
  expect(voucherNote({ type: 'FIXED', value: '50000.00', min_order: '1000000.00' })).toBe('Shop giảm 50K · đơn từ 1tr')
  expect(voucherNote({ type: 'PERCENT', value: '10.00', min_order: '0.00' })).toBe('Shop giảm 10%')
  expect(voucherNote(null)).toBe('')
})

it('a discounted card shows the price before the discount struck through and the percent', () => {
  const wrapper = card(product({ compare_at_price: '1000000.00', discount_percent: 25 }))
  expect(wrapper.get('.product-card-price').text()).toContain('750.000')
  expect(wrapper.get('.product-card-compare').element.tagName).toBe('S')
  expect(wrapper.get('.product-card-compare').text()).toContain('1.000.000')
  expect(wrapper.get('.product-card-off').text()).toBe('-25%')
  expect(wrapper.find('.product-card-tags').exists()).toBe(false)
})

it('an auction and a shop voucher each get a line; an auction card shows the bid price, not a discount', () => {
  const wrapper = card(product({
    compare_at_price: '1000000.00',
    discount_percent: 25,
    auction: { id: '9', status: 'LIVE', current_price: '820000.00', ends_at: '2026-10-17T09:00:00Z' },
    shop: { id: '2', name: 'Tiệm Cũ', slug: 'tiem-cu', voucher: { type: 'FIXED', value: '50000.00', min_order: '1000000.00', max_discount: null } },
  }))
  expect(wrapper.get('.product-card-price').text()).toContain('820.000')
  expect(wrapper.find('.product-card-compare').exists()).toBe(false)
  expect(wrapper.findAll('.product-card-tag').map((tag) => tag.text())).toEqual(['Đang đấu giá · còn 2 ngày', 'Shop giảm 50K · đơn từ 1tr'])
  expect(wrapper.text()).not.toMatch(/GIAM|code/i)
})
