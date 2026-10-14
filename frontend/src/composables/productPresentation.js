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
    PENDING: [],
    ACTIVE: ['hide'],
    REJECTED: ['reopen', 'delete'],
    HIDDEN: ['submit', 'delete'],
    SOLD: ['hide'],
  }
  return (actions[product.status] || []).filter(
    (action) => !['submit', 'reopen'].includes(action) || product.shop_status === 'ACTIVE',
  )
}
// Icon for each seller action; publish and reopen also keep their words on screen.
export const actionIcons = { submit: 'upload', reopen: 'refresh', hide: 'eyeOff', delete: 'trash' }
export const labelledActions = ['submit', 'reopen']
export const actionLabels = {
  submit: 'Đăng bán',
  reopen: 'Yêu cầu mở lại',
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

/** Short money for small labels: 50000 → "50K", 1500000 → "1,5tr". */
export function compactMoney(value) {
  const amount = Number(value)
  if (!Number.isFinite(amount)) return ''
  const trim = (number) => String(Math.round(number * 10) / 10).replace('.', ',')
  if (amount >= 1000000) return `${trim(amount / 1000000)}tr`
  if (amount >= 1000) return `${trim(amount / 1000)}K`
  return String(amount)
}

/** "2 ngày", "3 giờ", "15 phút" until a moment; empty once it has passed. */
export function timeUntil(value, now = Date.now()) {
  const time = value ? Date.parse(value) : NaN
  if (Number.isNaN(time) || time <= now) return ''
  const minutes = Math.ceil((time - now) / 60000)
  if (minutes < 60) return `${minutes} phút`
  if (minutes < 1440) return `${Math.floor(minutes / 60)} giờ`
  return `${Math.floor(minutes / 1440)} ngày`
}

/** The card line for an auction holding the listing. */
export function auctionNote(auction, now = Date.now()) {
  if (!auction) return ''
  if (auction.status === 'SCHEDULED') {
    const left = timeUntil(auction.starts_at, now)
    return left ? `Sắp đấu giá · mở sau ${left}` : 'Sắp đấu giá'
  }
  if (auction.status === 'LIVE') {
    const left = timeUntil(auction.ends_at, now)
    return left ? `Đang đấu giá · còn ${left}` : 'Đang đấu giá'
  }
  return 'Đấu giá đã kết thúc · chờ người thắng thanh toán'
}

/** The card line for the shop's best running voucher; the code itself is never shown on cards. */
export function voucherNote(voucher) {
  if (!voucher) return ''
  const amount =
    voucher.type === 'PERCENT' ? `${Number(voucher.value)}%` : compactMoney(voucher.value)
  return Number(voucher.min_order) > 0
    ? `Shop giảm ${amount} · đơn từ ${compactMoney(voucher.min_order)}`
    : `Shop giảm ${amount}`
}
