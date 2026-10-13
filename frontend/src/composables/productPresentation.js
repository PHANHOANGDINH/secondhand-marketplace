export const conditions = [
  { value: 'LIKE_NEW', label: 'Như mới' },
  { value: 'GOOD', label: 'Tốt' },
  { value: 'FAIR', label: 'Đã sử dụng nhiều' },
  { value: 'POOR', label: 'Cần sửa chữa' },
]
export const statuses = ['DRAFT', 'PENDING', 'ACTIVE', 'REJECTED', 'HIDDEN', 'SOLD']
export const conditionLabel = (value) =>
  conditions.find((item) => item.value === value)?.label || 'Không xác định'
export function formatPrice(value) {
  const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(String(value))
  if (!match) return '—'
  const integer = new Intl.NumberFormat('vi-VN').format(BigInt(match[1]))
  const fraction = (match[2] || '').padEnd(2, '0')
  return `${integer}${fraction !== '00' ? ',' + fraction : ''} ₫`
}
export const auctionLockMessage =
  'Sản phẩm đang được giữ cho đấu giá nên tạm thời không thể chỉnh sửa.'
export const auctionLockRoute = (product) =>
  product?.auction_locked && /^[1-9][0-9]*$/.test(String(product.auction?.id ?? ''))
    ? `/seller/auctions/${product.auction.id}`
    : null
export function sellerActions(product) {
  // The API rejects every Seller change while an auction holds the product.
  if (!product.can_edit || product.archived || product.auction_locked) return []
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
// "3 giờ trước" from an ISO timestamp; empty when the value is missing or invalid.
export function timeAgo(value, now = Date.now()) {
  const time = value ? Date.parse(value) : NaN
  if (Number.isNaN(time)) return ''
  const minutes = Math.floor(Math.max(0, now - time) / 60000)
  if (minutes < 1) return 'Vừa đăng'
  if (minutes < 60) return `${minutes} phút trước`
  if (minutes < 1440) return `${Math.floor(minutes / 60)} giờ trước`
  const days = Math.floor(minutes / 1440)
  if (days < 30) return `${days} ngày trước`
  if (days < 365) return `${Math.floor(days / 30)} tháng trước`
  return `${Math.floor(days / 365)} năm trước`
}
