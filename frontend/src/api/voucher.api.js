import { read, mutate } from './domain.api'
// Price preview for the cart (or the selected cart lines) with voucher codes. Writes nothing.
export const previewCheckout = async (body) => (await mutate('post', 'checkout/preview', body)).data
const area = (admin) => (admin ? 'admin/vouchers' : 'seller/vouchers')
export const listVouchers = (admin, params) => read(area(admin), params)
export const createVoucher = async (admin, data) => (await mutate('post', area(admin), data)).data
export const updateVoucher = async (admin, id, data) =>
  (await mutate('patch', `${area(admin)}/${id}`, data)).data
export const deactivateVoucher = async (admin, id) =>
  (await mutate('delete', `${area(admin)}/${id}`)).data
