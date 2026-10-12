export const reportTypes = {
  PRODUCT: 'Sản phẩm',
  SHOP: 'Gian hàng',
  VIDEO_COMMENT: 'Bình luận video',
}
export const reportStatuses = {
  PENDING: 'Chờ xử lý',
  IN_REVIEW: 'Đang xem xét',
  RESOLVED: 'Đã xử lý',
  DISMISSED: 'Không vi phạm',
}
export const reportReasons = {
  SCAM: 'Có dấu hiệu lừa đảo',
  COUNTERFEIT: 'Nghi hàng giả / hàng nhái',
  MISLEADING: 'Thông tin sai lệch',
  PROHIBITED_CONTENT: 'Nội dung / sản phẩm bị cấm',
  SPAM: 'Spam / quảng cáo rác',
  HARASSMENT: 'Quấy rối',
  INAPPROPRIATE_CONTENT: 'Nội dung không phù hợp',
  OTHER: 'Lý do khác',
}
export const reportActions = {
  PENDING: ['IN_REVIEW', 'DISMISSED'],
  IN_REVIEW: ['RESOLVED', 'DISMISSED'],
  RESOLVED: [],
  DISMISSED: [],
}
export const reportDate = (value) =>
  value
    ? new Date(value).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' })
    : 'Chưa xử lý'
