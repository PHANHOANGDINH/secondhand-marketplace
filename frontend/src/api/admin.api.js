import { read, mutate } from './domain.api'
export const getAdminDashboard = async () => (await read('admin/dashboard')).data
export const listAdminUsers = (params) => read('admin/users', params)
export const getAdminUser = async (id) => (await read(`admin/users/${id}`)).data
export const moderateUser = async (id, data) =>
  (await mutate('patch', `admin/users/${id}/status`, data)).data
export const listAdminReviews = (params) => read('admin/reviews', params)
export const getAdminReview = async (id) => (await read(`admin/reviews/${id}`)).data
export const moderateReview = async (id, data) =>
  (await mutate('patch', `admin/reviews/${id}/status`, data)).data
export const listAdminOrders = (params) => read('admin/orders', params)
export const getAdminOrder = async (id) => (await read(`admin/orders/${id}`)).data
export const getAdminActionCenter = async () => (await read('admin/action-center')).data
export const listAdminPayments = (params) => read('admin/payments', params)
export const getAdminPayment = async (id) =>
  (await read(`admin/payments/${encodeURIComponent(id)}`)).data
export const resolveReconciliation = async (id, data) =>
  (await mutate('post', `admin/payments/${encodeURIComponent(id)}/reconciliation`, data)).data
export const listAdminAuctions = (params) => read('admin/auctions', params)
export const cancelAdminAuction = async (id, data) =>
  (await mutate('post', `admin/auctions/${encodeURIComponent(id)}/cancel`, data)).data
