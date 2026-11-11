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
/** Posts the signed link from the mail; the API checks its signature and expiry. */
export async function verifyEmail({ id, hash, expires, signature }) {
  await getCsrfCookie()
  const path = `/api/v1/auth/email/verify/${encodeURIComponent(id)}/${encodeURIComponent(hash)}`
  return (await api.post(path, null, { params: { expires, signature } })).data
}
/** Confirms the address with the six-digit code from the same mail. */
export async function verifyEmailCode(details) {
  await getCsrfCookie()
  return (await api.post('/api/v1/auth/email/verify-code', details)).data
}
export async function resendVerification(details) {
  await getCsrfCookie()
  return (await api.post('/api/v1/auth/email/resend', details)).data
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
  if (status === 419) return 'Phiên bảo mật đã hết hạn. Vui lòng thử lại.'
  if (status === 429) return 'Bạn đã thử quá nhiều lần. Vui lòng chờ rồi thử lại.'
  if (status === 403) return 'Tài khoản hiện không hoạt động. Vui lòng liên hệ quản trị viên.'
  if (status === 422) return 'Vui lòng kiểm tra thông tin đã nhập.'
  if (status === 409) return 'Bạn đã đăng nhập. Hãy tải lại trang để cập nhật phiên.'
  return 'Không thể kết nối máy chủ. Vui lòng thử lại.'
}

export async function forgotPassword(details) {
  await getCsrfCookie()
  return (await api.post('/api/v1/auth/forgot-password', details)).data
}
/** A signed-in user's new password; returns the user (must_change_password cleared). */
export async function changePassword(details) {
  await getCsrfCookie()
  return (await api.patch('/api/v1/auth/password', details)).data.data
}
export async function resetPassword(details) {
  await getCsrfCookie()
  return (await api.post('/api/v1/auth/reset-password', details)).data
}
