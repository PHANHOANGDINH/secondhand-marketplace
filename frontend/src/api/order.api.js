import { read, mutate } from './domain.api'
import api from './axios'
import { getCsrfCookie } from './auth.api'
export async function checkout(data, key) {
  await getCsrfCookie()
  return (await api.post('/api/v1/checkout', data, { headers: { 'Idempotency-Key': key } })).data
    .data
}
export const listOrders = (params) => read('orders', params)
export const getOrder = async (id) => (await read(`orders/${id}`)).data
export const cancelShopOrder = async (order, part, data) =>
  (await mutate('post', `orders/${order}/shop-orders/${part}/cancel`, data)).data
export const listSellerOrders = (params) => read('seller/orders', params)
export const getSellerOrder = async (id) => (await read(`seller/orders/${id}`)).data
export const updateSellerOrder = async (id, data) =>
  (await mutate('patch', `seller/orders/${id}/status`, data)).data
