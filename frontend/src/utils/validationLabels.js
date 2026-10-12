import { getStatusLabel } from './labels'

const fields = {
  name: 'Tên', email: 'Địa chỉ thư điện tử', password: 'Mật khẩu',
  password_confirmation: 'Mật khẩu xác nhận', phone: 'Số điện thoại',
  token: 'Liên kết đặt lại mật khẩu', body: 'Nội dung', amount: 'Số tiền',
  price: 'Giá', stock_quantity: 'Số lượng tồn kho', quantity: 'Số lượng',
  status: 'Trạng thái', expected_status: 'Trạng thái', role: 'Vai trò',
  product_id: 'Sản phẩm', category_id: 'Danh mục', parent_id: 'Danh mục cha',
  address_id: 'Địa chỉ giao hàng', recipient_name: 'Tên người nhận',
  address_line: 'Địa chỉ', province: 'Tỉnh/thành phố', district: 'Quận/huyện',
  ward: 'Phường/xã', description: 'Mô tả', title: 'Tiêu đề', reason: 'Lý do',
  comment: 'Nhận xét', rating: 'Số sao', start_price: 'Giá khởi điểm',
  bid_increment: 'Bước giá', starts_at: 'Thời gian bắt đầu', ends_at: 'Thời gian kết thúc',
  payment_method: 'Phương thức thanh toán', file: 'Tệp', video: 'Video', images: 'Ảnh',
  from: 'Ngày bắt đầu', to: 'Ngày kết thúc', min_price: 'Giá thấp nhất', max_price: 'Giá cao nhất',
}
export function localizeValidationMessage(message, field = '') {
  const key = field.replace(/\.\d+(?:\..*)?$/, '')
  const label = Object.hasOwn(fields, key) ? fields[key] : 'Trường này'
  const text = typeof message === 'string' ? message : ''
  // Never echo exception/SQL details or unrecognized English server messages.
  if (/[à-ỹÀ-Ỹ]/u.test(text) && !/SQLSTATE|Exception|Traceback|stack trace|\/vendor\//i.test(text))
    return text.replace(/\b[A-Z][A-Z_]{2,}\b/g, (value) =>
      ['JPEG', 'PNG', 'MP4', 'WEBM'].includes(value) ? value : getStatusLabel(value))
  if (/required/i.test(text)) return `${label} là bắt buộc.`
  if (/credentials.*incorrect/i.test(text)) return 'Thông tin đăng nhập không chính xác.'
  if (/valid email/i.test(text)) return 'Địa chỉ thư điện tử không hợp lệ.'
  if (/confirmation|confirmed/i.test(text)) return 'Mật khẩu xác nhận chưa khớp.'
  if (/already.*taken/i.test(text)) return `${label} đã được sử dụng.`
  const min = /at least (\d+) characters/i.exec(text)
  if (min) return `${label} phải có ít nhất ${min[1]} ký tự.`
  const max = /(?:greater than|more than) (\d+) characters/i.exec(text)
  if (max) return `${label} không được vượt quá ${max[1]} ký tự.`
  return `${label} không hợp lệ. Vui lòng kiểm tra lại.`
}
export function localizeValidationErrors(errors = {}) {
  return Object.fromEntries(Object.entries(errors || {}).map(([field, messages]) => [
    field, (Array.isArray(messages) ? messages : [messages]).map((message) =>
      localizeValidationMessage(message, field)),
  ]))
}
