import api from './axios'
import { getCsrfCookie } from './auth.api'
export async function read(path, params) {
  return (await api.get(`/api/v1/${path}`, { params })).data
}
export async function mutate(method, path, data = {}) {
  await getCsrfCookie()
  return (await api.request({ method, url: `/api/v1/${path}`, data })).data
}
export function domainError(error) {
  const status = error.response?.status
  if (status === 401) return 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.'
  if (status === 403)
    return 'Bạn không có quyền thực hiện thao tác này hoặc tài khoản/gian hàng đang bị khóa.'
  if (status === 404) return 'Không tìm thấy dữ liệu hoặc dữ liệu không còn được công khai.'
  if (status === 409)
    return 'Dữ liệu đã thay đổi hoặc đang được sử dụng. Vui lòng tải lại.'
  if (status === 422) return 'Vui lòng kiểm tra các trường bên dưới.'
  if (status === 419) return 'Phiên bảo mật đã hết hạn. Vui lòng thử lại.'
  if (status === 429) return 'Bạn thao tác quá nhanh. Vui lòng chờ rồi thử lại.'
  return 'Không thể kết nối máy chủ. Vui lòng thử lại.'
}
