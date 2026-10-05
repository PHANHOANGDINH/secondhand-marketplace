export const orderStatuses = {
  PENDING: 'Chờ xác nhận',
  CONFIRMED: 'Đã xác nhận',
  PROCESSING: 'Đang chuẩn bị',
  SHIPPING: 'Đang giao',
  COMPLETED: 'Hoàn tất',
  CANCELLED: 'Đã hủy',
  IN_PROGRESS: 'Đang xử lý',
  PARTIALLY_COMPLETED: 'Hoàn tất một phần',
}
export const nextOrderStatus = {
  PENDING: 'CONFIRMED',
  CONFIRMED: 'PROCESSING',
  PROCESSING: 'SHIPPING',
  SHIPPING: 'COMPLETED',
}
export const paymentStatuses = { PENDING: 'Chưa thu đủ', PAID: 'Đã thu đủ', CANCELLED: 'Đã hủy', FAILED: 'Không thành công', REFUNDED: 'Đã hoàn tiền' }
