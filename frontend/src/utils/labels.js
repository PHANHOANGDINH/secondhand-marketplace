// Presentation only: API values, permissions and state transitions stay unchanged.
export const unknownLabel = 'Không xác định'
export const statusLabels = {
  ACTIVE: 'Đang hoạt động', INACTIVE: 'Ngừng hoạt động', PENDING: 'Chờ duyệt',
  SUSPENDED: 'Tạm đình chỉ', REJECTED: 'Bị từ chối', HIDDEN: 'Đã ẩn',
  DRAFT: 'Bản nháp', SOLD: 'Đã bán', PUBLISHED: 'Đã hiển thị',
  SCHEDULED: 'Sắp diễn ra', LIVE: 'Đang đấu giá', ENDED: 'Đã kết thúc',
  CANCELLED: 'Đã hủy', PROCESSING: 'Đang xử lý', READY: 'Sẵn sàng',
  FAILED: 'Xử lý thất bại', PAID: 'Đã thanh toán', REFUNDED: 'Đã hoàn tiền',
  CONFIRMED: 'Đã xác nhận', SHIPPING: 'Đang giao hàng', COMPLETED: 'Hoàn thành',
  IN_PROGRESS: 'Đang xử lý', PARTIALLY_COMPLETED: 'Hoàn thành một phần',
}
export const orderStatusLabels = {
  PENDING: 'Chờ xử lý', CONFIRMED: 'Đã xác nhận', PROCESSING: 'Đang xử lý',
  SHIPPING: 'Đang giao hàng', COMPLETED: 'Hoàn thành', CANCELLED: 'Đã hủy',
  IN_PROGRESS: 'Đang xử lý', PARTIALLY_COMPLETED: 'Hoàn thành một phần',
}
export const paymentStatusLabels = {
  PENDING: 'Chờ thanh toán', PAID: 'Đã thanh toán', FAILED: 'Thanh toán thất bại',
  CANCELLED: 'Đã hủy', REFUNDED: 'Đã hoàn tiền',
}
export const auctionStatusLabels = {
  SCHEDULED: 'Sắp diễn ra', LIVE: 'Đang đấu giá', ENDED: 'Đã kết thúc', CANCELLED: 'Đã hủy',
}
export const settlementStatusLabels = {
  NO_WINNER: 'Không có người thắng', AWAITING_CHECKOUT: 'Chờ người thắng hoàn tất đơn',
  ORDER_CREATED: 'Đã tạo đơn hàng', CHECKOUT_EXPIRED: 'Hết hạn thanh toán',
}
export const videoStatusLabels = {
  PROCESSING: 'Đang xử lý', READY: 'Sẵn sàng', HIDDEN: 'Đã ẩn', FAILED: 'Xử lý thất bại',
}
export const roleLabels = { USER: 'Người dùng', ADMIN: 'Quản trị viên' }
const groups = {
  order: orderStatusLabels, payment: paymentStatusLabels, auction: auctionStatusLabels,
  settlement: settlementStatusLabels, video: videoStatusLabels,
}
export const getStatusLabel = (value, domain) => {
  const labels = Object.hasOwn(groups, domain) ? groups[domain] : statusLabels
  return Object.hasOwn(labels, value) ? labels[value] : unknownLabel
}
export const getRoleLabel = (value) => Object.hasOwn(roleLabels, value) ? roleLabels[value] : unknownLabel
export const getSettlementLabel = (value) =>
  value == null ? 'Không phát sinh' : getStatusLabel(value, 'settlement')
const paymentMethods = { COD: 'Thanh toán khi nhận hàng', ZALOPAY: 'ZaloPay', VNPAY: 'VNPay' }
export const getPaymentMethodLabel = (value) =>
  Object.hasOwn(paymentMethods, value) ? paymentMethods[value] : unknownLabel
const reconciliationOutcomes = {
  REFUNDED_OUTSIDE: 'Đã hoàn tiền ngoài hệ thống', KEEP_PAID: 'Giữ khoản thanh toán',
}
export const getReconciliationOutcomeLabel = (value) =>
  Object.hasOwn(reconciliationOutcomes, value) ? reconciliationOutcomes[value] : unknownLabel
const cancellationCategories = {
  OUT_OF_STOCK: 'Hết hàng', DAMAGED: 'Hư hỏng', UNFULFILLABLE: 'Không giao được',
}
// Sellers store "<FAILURE_CATEGORY>: <reason>"; buyers and sellers read the Vietnamese label.
export function formatCancellationReason(text) {
  if (typeof text !== 'string') return ''
  const match = /^(OUT_OF_STOCK|DAMAGED|UNFULFILLABLE):\s*/.exec(text)
  if (!match) return text
  const reason = text.slice(match[0].length)
  return reason ? `${cancellationCategories[match[1]]} — ${reason}` : cancellationCategories[match[1]]
}
