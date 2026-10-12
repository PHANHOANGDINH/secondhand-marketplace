import { mount } from '@vue/test-utils'
import StatusBadge from '../components/ui/StatusBadge.vue'
import DomainField from '../components/DomainField.vue'
import ProductImagesEditor from '../components/ProductImagesEditor.vue'
import VideoReviewCard from '../components/home/VideoReviewCard.vue'
import { getStatusLabel, getRoleLabel, getSettlementLabel, getPaymentMethodLabel } from '../utils/labels'
import { localizeValidationErrors } from '../utils/validationLabels'
import { domainError } from '../api/domain.api'
import { conditionLabel, formatPrice } from '../composables/productPresentation'

it('distinguishes pending and failure labels by business context', () => {
  expect(getStatusLabel('PENDING')).toBe('Chờ duyệt')
  expect(getStatusLabel('PENDING', 'order')).toBe('Chờ xử lý')
  expect(getStatusLabel('PENDING', 'payment')).toBe('Chờ thanh toán')
  expect(getStatusLabel('FAILED', 'payment')).toBe('Thanh toán thất bại')
  expect(getStatusLabel('FAILED', 'video')).toBe('Xử lý thất bại')
})
it('never displays unknown enum or inherited object keys in badges and helpers', () => {
  for (const status of ['SOME_NEW_STATUS', 'constructor', 'toString', null, undefined]) {
    const wrapper = mount(StatusBadge, { props: { status, label: 'SOME_NEW_STATUS' } })
    expect(wrapper.text()).toBe('Không xác định')
    wrapper.unmount()
    expect(getRoleLabel(status)).toBe('Không xác định')
    expect(getPaymentMethodLabel(status)).toBe('Không xác định')
  }
  expect(conditionLabel('UNKNOWN_CONDITION')).toBe('Không xác định')
  const card = mount(VideoReviewCard, { props: { video: {
    id: '1', product: { name: 'Máy ảnh', price: '100000.00' },
    auction: { status: 'SOME_NEW_STATUS' },
  } } })
  expect(card.text()).toContain('Không xác định')
  expect(card.text()).not.toContain('SOME_NEW_STATUS')
  card.unmount()
})
it('uses actual settlement states without inventing aliases from the request examples', () => {
  expect(getSettlementLabel(null)).toBe('Không phát sinh')
  expect(getSettlementLabel('NO_WINNER')).toBe('Không có người thắng')
  expect(getSettlementLabel('AWAITING_CHECKOUT')).toBe('Chờ người thắng hoàn tất đơn')
  expect(getSettlementLabel('ORDER_CREATED')).toBe('Đã tạo đơn hàng')
  expect(getSettlementLabel('CHECKOUT_EXPIRED')).toBe('Hết hạn thanh toán')
  expect(getSettlementLabel('PENDING_CHECKOUT')).toBe('Không xác định')
})
it('translates labels while keeping raw status values emitted by form controls', async () => {
  const wrapper = mount(DomainField, { props: {
    name: 'status', label: 'Trạng thái', type: 'select', modelValue: 'PENDING',
    options: ['PENDING', 'ACTIVE'].map(value => ({ value, label: getStatusLabel(value) })),
  } })
  expect(wrapper.get('option[value=ACTIVE]').text()).toBe('Đang hoạt động')
  await wrapper.get('select').setValue('ACTIVE')
  expect(wrapper.emitted('update:modelValue')).toEqual([['ACTIVE']])
  wrapper.unmount()
})
it('localizes validation errors without changing field keys or exposing internals', () => {
  const result = localizeValidationErrors({
    email: ['The email field must be a valid email address.'],
    password: ['The password field must be at least 8 characters.'],
    password_confirmation: ['The password confirmation does not match.'],
    body: ['The body field must not be greater than 1000 characters.'],
    status: ['Trạng thái ACTIVE không hợp lệ.'],
    'images.0': ['SQLSTATE[HY000]: mật khẩu máy chủ'],
  })
  expect(Object.keys(result)).toEqual(['email', 'password', 'password_confirmation', 'body', 'status', 'images.0'])
  expect(result.email).toEqual(['Địa chỉ thư điện tử không hợp lệ.'])
  expect(result.password).toEqual(['Mật khẩu phải có ít nhất 8 ký tự.'])
  expect(result.password_confirmation).toEqual(['Mật khẩu xác nhận chưa khớp.'])
  expect(result.body).toEqual(['Nội dung không được vượt quá 1000 ký tự.'])
  expect(result.status).toEqual(['Trạng thái Đang hoạt động không hợp lệ.'])
  expect(result['images.0']).toEqual(['Ảnh không hợp lệ. Vui lòng kiểm tra lại.'])
})
it('does not expose raw conflict exceptions and formats Vietnamese money', () => {
  expect(domainError({ response: { status: 409, data: { message: 'SQLSTATE secret' } } })).toBe(
    'Dữ liệu đã thay đổi hoặc đang được sử dụng. Vui lòng tải lại.',
  )
  expect(formatPrice('5500000.00')).toBe('5.500.000 ₫')
  expect(getRoleLabel('ADMIN')).toBe('Quản trị viên')
  expect(getPaymentMethodLabel('COD')).toBe('Thanh toán khi nhận hàng')
})
it('shows a Vietnamese file chooser with keyboard-native button semantics and unchanged disabled rules', async () => {
  const wrapper = mount(ProductImagesEditor, { props: {
    product: { id: '1', images: [], can_edit: true }, inputId: 'localized-images',
  } })
  const input = wrapper.get('input[type=file]')
  const click = vi.spyOn(input.element, 'click').mockImplementation(() => {})
  const picker = wrapper.findAll('button').find(button => button.text() === 'Chọn ảnh')
  expect(picker.attributes('type')).toBe('button')
  expect(picker.attributes('aria-controls')).toBe('localized-images')
  expect(input.attributes('accept')).toBe('image/jpeg,image/png,image/webp')
  expect(input.element.multiple).toBe(true)
  expect(wrapper.text()).toContain('Chưa chọn ảnh')
  await picker.trigger('click')
  expect(click).toHaveBeenCalledOnce()
  await wrapper.setProps({ disabled: true })
  expect(picker.element.disabled).toBe(true)
  expect(input.element.disabled).toBe(true)
  click.mockRestore()
  wrapper.unmount()
})
