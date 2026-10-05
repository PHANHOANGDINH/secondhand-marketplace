import api from './axios'
export const getCsrfCookie = () => api.get('/sanctum/csrf-cookie')
export async function login(credentials) {
  await getCsrfCookie()
  return (await api.post('/api/v1/auth/login', credentials)).data.data
}
export async function register(details) {
  await getCsrfCookie()
  return (await api.post('/api/v1/auth/register', details)).data.data
}
export async function logout() {
  await getCsrfCookie()
  await api.post('/api/v1/auth/logout')
}
export async function me() {
  return (await api.get('/api/v1/auth/me')).data.data
}
export function authError(error) {
  const status = error.response?.status
  if (status === 419) return 'Phiên bảo mật đã hết hạn. Vui lòng thử lại để lấy phiên CSRF mới.'
  if (status === 429) return 'Bạn đã thử quá nhiều lần. Vui lòng chờ rồi thử lại.'
  if (status === 403) return 'Tài khoản hiện không hoạt động. Vui lòng liên hệ quản trị viên.'
  if (status === 422) return 'Vui lòng kiểm tra thông tin đã nhập.'
  if (status === 409) return 'Bạn đã đăng nhập. Hãy tải lại trang để cập nhật phiên.'
  return 'Không thể kết nối máy chủ. Vui lòng thử lại.'
}
