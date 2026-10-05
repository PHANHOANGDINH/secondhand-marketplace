export const conditions = [
  { value: 'LIKE_NEW', label: 'Như mới' },
  { value: 'GOOD', label: 'Tốt' },
  { value: 'FAIR', label: 'Đã sử dụng nhiều' },
  { value: 'POOR', label: 'Cần sửa chữa' },
]
export const statuses = ['DRAFT', 'PENDING', 'ACTIVE', 'REJECTED', 'HIDDEN', 'SOLD']
export const conditionLabel = (value) =>
  conditions.find((item) => item.value === value)?.label || value
export function formatPrice(value) {
  const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(String(value))
  if (!match) return '—'
  const integer = new Intl.NumberFormat('vi-VN').format(BigInt(match[1]))
  const fraction = (match[2] || '').padEnd(2, '0')
  return `${integer}${fraction !== '00' ? ',' + fraction : ''} ₫`
}
export function sellerActions(product) {
  if (!product.can_edit || product.archived) return []
  const actions = {
    DRAFT: ['submit', 'delete'],
    PENDING: ['withdraw'],
    ACTIVE: ['hide'],
    REJECTED: ['revise', 'delete'],
    HIDDEN: ['submit', 'delete'],
    SOLD: ['hide'],
  }
  return (actions[product.status] || []).filter(
    (action) => action !== 'submit' || product.shop_status === 'ACTIVE',
  )
}
export const actionLabels = {
  submit: 'Gửi duyệt',
  withdraw: 'Rút khỏi hàng chờ',
  revise: 'Sửa lại bản nháp',
  hide: 'Ẩn sản phẩm',
  delete: 'Xóa sản phẩm',
}
